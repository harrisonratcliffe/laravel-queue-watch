<?php

namespace HarrisonRatcliffe\QueueWatch\Watching;

final class PollingWatcher implements Watcher
{
    /** @var array<string, array{mtime: int, size: int}> */
    private array $fingerprints = [];

    /**
     * @param  string[]  $paths  absolute file/directory paths to watch
     * @param  string[]  $ignore  absolute directory paths to prune during traversal
     * @param  string[]  $extensions  extensions (no leading dot) to include, e.g. ['php']
     */
    public function __construct(
        private readonly array $paths,
        private readonly array $ignore = [],
        private readonly array $extensions = ['php'],
    ) {}

    public function baseline(): void
    {
        $this->fingerprints = $this->scan();
    }

    public function poll(): array
    {
        $current = $this->scan();
        $changes = [];

        foreach ($current as $path => $fingerprint) {
            if (! isset($this->fingerprints[$path])) {
                $changes[] = new FileChange($path, FileChangeType::Created);
            } elseif ($this->fingerprints[$path] !== $fingerprint) {
                $changes[] = new FileChange($path, FileChangeType::Modified);
            }
        }

        foreach ($this->fingerprints as $path => $fingerprint) {
            if (! isset($current[$path])) {
                $changes[] = new FileChange($path, FileChangeType::Deleted);
            }
        }

        $this->fingerprints = $current;

        return $changes;
    }

    /**
     * @return array<string, array{mtime: int, size: int}>
     */
    private function scan(): array
    {
        $result = [];

        foreach ($this->paths as $path) {
            if (is_file($path)) {
                // A path explicitly configured as a file (e.g. .env) is always
                // watched verbatim, bypassing extension filtering.
                $result[$path] = $this->fingerprint($path);

                continue;
            }

            if (! is_dir($path)) {
                continue;
            }

            foreach ($this->walk($path) as $file) {
                $result[$file] = $this->fingerprint($file);
            }
        }

        return $result;
    }

    /**
     * @return \Generator<string>
     */
    private function walk(string $directory): \Generator
    {
        try {
            $root = new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS);
        } catch (\UnexpectedValueException) {
            // The watched root itself is unreadable (permissions, or it was
            // removed between is_dir() and here). Nothing to report - a later
            // poll picks it up again if it becomes readable.
            return;
        }

        $filter = new \RecursiveCallbackFilterIterator(
            $root,
            function (\SplFileInfo $file): bool {
                if ($file->isDir()) {
                    // Returning false here prunes the branch during traversal -
                    // RecursiveIteratorIterator never descends into it.
                    return ! in_array($file->getPathname(), $this->ignore, true);
                }

                return in_array($file->getExtension(), $this->extensions, true);
            }
        );

        // CATCH_GET_CHILD: a single unreadable subdirectory must not take down
        // the whole watch loop. Without it, RecursiveDirectoryIterator throws
        // UnexpectedValueException out of getChildren() and kills the command -
        // an easy thing to hit on Docker bind mounts and root-owned artifacts.
        $iterator = new \RecursiveIteratorIterator(
            $filter,
            \RecursiveIteratorIterator::LEAVES_ONLY,
            \RecursiveIteratorIterator::CATCH_GET_CHILD
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            yield $file->getPathname();
        }
    }

    /**
     * @return array{mtime: int, size: int}
     */
    private function fingerprint(string $path): array
    {
        return [
            'mtime' => (int) (@filemtime($path) ?: 0),
            'size' => (int) (@filesize($path) ?: 0),
        ];
    }
}

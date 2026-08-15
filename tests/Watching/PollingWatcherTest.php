<?php

use HarrisonRatcliffe\QueueWatch\Watching\FileChangeType;
use HarrisonRatcliffe\QueueWatch\Watching\PollingWatcher;

function makeTempDir(): string
{
    $dir = sys_get_temp_dir().'/queue-watch-test-'.bin2hex(random_bytes(8));
    mkdir($dir, 0777, true);

    return $dir;
}

function removeTempDir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }

    rmdir($dir);
}

beforeEach(function () {
    $this->dir = makeTempDir();
});

afterEach(function () {
    removeTempDir($this->dir);
});

it('reports no changes when nothing has changed', function () {
    file_put_contents($this->dir.'/foo.php', '<?php // v1');

    $watcher = new PollingWatcher([$this->dir]);
    $watcher->baseline();

    expect($watcher->poll())->toBeEmpty();
});

it('detects a created file', function () {
    $watcher = new PollingWatcher([$this->dir]);
    $watcher->baseline();

    file_put_contents($this->dir.'/new.php', '<?php // new');

    $changes = $watcher->poll();

    expect($changes)->toHaveCount(1)
        ->and($changes[0]->type)->toBe(FileChangeType::Created)
        ->and($changes[0]->path)->toBe($this->dir.'/new.php');
});

it('detects a modified file', function () {
    $path = $this->dir.'/foo.php';
    file_put_contents($path, '<?php // short');

    $watcher = new PollingWatcher([$this->dir]);
    $watcher->baseline();

    // Change the file size so the fingerprint differs regardless of mtime
    // resolution on the filesystem under test.
    file_put_contents($path, '<?php // a much longer body than before');

    $changes = $watcher->poll();

    expect($changes)->toHaveCount(1)
        ->and($changes[0]->type)->toBe(FileChangeType::Modified)
        ->and($changes[0]->path)->toBe($path);
});

it('detects a deleted file', function () {
    $path = $this->dir.'/foo.php';
    file_put_contents($path, '<?php // v1');

    $watcher = new PollingWatcher([$this->dir]);
    $watcher->baseline();

    unlink($path);

    $changes = $watcher->poll();

    expect($changes)->toHaveCount(1)
        ->and($changes[0]->type)->toBe(FileChangeType::Deleted)
        ->and($changes[0]->path)->toBe($path);
});

it('only watches configured extensions', function () {
    $watcher = new PollingWatcher([$this->dir], [], ['php']);
    $watcher->baseline();

    file_put_contents($this->dir.'/style.css', 'body {}');
    file_put_contents($this->dir.'/script.php', '<?php // ok');

    $changes = $watcher->poll();

    expect($changes)->toHaveCount(1)
        ->and($changes[0]->path)->toBe($this->dir.'/script.php');
});

it('never descends into ignored directories', function () {
    $ignoredDir = $this->dir.'/vendor';
    mkdir($ignoredDir);
    $markerPath = $ignoredDir.'/marker.php';
    file_put_contents($markerPath, '<?php // v1');

    $watcher = new PollingWatcher([$this->dir], [$ignoredDir]);
    $watcher->baseline();

    file_put_contents($markerPath, '<?php // modified, should never be seen');
    unlink($markerPath);
    file_put_contents($markerPath, '<?php // recreated, still ignored');

    expect($watcher->poll())->toBeEmpty();
});

it('keeps scanning readable files when a subdirectory is unreadable', function () {
    // Regression: RecursiveDirectoryIterator throws UnexpectedValueException
    // out of getChildren() on a permission-denied directory, which used to
    // take down the entire watch loop. Easy to hit on Docker bind mounts and
    // root-owned artifacts - exactly this package's target environment.
    $locked = $this->dir.'/locked';
    mkdir($locked);
    file_put_contents($locked.'/hidden.php', '<?php');
    file_put_contents($this->dir.'/visible.php', '<?php // v1');
    chmod($locked, 0000);

    $watcher = new PollingWatcher([$this->dir]);
    $watcher->baseline();

    file_put_contents($this->dir.'/visible.php', '<?php // a much longer body than before');

    $changes = $watcher->poll();

    chmod($locked, 0755);

    expect($changes)->toHaveCount(1)
        ->and($changes[0]->path)->toBe($this->dir.'/visible.php')
        ->and($changes[0]->type)->toBe(FileChangeType::Modified);
})->skip(
    fn () => PHP_OS_FAMILY === 'Windows' || (function_exists('posix_geteuid') && posix_geteuid() === 0),
    'Requires POSIX permissions and a non-root user.'
);

it('does not throw when a watched root directory is unreadable', function () {
    $root = $this->dir.'/root';
    mkdir($root);
    file_put_contents($root.'/thing.php', '<?php');
    chmod($root, 0000);

    $watcher = new PollingWatcher([$root]);

    try {
        $watcher->baseline();
        $changes = $watcher->poll();
    } finally {
        chmod($root, 0755);
    }

    expect($changes)->toBeEmpty();
})->skip(
    fn () => PHP_OS_FAMILY === 'Windows' || (function_exists('posix_geteuid') && posix_geteuid() === 0),
    'Requires POSIX permissions and a non-root user.'
);

it('watches an explicitly configured file path directly, bypassing extension filtering', function () {
    $envPath = $this->dir.'/.env';
    file_put_contents($envPath, 'APP_ENV=local');

    $watcher = new PollingWatcher([$envPath], [], ['php']);
    $watcher->baseline();

    file_put_contents($envPath, 'APP_ENV=production');

    $changes = $watcher->poll();

    expect($changes)->toHaveCount(1)
        ->and($changes[0]->type)->toBe(FileChangeType::Modified)
        ->and($changes[0]->path)->toBe($envPath);
});

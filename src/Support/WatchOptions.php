<?php

namespace HarrisonRatcliffe\QueueWatch\Support;

final class WatchOptions
{
    /**
     * @param  string[]  $paths  absolute
     * @param  string[]  $ignore  absolute
     * @param  string[]  $extensions
     * @param  string[]  $workerCommand  full argv for the queue:work child process
     */
    public function __construct(
        public readonly array $paths,
        public readonly array $ignore,
        public readonly array $extensions,
        public readonly int $pollIntervalMs,
        public readonly int $debounceMs,
        public readonly int $restartTimeoutSeconds,
        public readonly array $workerCommand,
    ) {}
}

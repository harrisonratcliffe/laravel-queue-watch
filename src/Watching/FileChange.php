<?php

namespace HarrisonRatcliffe\QueueWatch\Watching;

final class FileChange
{
    public function __construct(
        public readonly string $path,
        public readonly FileChangeType $type,
    ) {}
}

<?php

namespace HarrisonRatcliffe\QueueWatch\Watching;

interface Watcher
{
    /**
     * Take an initial snapshot without reporting any changes, so the first
     * poll() call doesn't treat every existing file as "created".
     */
    public function baseline(): void;

    /**
     * @return FileChange[]
     */
    public function poll(): array;
}

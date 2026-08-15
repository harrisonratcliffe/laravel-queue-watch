<?php

namespace HarrisonRatcliffe\QueueWatch\Watching;

final class Debouncer
{
    private ?float $windowStartedAt = null;

    private ?string $reason = null;

    /**
     * @param  \Closure(): float|null  $now  returns the current time in milliseconds
     */
    public function __construct(
        private readonly int $debounceMs,
        private ?\Closure $now = null,
    ) {
        $this->now ??= static fn (): float => microtime(true) * 1000;
    }

    public function recordChange(string $reason): void
    {
        // Every new change resets/extends the quiet window (true debounce, not
        // throttle) - a multi-file save keeps postponing the restart until
        // things go quiet for $debounceMs.
        $this->windowStartedAt = ($this->now)();
        $this->reason ??= $reason;
    }

    public function isReady(): bool
    {
        if ($this->windowStartedAt === null) {
            return false;
        }

        return ($this->now)() - $this->windowStartedAt >= $this->debounceMs;
    }

    public function consume(): ?string
    {
        $reason = $this->reason;
        $this->windowStartedAt = null;
        $this->reason = null;

        return $reason;
    }
}

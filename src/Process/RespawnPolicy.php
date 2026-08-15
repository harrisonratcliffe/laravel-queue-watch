<?php

namespace HarrisonRatcliffe\QueueWatch\Process;

/**
 * Decides how aggressively to respawn a worker that exited on its own.
 *
 * A worker that dies instantly - a bad `worker_command`, a missing artisan, a
 * boot-time exception - would otherwise be restarted on every 50ms tick, which
 * burns CPU and scrolls the underlying error past the user faster than they can
 * read it. Fast, repeated exits back off exponentially and eventually give up;
 * a worker that stayed up long enough to be doing real work resets the count.
 */
final class RespawnPolicy
{
    private int $consecutiveFailures = 0;

    /**
     * @param  int  $healthyRuntimeMs  a worker alive at least this long counts as healthy, not a failure
     * @param  int  $baseDelayMs  delay after the first fast exit; doubles each time
     * @param  int  $maxDelayMs  ceiling for the backoff delay
     * @param  int  $failureLimit  consecutive fast exits before giving up entirely
     */
    public function __construct(
        private readonly int $healthyRuntimeMs = 5_000,
        private readonly int $baseDelayMs = 250,
        private readonly int $maxDelayMs = 10_000,
        private readonly int $failureLimit = 10,
    ) {}

    /**
     * Record that a worker which ran for $runtimeMs has exited.
     */
    public function recordExit(int $runtimeMs): void
    {
        if ($runtimeMs >= $this->healthyRuntimeMs) {
            $this->consecutiveFailures = 0;

            return;
        }

        $this->consecutiveFailures++;
    }

    /**
     * How long to wait before respawning, in milliseconds.
     */
    public function delayMs(): int
    {
        if ($this->consecutiveFailures === 0) {
            return 0;
        }

        return (int) min(
            $this->maxDelayMs,
            $this->baseDelayMs * (2 ** ($this->consecutiveFailures - 1))
        );
    }

    public function shouldGiveUp(): bool
    {
        return $this->consecutiveFailures >= $this->failureLimit;
    }

    public function consecutiveFailures(): int
    {
        return $this->consecutiveFailures;
    }

    /**
     * Clear the failure streak - used after a deliberate, file-change-driven
     * restart, which is never evidence of a broken worker.
     */
    public function reset(): void
    {
        $this->consecutiveFailures = 0;
    }
}

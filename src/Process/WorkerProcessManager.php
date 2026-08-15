<?php

namespace HarrisonRatcliffe\QueueWatch\Process;

final class WorkerProcessManager
{
    private const SIGTERM = 15;

    private const SIGKILL = 9;

    private WorkerProcess $process;

    /**
     * @param  \Closure(): WorkerProcess  $processFactory  builds a fresh WorkerProcess each (re)start
     * @param  \Closure(int): void|null  $sleep  fn(int $milliseconds): void
     * @param  \Closure(): float|null  $clock  fn(): float, milliseconds
     */
    public function __construct(
        private readonly \Closure $processFactory,
        private readonly int $restartTimeoutSeconds,
        private readonly bool $posixSignalsAvailable,
        private ?\Closure $sleep = null,
        private ?\Closure $clock = null,
    ) {
        $this->sleep ??= static fn (int $ms) => usleep($ms * 1000);
        $this->clock ??= static fn (): float => microtime(true) * 1000;
        $this->process = ($this->processFactory)();
    }

    public function start(): void
    {
        $this->process->start();
    }

    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    public function pumpOutput(): void
    {
        $this->process->pumpOutput();
    }

    public function exitCode(): ?int
    {
        return $this->process->exitCode();
    }

    public function restart(): void
    {
        $this->gracefulStop();
        $this->process = ($this->processFactory)();
        $this->process->start();
    }

    public function stop(): void
    {
        $this->gracefulStop();
    }

    private function gracefulStop(): void
    {
        if (! $this->process->isRunning()) {
            return;
        }

        if (! $this->posixSignalsAvailable) {
            // Windows fallback: no reliable POSIX signals, so we can't ask the
            // worker to finish its current job first. Documented limitation.
            $this->process->stopImmediately();

            return;
        }

        $this->process->signal(self::SIGTERM);

        $deadline = ($this->clock)() + ($this->restartTimeoutSeconds * 1000);

        while ($this->process->isRunning() && ($this->clock)() < $deadline) {
            ($this->sleep)(50);
        }

        if ($this->process->isRunning()) {
            $this->process->signal(self::SIGKILL);
        }
    }
}

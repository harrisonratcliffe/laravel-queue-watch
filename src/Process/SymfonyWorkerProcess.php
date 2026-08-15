<?php

namespace HarrisonRatcliffe\QueueWatch\Process;

use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

final class SymfonyWorkerProcess implements WorkerProcess
{
    private Process $process;

    /**
     * @param  string[]  $command
     * @param  \Closure(OutputChannel, string): void  $outputHandler
     */
    public function __construct(
        private readonly array $command,
        private readonly string $workingDirectory,
        private readonly \Closure $outputHandler,
    ) {
        $this->process = $this->newProcess();
    }

    public function start(): void
    {
        $this->process = $this->newProcess();
        $this->process->start(function (string $type, string $buffer): void {
            ($this->outputHandler)(
                $type === Process::ERR ? OutputChannel::Err : OutputChannel::Out,
                $buffer
            );
        });
    }

    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    public function pumpOutput(): void
    {
        // Symfony flushes buffered pipe output into the start()-time callback
        // whenever the process is polled - isRunning() is enough to pump it.
        $this->process->isRunning();
    }

    public function signal(int $signal): void
    {
        if (! $this->process->isRunning()) {
            return;
        }

        try {
            $this->process->signal($signal);
        } catch (RuntimeException) {
            // Signals unsupported on this platform (e.g. Windows without
            // pcntl); WorkerProcessManager decides the fallback, not this class.
        }
    }

    public function stopImmediately(): void
    {
        $this->process->stop(0, 9);
    }

    public function exitCode(): ?int
    {
        return $this->process->getExitCode();
    }

    private function newProcess(): Process
    {
        $process = new Process($this->command, $this->workingDirectory, null, null, null);

        if ($this->ttySupported()) {
            $process->setTty(true);
        }

        return $process;
    }

    private function ttySupported(): bool
    {
        return Process::isTtySupported()
            && function_exists('stream_isatty')
            && @stream_isatty(STDOUT)
            && @stream_isatty(STDERR);
    }
}

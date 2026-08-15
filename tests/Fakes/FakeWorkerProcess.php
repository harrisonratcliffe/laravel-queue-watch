<?php

namespace HarrisonRatcliffe\QueueWatch\Tests\Fakes;

use HarrisonRatcliffe\QueueWatch\Process\WorkerProcess;

final class FakeWorkerProcess implements WorkerProcess
{
    /** @var int[] */
    public array $signalsReceived = [];

    public bool $started = false;

    private bool $running = true;

    /**
     * Test hook: call from a fake `sleep` closure to simulate the child
     * exiting after receiving SIGTERM.
     */
    public function stopRunning(): void
    {
        $this->running = false;
    }

    public function start(): void
    {
        $this->started = true;
        $this->running = true;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function pumpOutput(): void {}

    public function signal(int $signal): void
    {
        $this->signalsReceived[] = $signal;
    }

    public function stopImmediately(): void
    {
        $this->running = false;
    }

    public function exitCode(): ?int
    {
        return $this->running ? null : 0;
    }
}

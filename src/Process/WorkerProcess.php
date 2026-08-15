<?php

namespace HarrisonRatcliffe\QueueWatch\Process;

interface WorkerProcess
{
    public function start(): void;

    public function isRunning(): bool;

    /**
     * Flush any buffered stdout/stderr through to the configured output handler.
     */
    public function pumpOutput(): void;

    /**
     * No-op if unsupported or not running; the caller decides the fallback behaviour.
     */
    public function signal(int $signal): void;

    /**
     * Hard kill - used as the Windows fallback path.
     */
    public function stopImmediately(): void;

    public function exitCode(): ?int;
}

<?php

use HarrisonRatcliffe\QueueWatch\Process\WorkerProcessManager;
use HarrisonRatcliffe\QueueWatch\Tests\Fakes\FakeClock;
use HarrisonRatcliffe\QueueWatch\Tests\Fakes\FakeWorkerProcess;

it('stops gracefully with only SIGTERM when the worker exits promptly', function () {
    $clock = new FakeClock;
    $process = new FakeWorkerProcess;
    $process->start();

    $manager = new WorkerProcessManager(
        processFactory: fn () => $process,
        restartTimeoutSeconds: 10,
        posixSignalsAvailable: true,
        sleep: function (int $ms) use ($clock, $process) {
            $clock->advance($ms);
            // Simulate the worker finishing its job and exiting shortly after SIGTERM.
            if (in_array(15, $process->signalsReceived, true)) {
                $process->stopRunning();
            }
        },
        clock: fn () => $clock->now(),
    );

    $manager->stop();

    expect($process->signalsReceived)->toBe([15]);
});

it('escalates to SIGKILL only once the restart timeout has elapsed', function () {
    $clock = new FakeClock;
    $process = new FakeWorkerProcess;
    $process->start();

    $manager = new WorkerProcessManager(
        processFactory: fn () => $process,
        restartTimeoutSeconds: 10,
        posixSignalsAvailable: true,
        sleep: function (int $ms) use ($clock) {
            // Worker never exits on its own within this test.
            $clock->advance($ms);
        },
        clock: fn () => $clock->now(),
    );

    $manager->stop();

    expect($process->signalsReceived)->toBe([15, 9]);
});

it('does not send any signal if the worker is already stopped', function () {
    $clock = new FakeClock;
    $process = new FakeWorkerProcess;
    $process->start();
    $process->stopRunning();

    $manager = new WorkerProcessManager(
        processFactory: fn () => $process,
        restartTimeoutSeconds: 10,
        posixSignalsAvailable: true,
        sleep: fn (int $ms) => $clock->advance($ms),
        clock: fn () => $clock->now(),
    );

    $manager->stop();

    expect($process->signalsReceived)->toBe([]);
});

it('falls back to an immediate stop when POSIX signals are unavailable', function () {
    $clock = new FakeClock;
    $process = new FakeWorkerProcess;
    $process->start();

    $manager = new WorkerProcessManager(
        processFactory: fn () => $process,
        restartTimeoutSeconds: 10,
        posixSignalsAvailable: false,
        sleep: fn (int $ms) => $clock->advance($ms),
        clock: fn () => $clock->now(),
    );

    $manager->stop();

    expect($process->signalsReceived)->toBe([])
        ->and($process->isRunning())->toBeFalse();
});

it('restarts by gracefully stopping the old process and starting a fresh one', function () {
    $clock = new FakeClock;
    $oldProcess = new FakeWorkerProcess;
    $oldProcess->start();
    $newProcess = new FakeWorkerProcess;

    $factoryCalls = 0;

    $manager = new WorkerProcessManager(
        // The factory is called once by the constructor (returning $oldProcess,
        // matching the already-started process above) and once again by
        // restart() (returning $newProcess). $factoryCalls is captured by
        // reference so the count persists across both invocations.
        processFactory: function () use (&$factoryCalls, $oldProcess, $newProcess) {
            $factoryCalls++;

            return $factoryCalls === 1 ? $oldProcess : $newProcess;
        },
        restartTimeoutSeconds: 10,
        posixSignalsAvailable: true,
        sleep: function (int $ms) use ($clock, $oldProcess) {
            $clock->advance($ms);
            if (in_array(15, $oldProcess->signalsReceived, true)) {
                $oldProcess->stopRunning();
            }
        },
        clock: fn () => $clock->now(),
    );

    $manager->restart();

    expect($oldProcess->signalsReceived)->toBe([15])
        ->and($newProcess->started)->toBeTrue();
});

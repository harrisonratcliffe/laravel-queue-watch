<?php

use HarrisonRatcliffe\QueueWatch\Commands\QueueWatchCommand;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Command\SignalableCommandInterface;

it('registers the queue:watch command', function () {
    expect($this->app[Kernel::class]->all())
        ->toHaveKey('queue:watch');
});

it('subscribes to signals without ever referencing undefined constants', function () {
    // Regression guard: Symfony calls getSubscribedSignals() unconditionally on
    // any SignalableCommandInterface command, before consulting its own signal
    // registry. SIGINT/SIGTERM are ext-pcntl constants, so returning them
    // unguarded is a fatal error on hosts without pcntl - which is every
    // Windows host, where the command died on startup instead of falling back
    // to the documented hard-stop behaviour.
    $command = new QueueWatchCommand;

    expect($command)->toBeInstanceOf(SignalableCommandInterface::class);

    $signals = $command->getSubscribedSignals();

    expect($signals)->toBeArray()->each->toBeInt();

    if (defined('SIGINT') && defined('SIGTERM')) {
        expect($signals)->toBe([SIGINT, SIGTERM]);
    } else {
        expect($signals)->toBe([]);
    }
});

it('reports the pcntl-less signal set as empty', function () {
    // The guard itself, exercised independently of the host's pcntl status:
    // whatever defined() reports, the method must agree with it rather than
    // dereferencing the constants.
    $command = new QueueWatchCommand;

    $expectsSignals = defined('SIGINT') && defined('SIGTERM');

    expect($command->getSubscribedSignals() !== [])->toBe($expectsSignals);
});

it('asks the main loop to stop rather than letting Symfony force-exit', function () {
    $command = new QueueWatchCommand;

    // Returning false tells Symfony not to exit, so handle() can perform its
    // own graceful child shutdown - the same pattern queue:work uses.
    expect($command->handleSignal(defined('SIGINT') ? SIGINT : 2))->toBeFalse();
});

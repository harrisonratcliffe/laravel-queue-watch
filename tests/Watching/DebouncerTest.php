<?php

use HarrisonRatcliffe\QueueWatch\Tests\Fakes\FakeClock;
use HarrisonRatcliffe\QueueWatch\Watching\Debouncer;

it('is not ready until the debounce window has elapsed', function () {
    $clock = new FakeClock;
    $debouncer = new Debouncer(300, fn () => $clock->now());

    $debouncer->recordChange('app/Foo.php');

    expect($debouncer->isReady())->toBeFalse();

    $clock->advance(299);
    expect($debouncer->isReady())->toBeFalse();

    $clock->advance(1);
    expect($debouncer->isReady())->toBeTrue();
});

it('collapses rapid successive changes into a single ready restart, keyed on the last change', function () {
    $clock = new FakeClock;
    $debouncer = new Debouncer(300, fn () => $clock->now());

    $debouncer->recordChange('app/One.php');
    $clock->advance(200);

    $debouncer->recordChange('app/Two.php');
    $clock->advance(200);
    expect($debouncer->isReady())->toBeFalse(); // only 200ms since the last change

    $debouncer->recordChange('app/Three.php');
    $clock->advance(300);
    expect($debouncer->isReady())->toBeTrue();

    // The first reason across the whole coalesced window is preserved.
    expect($debouncer->consume())->toBe('app/One.php');
});

it('is idle after consume until a new change is recorded', function () {
    $clock = new FakeClock;
    $debouncer = new Debouncer(300, fn () => $clock->now());

    $debouncer->recordChange('app/Foo.php');
    $clock->advance(300);
    expect($debouncer->isReady())->toBeTrue();

    $debouncer->consume();
    expect($debouncer->isReady())->toBeFalse();

    $clock->advance(1000);
    expect($debouncer->isReady())->toBeFalse();
});

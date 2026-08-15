<?php

use HarrisonRatcliffe\QueueWatch\Process\RespawnPolicy;

it('respawns immediately after a worker that stayed up long enough to be healthy', function () {
    $policy = new RespawnPolicy(healthyRuntimeMs: 5_000);

    $policy->recordExit(30_000);

    expect($policy->delayMs())->toBe(0)
        ->and($policy->consecutiveFailures())->toBe(0)
        ->and($policy->shouldGiveUp())->toBeFalse();
});

it('backs off exponentially while the worker keeps exiting immediately', function () {
    $policy = new RespawnPolicy(healthyRuntimeMs: 5_000, baseDelayMs: 250, maxDelayMs: 10_000);

    $delays = [];
    for ($i = 0; $i < 6; $i++) {
        $policy->recordExit(10);
        $delays[] = $policy->delayMs();
    }

    expect($delays)->toBe([250, 500, 1000, 2000, 4000, 8000]);
});

it('caps the backoff delay at the configured maximum', function () {
    $policy = new RespawnPolicy(healthyRuntimeMs: 5_000, baseDelayMs: 250, maxDelayMs: 1_000);

    for ($i = 0; $i < 8; $i++) {
        $policy->recordExit(10);
    }

    expect($policy->delayMs())->toBe(1_000);
});

it('gives up once the consecutive fast-exit limit is reached', function () {
    $policy = new RespawnPolicy(healthyRuntimeMs: 5_000, failureLimit: 3);

    $policy->recordExit(10);
    expect($policy->shouldGiveUp())->toBeFalse();

    $policy->recordExit(10);
    expect($policy->shouldGiveUp())->toBeFalse();

    $policy->recordExit(10);
    expect($policy->shouldGiveUp())->toBeTrue()
        ->and($policy->consecutiveFailures())->toBe(3);
});

it('clears the failure streak when a worker survives the healthy threshold', function () {
    $policy = new RespawnPolicy(healthyRuntimeMs: 5_000, failureLimit: 3);

    $policy->recordExit(10);
    $policy->recordExit(10);
    expect($policy->consecutiveFailures())->toBe(2);

    // A worker that ran for a full minute is evidence the problem is gone.
    $policy->recordExit(60_000);

    expect($policy->consecutiveFailures())->toBe(0)
        ->and($policy->delayMs())->toBe(0)
        ->and($policy->shouldGiveUp())->toBeFalse();
});

it('treats a runtime exactly at the healthy threshold as healthy', function () {
    $policy = new RespawnPolicy(healthyRuntimeMs: 5_000);

    $policy->recordExit(5_000);

    expect($policy->consecutiveFailures())->toBe(0);
});

it('resets on a deliberate restart so a file change never counts as a failure', function () {
    $policy = new RespawnPolicy(healthyRuntimeMs: 5_000, failureLimit: 3);

    $policy->recordExit(10);
    $policy->recordExit(10);

    $policy->reset();

    expect($policy->consecutiveFailures())->toBe(0)
        ->and($policy->delayMs())->toBe(0);
});

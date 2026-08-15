<?php

namespace HarrisonRatcliffe\QueueWatch\Tests\Fakes;

final class FakeClock
{
    private float $nowMs = 0.0;

    public function now(): float
    {
        return $this->nowMs;
    }

    public function advance(float $ms): void
    {
        $this->nowMs += $ms;
    }
}

<?php

namespace Overtrue\LaravelOpenTelemetry\Support;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Common\Time\ClockInterface;

class CarbonClock implements ClockInterface
{
    protected ClockInterface $systemClock;

    public function __construct()
    {
        $this->systemClock = Clock::getDefault();
    }

    public function now(): int
    {
        if (Carbon::hasTestNow()) {
            return static::carbonToNanos(CarbonImmutable::now());
        }

        return $this->systemClock->now();
    }

    public static function carbonToNanos(CarbonInterface $carbon): int
    {
        return (int) $carbon->getPreciseTimestamp(6) * 1000;
    }
}

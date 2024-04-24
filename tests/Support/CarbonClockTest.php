<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Support;

use Carbon\Carbon;
use Overtrue\LaravelOpenTelemetry\Support\CarbonClock;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;
use Spatie\TestTime\TestTime;

class CarbonClockTest extends TestCase
{
    public function testGetNow()
    {
        $carbon = TestTime::freeze('Y-m-d H:i:s', '2024-01-01 00:00:00');
        $nano = Carbon::parse('2024-01-01 00:00:00')->getTimestampMs() * 1000000;

        $this->assertSame($nano, (new CarbonClock())->now());
    }

    public function testTransformCarbonInstanceToNanos()
    {
        $carbon = TestTime::freeze('Y-m-d H:i:s', '2024-01-01 00:00:00');
        $nano = Carbon::parse('2024-01-01 00:00:00')->getTimestampMs() * 1000000;

        $this->assertSame($nano, CarbonClock::carbonToNanos($carbon));
    }
}

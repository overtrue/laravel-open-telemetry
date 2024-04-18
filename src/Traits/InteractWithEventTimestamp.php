<?php

namespace Overtrue\LaravelOpenTelemetry\Traits;

use OpenTelemetry\SDK\Common\Time\ClockFactory;
use OpenTelemetry\SDK\Common\Time\ClockInterface;

trait InteractWithEventTimestamp
{
    protected function getEventStartTimestampNs(float $timeMs): int
    {
        $nowNs = ClockFactory::getDefault()->now();
        $durationNs = (int) ($timeMs * ClockInterface::NANOS_PER_MILLISECOND);

        return $nowNs - $durationNs;
    }
}

<?php

namespace Overtrue\LaravelOpenTelemetry\Support;

use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Facades\Metric;

class MeasureDataFlusher
{

    public static function flush(): void
    {
        Measure::flush();
        Metric::flush();
    }
}
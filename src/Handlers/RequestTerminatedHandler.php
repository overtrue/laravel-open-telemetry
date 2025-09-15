<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Handlers;

use Laravel\Octane\Events\RequestTerminated;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Facades\Metric;

class RequestTerminatedHandler
{
    /**
     * Handle the event.
     */
    public function handle(RequestTerminated $event): void
    {
        // In Octane mode, we need to force flush the tracer provider.
        Measure::flush();
        Metric::flush();
    }
}

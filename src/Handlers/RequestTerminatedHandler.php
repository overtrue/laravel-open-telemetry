<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Handlers;

use Laravel\Octane\Events\RequestTerminated;
use Overtrue\LaravelOpenTelemetry\Support\MeasureDataFlusher;

class RequestTerminatedHandler
{
    /**
     * Handle the event.
     */
    public function handle(RequestTerminated $event): void
    {
        // In Octane mode, we need to force flush the tracer provider.
        MeasureDataFlusher::flush();
    }
}

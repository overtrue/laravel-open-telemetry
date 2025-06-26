<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Handlers;

use Laravel\Octane\Events\RequestReceived;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

class RequestReceivedHandler
{
    /**
     * Handle the event.
     */
    public function handle(RequestReceived $event): void
    {
        // In Octane mode, we need to reset the state before each request.
        Measure::reset();
    }
}

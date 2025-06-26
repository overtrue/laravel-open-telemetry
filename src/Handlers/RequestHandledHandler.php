<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Handlers;

use Laravel\Octane\Events\RequestHandled;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Support\HttpAttributesHelper;

class RequestHandledHandler
{
    /**
     * Handle the event.
     */
    public function handle(RequestHandled $event): void
    {
        // This is now handled by the TraceRequest middleware.
    }
}

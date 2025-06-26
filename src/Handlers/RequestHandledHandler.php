<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Handlers;

use Laravel\Octane\Events\RequestHandled;

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

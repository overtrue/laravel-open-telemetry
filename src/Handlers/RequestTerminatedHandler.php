<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Handlers;

use Laravel\Octane\Events\RequestTerminated;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Support\HttpAttributesHelper;

class RequestTerminatedHandler
{
    /**
     * Handle the event.
     */
    public function handle(RequestTerminated $event): void
    {
        // Get root span and set response attributes
        $span = Measure::getRootSpan();

        // Set response attributes and status
        if ($span && $event->response) {
            HttpAttributesHelper::setResponseAttributes($span, $event->response);
            HttpAttributesHelper::setSpanStatusFromResponse($span, $event->response);
        }

        // Add trace ID to response headers
        if ($event->response && $span) {
            $event->response->headers->set('X-Trace-Id', $span->getContext()->getTraceId());
        }

        // Force flush and reset state (Octane mode)
        Measure::endRootSpan();
    }
}

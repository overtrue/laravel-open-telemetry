<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Handlers;

use Laravel\Octane\Events\RequestHandled;
use OpenTelemetry\API\Trace\StatusCode;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Support\HttpAttributesHelper;

class RequestHandledHandler
{
    /**
     * Handle the event.
     */
    public function handle(RequestHandled $event): void
    {
        // Get root span and set status (don't set response attributes, that's handled by RequestTerminatedHandler)
        $span = Measure::getRootSpan();

        if ($span && $event->response) {
            // Set span status based on status code
            HttpAttributesHelper::setSpanStatusFromResponse($span, $event->response);
        }
    }
}

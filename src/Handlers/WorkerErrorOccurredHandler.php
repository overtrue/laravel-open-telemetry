<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Handlers;

use Laravel\Octane\Events\WorkerErrorOccurred;
use OpenTelemetry\API\Trace\StatusCode;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

class WorkerErrorOccurredHandler
{
    /**
     * Handle the event.
     */
    public function handle(WorkerErrorOccurred $event): void
    {
        // Get root span and record exception
        $span = Measure::getRootSpan();

        if ($span && $event->exception) {
            $span->recordException($event->exception);
            $span->setStatus(StatusCode::STATUS_ERROR, $event->exception->getMessage());
        }

        // Note: Don't end() span here, that's handled uniformly by RequestTerminatedHandler
    }
}

<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Handlers;

use Laravel\Octane\Events\RequestReceived;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Support\HttpAttributesHelper;
use Overtrue\LaravelOpenTelemetry\Support\SpanNameHelper;

class RequestReceivedHandler
{
    /**
     * Handle the event.
     */
    public function handle(RequestReceived $event): void
    {
        // Only handle in Octane mode
        if (!Measure::isOctane()) {
            return;
        }

        Measure::reset();

        $request = $event->request;

        // Check if request path should be ignored
        if (HttpAttributesHelper::shouldIgnoreRequest($request)) {
            Measure::disable();
            return;
        }

        // Extract trace context from HTTP headers
        $parentContext = Measure::extractContextFromPropagationHeaders($request->headers->all());

        // Extract remote context
        $parentContext = $parentContext ?: \OpenTelemetry\Context\Context::getRoot();

        // Create root span
        $span = Measure::tracer()
            ->spanBuilder(SpanNameHelper::http($request))
            ->setParent($parentContext)  // Set parent context
            ->setSpanKind(\OpenTelemetry\API\Trace\SpanKind::KIND_SERVER)
            ->startSpan();

        // Store span in new context and activate this context
        $scope = $span->storeInContext($parentContext)->activate();

        // Set request attributes
        HttpAttributesHelper::setRequestAttributes($span, $request);

        // Set root span in Measure
        Measure::setRootSpan($span, $scope);

        // Store span in application container for later use (backward compatibility)
        app()->instance('otel.root_span', $span);
        app()->instance('otel.root_scope', $scope);
    }
}

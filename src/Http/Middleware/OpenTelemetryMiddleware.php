<?php

namespace Overtrue\LaravelOpenTelemetry\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenTelemetry\API\Trace\StatusCode;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Support\HttpAttributesHelper;
use Overtrue\LaravelOpenTelemetry\Support\SpanNameHelper;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class OpenTelemetryMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        Log::debug('[OpenTelemetry] Middleware started for: ' . $request->path());

        // Check if it's Octane mode, skip if so (Octane mode is handled by Handlers)
        if (Measure::isOctane()) {
            Log::debug('[OpenTelemetry] Skipping middleware - Octane mode detected');
            return $next($request);
        }

        Log::debug('[OpenTelemetry] Processing in FPM mode');

        // Check if request path should be ignored
        if (HttpAttributesHelper::shouldIgnoreRequest($request)) {
            Log::debug('[OpenTelemetry] Skipping OpenTelemetry middleware for ignored request path: ' . $request->path());
            Measure::disable();
            return $next($request);
        }

        Log::debug('[OpenTelemetry] Request path not ignored, proceeding with tracing');

        // Extract trace context from HTTP headers
        $parentContext = Measure::extractContextFromPropagationHeaders($request->headers->all());

        // Extract remote context
        $parentContext = $parentContext ?: \OpenTelemetry\Context\Context::getRoot();

        Log::debug('[OpenTelemetry] Parent context extracted');

        // Create root span
        $span = Measure::tracer()
            ->spanBuilder(SpanNameHelper::http($request))
            ->setParent($parentContext)  // Set parent context
            ->setSpanKind(\OpenTelemetry\API\Trace\SpanKind::KIND_SERVER)
            ->startSpan();

        Log::debug('[OpenTelemetry] Root span created: ' . $span->getContext()->getSpanId());

        // Store span in new context and activate this context
        $scope = $span->storeInContext($parentContext)->activate();

        Log::debug('[OpenTelemetry] Span context activated');

        try {
            // Set request attributes
            HttpAttributesHelper::setRequestAttributes($span, $request);

            // Set root span in Measure (for compatibility)
            Measure::setRootSpan($span, $scope);

            Log::debug('[OpenTelemetry] Root span set in Measure');

            // Process request
            $response = $next($request);

            Log::debug('[OpenTelemetry] Request processed, setting response attributes');

            // Set response attributes and status
            HttpAttributesHelper::setResponseAttributes($span, $response);
            HttpAttributesHelper::setSpanStatusFromResponse($span, $response);

            // Add trace ID to response headers
            HttpAttributesHelper::addTraceIdToResponse($span, $response);

            return $response;

        } catch (Throwable $exception) {
            Log::error('[OpenTelemetry] Exception caught: ' . $exception->getMessage());

            // Record exception
            $span->recordException($exception);
            $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());

            throw $exception;

        } finally {
            Log::debug('[OpenTelemetry] Cleaning up span and scope');

            // End span and detach scope
            $span->end();
            $scope->detach();

            // Clean up root span in Measure
            Measure::endRootSpan();

            Log::debug('[OpenTelemetry] Middleware completed');
        }
    }
}

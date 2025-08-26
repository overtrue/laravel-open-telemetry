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

class TraceRequest
{
    /**
     * @throws \Throwable
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if request path should be ignored
        if (HttpAttributesHelper::shouldIgnoreRequest($request)) {
            Measure::disable();
            return $next($request);
        }

        // Extract trace context from HTTP headers
        $parentContext = Measure::extractContextFromPropagationHeaders($request->headers->all());

        $span = Measure::startRootSpan(SpanNameHelper::http($request), [], $parentContext);

        try {
            // Set request attributes
            HttpAttributesHelper::setRequestAttributes($span, $request);

            // Process request
            $response = $next($request);

            // Set response attributes and status
            HttpAttributesHelper::setResponseAttributes($span, $response);
            HttpAttributesHelper::setSpanStatusFromResponse($span, $response);

            // Add trace ID to response headers
            HttpAttributesHelper::addTraceIdToResponse($span, $response);

            return $response;

        } catch (Throwable $exception) {
            // Log exception for debugging purposes
            Log::error('[laravel-open-telemetry] TraceRequest: Exception occurred during request processing', [
                'exception' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace_id' => $span->getContext()->getTraceId(),
            ]);

            // Record exception
            $span->recordException($exception);
            $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());

            throw $exception;
        } finally {
            // End span and detach scope
            Measure::endRootSpan();
        }
    }
}

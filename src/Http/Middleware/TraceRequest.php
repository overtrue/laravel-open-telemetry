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
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        Log::debug('OpenTelemetry TraceRequest: Processing request initiated', [
            'method' => $request->method(),
            'path' => $request->path(),
            'url' => $request->fullUrl(),
        ]);

        // Check if request path should be ignored
        if (HttpAttributesHelper::shouldIgnoreRequest($request)) {
            Log::debug('OpenTelemetry TraceRequest: Request path ignored - skipping tracing', [
                'path' => $request->path(),
            ]);
            Measure::disable();

            return $next($request);
        }

        // Extract trace context from HTTP headers
        $parentContext = Measure::extractContextFromPropagationHeaders($request->headers->all());

        $span = Measure::startRootSpan(SpanNameHelper::http($request), [], $parentContext);

        Log::debug('OpenTelemetry TraceRequest: Root span created successfully', [
            'span_id' => $span->getContext()->getSpanId(),
            'trace_id' => $span->getContext()->getTraceId(),
        ]);

        try {
            // Set request attributes
            HttpAttributesHelper::setRequestAttributes($span, $request);

            Log::debug('OpenTelemetry TraceRequest: Root span configured in Measure service');

            // Process request
            $response = $next($request);

            Log::debug('OpenTelemetry TraceRequest: Request processing completed - setting response attributes', [
                'status_code' => $response->getStatusCode(),
            ]);

            // Set response attributes and status
            HttpAttributesHelper::setResponseAttributes($span, $response);
            HttpAttributesHelper::setSpanStatusFromResponse($span, $response);

            // Add trace ID to response headers
            HttpAttributesHelper::addTraceIdToResponse($span, $response);

            return $response;

        } catch (Throwable $exception) {
            Log::error('OpenTelemetry TraceRequest: Exception occurred during request processing', [
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
            Log::debug('OpenTelemetry TraceRequest: Finalizing span and cleaning up resources');

            // End span and detach scope
            Measure::endRootSpan();

            Log::debug('OpenTelemetry TraceRequest: Processing completed successfully');
        }
    }
}

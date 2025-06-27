<?php

namespace Overtrue\LaravelOpenTelemetry\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use OpenTelemetry\API\Trace\Span;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Symfony\Component\HttpFoundation\Response;

class AddTraceId
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Get current trace ID
        $traceId = $this->getTraceId();

        if ($traceId) {
            // Get header name from config
            $headerName = config('otel.middleware.trace_id.header_name', 'X-Trace-Id');

            // Add trace ID response header
            $response->headers->set($headerName, $traceId);
        }

        return $response;
    }

    /**
     * Get trace ID for current request
     */
    protected function getTraceId(): ?string
    {
        // First try to get from root span
        $rootSpan = Measure::getRootSpan();
        if ($rootSpan && $rootSpan->getContext()->isValid()) {
            return $rootSpan->getContext()->getTraceId();
        }

        // If no root span, try to get from current active span
        $currentSpan = Span::getCurrent();
        if ($currentSpan->getContext()->isValid()) {
            return $currentSpan->getContext()->getTraceId();
        }

        return null;
    }
}

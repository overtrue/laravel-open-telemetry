<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Hooks\Illuminate\Http;

use Illuminate\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use OpenTelemetry\Contrib\Instrumentation\Laravel\Hooks\LaravelHook;
use OpenTelemetry\Contrib\Instrumentation\Laravel\Hooks\LaravelHookTrait;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Symfony\Component\HttpFoundation\Response;
use function OpenTelemetry\Instrumentation\hook;

class Kernel implements LaravelHook
{
    use LaravelHookTrait;

        public function instrument(): void
    {
        // Hook into the handle method to add trace ID to response
        hook(
            class: HttpKernel::class,
            function: 'handle',
            post: function (HttpKernel $kernel, array $params, Response $response) {
                $this->addTraceIdToResponse($response);
                return $response;
            }
        );
    }

    /**
     * Add trace ID to response headers
     */
    private function addTraceIdToResponse(Response $response): void
    {
        $headerName = config('otel.response_trace_header_name');

        // Skip if header name is not configured or empty
        if (empty($headerName)) {
            return;
        }

        try {
            // Get current trace ID
            $traceId = Measure::traceId();

            // Add trace ID to response header if it's valid (not empty and not all zeros)
            if (!empty($traceId) && $traceId !== '00000000000000000000000000000000') {
                $response->headers->set($headerName, $traceId);
            }
        } catch (\Throwable $e) {
            // Silently ignore errors when getting trace ID
            // This prevents failures when there's no trace context
        }
    }
}

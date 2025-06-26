<?php

/**
 * Laravel OpenTelemetry Configuration Guide
 *
 * This file demonstrates how to use the new HTTP client configuration options
 */

// 1. Check if OpenTelemetry is enabled
if (config('otel.enabled', true)) {
    echo "OpenTelemetry is enabled\n";
} else {
    echo "OpenTelemetry is disabled\n";
}

// 2. Check tracer name
$tracerName = config('otel.tracer_name', 'overtrue.laravel-open-telemetry');
echo "Tracer name: {$tracerName}\n";

// 3. Check middleware configuration
$traceIdEnabled = config('otel.middleware.trace_id.enabled', true);
$traceIdGlobal = config('otel.middleware.trace_id.global', true);
$traceIdHeaderName = config('otel.middleware.trace_id.header_name', 'X-Trace-Id');

echo "Trace ID Middleware enabled: " . ($traceIdEnabled ? 'Yes' : 'No') . "\n";
echo "Trace ID Middleware global: " . ($traceIdGlobal ? 'Yes' : 'No') . "\n";
echo "Trace ID Header name: {$traceIdHeaderName}\n";

// 4. Check new HTTP client configuration
$httpClientPropagationEnabled = config('otel.http_client.propagation_middleware.enabled', true);
echo "HTTP Client propagation middleware enabled: " . ($httpClientPropagationEnabled ? 'Yes' : 'No') . "\n";

// 5. View all available watchers
$watchers = config('otel.watchers', []);
echo "Available watchers:\n";
foreach ($watchers as $watcher) {
    echo "  - {$watcher}\n";
}

// 6. Environment variable configuration examples
echo "\n=== Environment Variable Examples ===\n";
echo "OTEL_ENABLED=true\n";
echo "OTEL_TRACER_NAME=my-app\n";
echo "OTEL_TRACE_ID_MIDDLEWARE_ENABLED=true\n";
echo "OTEL_TRACE_ID_MIDDLEWARE_GLOBAL=true\n";
echo "OTEL_TRACE_ID_HEADER_NAME=X-Custom-Trace-Id\n";
echo "OTEL_HTTP_CLIENT_PROPAGATION_ENABLED=true\n";

// 7. Demonstrate how to use in code
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Illuminate\Support\Facades\Http;

// Fully automatic HTTP request tracing
// No manual code needed - all requests through Http facade are automatically traced with context propagation
$response = Http::get('https://httpbin.org/ip');

// View tracing status
$status = Measure::getStatus();
echo "\n=== Tracing Status ===\n";
echo "Recording: " . ($status['is_recording'] ? 'Yes' : 'No') . "\n";
echo "Current trace ID: " . ($status['current_trace_id'] ?? 'None') . "\n";
echo "Active spans count: " . $status['active_spans_count'] . "\n";
echo "Tracer provider: " . $status['tracer_provider']['class'] . "\n";

// 8. How to disable HTTP client propagation middleware
echo "\n=== How to Disable HTTP Client Propagation ===\n";
echo "In your .env file, set:\n";
echo "OTEL_HTTP_CLIENT_PROPAGATION_ENABLED=false\n";
echo "\nOr in config/otel.php:\n";
echo "'http_client' => [\n";
echo "    'propagation_middleware' => [\n";
echo "        'enabled' => false,\n";
echo "    ],\n";
echo "],\n";

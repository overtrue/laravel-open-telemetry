<?php

/**
 * Simplified Automatic Tracing Example
 *
 * Uses HttpClientWatcher to automatically trace all HTTP requests
 * without needing to manually configure tracing middleware
 */

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

// ✅ Recommended: Use automatic tracing
Route::get('/hello', function () {
    Http::get('https://httpbin.org/ip');           // Automatically traced
    event('hello.created', ['name' => 'test']);    // EventWatcher automatically traces
    Cache::get('foo', 'bar');                      // CacheWatcher automatically traces
    return 'Hello, World!';
});

Route::get('/foo', function () {
    return Measure::trace('hello2', function () {
        sleep(rand(1, 3));
        Http::get('https://httpbin.org/ip');       // Automatically traced
        event('hello.created2', ['name' => 'test']); // EventWatcher automatically traces
        Cache::get('foo', 'bar');                  // CacheWatcher automatically traces
        return 'Hello, Foo!';
    });
});

Route::get('/trace-test', function () {
    $tracer = Measure::tracer();

    $span = $tracer->spanBuilder('test span')
        ->setAttribute('test.attribute', 'value')
        ->startSpan();

    sleep(rand(1, 3));

    // ✅ Automatic tracing: HttpClientWatcher handles all requests automatically
    Http::get('http://127.0.0.1:8002/foo');       // HttpClientWatcher handles automatically
    event('hello.created', ['name' => 'test']);    // EventWatcher handles automatically
    Cache::get('foo', 'bar');                      // CacheWatcher handles automatically

    $span->end();

    $span1 = $tracer->spanBuilder('test span 1')
        ->setAttribute('test.attribute.1', 'value.1')
        ->startSpan();
    $span1->end();

    return [
        'span_id' => $span->getContext()->getSpanId(),
        'trace_id' => $span->getContext()->getTraceId(),
        'trace_flags' => $span->getContext()->getTraceFlags(),
        'trace_state' => $span->getContext()->getTraceState(),
        'span_name' => $span->getName(),
        'env' => array_filter($_ENV, function ($key) {
            return str_starts_with($key, 'OTEL_') || str_starts_with($key, 'OTEL_EXPORTER_');
        }, ARRAY_FILTER_USE_KEY),
        'url' => sprintf('http://localhost:16686/jaeger/ui/trace/%s', $span->getContext()->getTraceId()),
    ];
});

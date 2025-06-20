<?php

return [

    /**
     * The name of the header that will be used to pass the trace id in the response.
     * if set to `null`, the header will not be added to the response.
     */
    'response_trace_header_name' => env('OTEL_RESPONSE_TRACE_HEADER_NAME', 'X-Trace-Id'),

    /**
     * Watchers Configuration
     * Note: Starting from v2.0, we use OpenTelemetry's official auto-instrumentation
     * Most tracing functionality is provided by the opentelemetry-auto-laravel package
     * This package provides the following additional Watcher functionality:
     *
     * Available Watcher classes:
     * - \Overtrue\LaravelOpenTelemetry\Watchers\ExceptionWatcher::class
     * - \Overtrue\LaravelOpenTelemetry\Watchers\AuthenticateWatcher::class
     * - \Overtrue\LaravelOpenTelemetry\Watchers\EventWatcher::class
     * - \Overtrue\LaravelOpenTelemetry\Watchers\QueueWatcher::class
     * - \Overtrue\LaravelOpenTelemetry\Watchers\RedisWatcher::class
     */
    'watchers' => [
        \Overtrue\LaravelOpenTelemetry\Watchers\ExceptionWatcher::class,
        \Overtrue\LaravelOpenTelemetry\Watchers\AuthenticateWatcher::class,
        \Overtrue\LaravelOpenTelemetry\Watchers\EventWatcher::class,
        \Overtrue\LaravelOpenTelemetry\Watchers\QueueWatcher::class,
        \Overtrue\LaravelOpenTelemetry\Watchers\RedisWatcher::class,
    ],

    /**
     * Allow to trace requests with specific headers. You can use `*` as wildcard.
     */
    'allowed_headers' => explode(',', env('OTEL_ALLOWED_HEADERS', implode(',', [
        'referer',
        'x-*',
        'accept',
        'request-id',
    ]))),

    /**
     * Sensitive headers will be marked as *** from the span attributes. You can use `*` as wildcard.
     */
    'sensitive_headers' => explode(',', env('OTEL_SENSITIVE_HEADERS', implode(',', [
        'cookie',
        'authorization',
        'x-api-key',
    ]))),

    /**
     * Ignore paths will not be traced. You can use `*` as wildcard.
     */
    'ignore_paths' => explode(',', env('OTEL_IGNORE_PATHS', implode(',', [
        'horizon*',
        'telescope*',
        '_debugbar*',
        'health*',
    ]))),
];

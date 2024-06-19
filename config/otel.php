<?php

use Overtrue\LaravelOpenTelemetry\Watchers;

return [
    /**
     * Enable or disable the OpenTelemetry Laravel Extension.
     */
    'enabled' => env('OTEL_ENABLED', true),

    /**
     * Auto Register the MeasureRequest middleware.
     */
    'automatically_trace_requests' => env('OTEL_AUTO_TRACE_REQUESTS', true),

    /**
     * Allow to trace requests with specific headers. You can use `*` as wildcard.
     */
    'allowed_headers' => [
        'referer',
        'x-*',
        'accept',
        'request-id',
    ],

    /**
     * Sensitive headers will be marked as *** from the span attributes. You can use `*` as wildcard.
     */
    'sensitive_headers' => [
        // 'cookie',
        // 'authorization',
        // ...
    ],

    /**
     * The name of the header that will be used to pass the trace id in the response.
     * if set to `null`, the header will not be added to the response.
     */
    'response_trace_header_name' => env('OTEL_RESPONSE_TRACE_HEADER_NAME', 'X-Trace-Id'),

    /**
     * Watchers to be registered.
     */
    'watchers' => [
        Watchers\ExceptionWatcher::class,
        Watchers\AuthenticateWatcher::class,
        Watchers\EventWatcher::class,
        Watchers\QueueWatcher::class,
        Watchers\RedisWatcher::class,
        // App\Trace\Watchers\YourCustomWatcher::class, // Add your custom watcher here.
    ],
];

<?php

return [

    /**
     * Enable or disable OpenTelemetry tracing
     * When disabled, no watchers will be registered and no tracing will occur
     */
    'enabled' => env('OTEL_ENABLED', false),

    /**
     * The name of the tracer that will be used to create spans.
     * This is useful for identifying the source of the spans.
     */
    'tracer_name' => env('OTEL_TRACER_NAME', 'overtrue.laravel-open-telemetry'),

    /**
     * OpenTelemetry SDK Configuration
     *
     * This package uses standard OpenTelemetry environment variables for configuration.
     * These are automatically picked up by the OpenTelemetry PHP SDK:
     *
     * Core Variables:
     * - OTEL_SERVICE_NAME: Name of your service (e.g., 'my-laravel-app')
     * - OTEL_SERVICE_VERSION: Version of your service (e.g., '1.0.0')
     * - OTEL_TRACES_EXPORTER: Trace exporter type ('console', 'otlp', etc.)
     * - OTEL_EXPORTER_OTLP_ENDPOINT: OTLP endpoint URL (e.g., 'http://localhost:4318')
     * - OTEL_EXPORTER_OTLP_PROTOCOL: OTLP protocol ('http/protobuf' or 'grpc')
     * - OTEL_EXPORTER_OTLP_HEADERS: Custom headers for authentication (e.g., 'x-api-key=key123')
     *   Format: comma-separated key=value pairs
     *   Example: "x-api-key=abc123,authorization=Bearer token456"
     * - OTEL_PROPAGATORS: Context propagators (e.g., 'tracecontext,baggage')
     * - OTEL_TRACES_SAMPLER: Sampling strategy ('always_on', 'traceidratio', etc.)
     * - OTEL_TRACES_SAMPLER_ARG: Sampler argument (e.g., '0.1' for 10% sampling)
     * - OTEL_RESOURCE_ATTRIBUTES: Additional resource attributes (e.g., 'key1=value1,key2=value2')
     *
     * For more information, see: https://opentelemetry.io/docs/specs/otel/configuration/sdk-environment-variables/
     */

    /**
     * Middleware Configuration
     */
    'middleware' => [
        /**
         * Trace ID Middleware Configuration
         * Used to add X-Trace-Id to response headers
         */
        'trace_id' => [
            'enabled' => env('OTEL_TRACE_ID_MIDDLEWARE_ENABLED', true),
            'global' => env('OTEL_TRACE_ID_MIDDLEWARE_GLOBAL', true),
            'header_name' => env('OTEL_TRACE_ID_HEADER_NAME', 'X-Trace-Id'),
        ],
    ],

    /**
     * HTTP Client Configuration
     */
    'http_client' => [
        /**
         * Global Request Middleware Configuration
         * Automatically adds OpenTelemetry propagation headers to all HTTP requests
         */
        'propagation_middleware' => [
            'enabled' => env('OTEL_HTTP_CLIENT_PROPAGATION_ENABLED', true),
        ],
    ],

    /**
     * Watchers Configuration
     *
     * Available Watcher classes:
     * - \Overtrue\LaravelOpenTelemetry\Watchers\CacheWatcher::class
     * - \Overtrue\LaravelOpenTelemetry\Watchers\QueryWatcher::class
     * - \Overtrue\LaravelOpenTelemetry\Watchers\HttpClientWatcher::class
     * - \Overtrue\LaravelOpenTelemetry\Watchers\ExceptionWatcher::class
     * - \Overtrue\LaravelOpenTelemetry\Watchers\AuthenticateWatcher::class
     * - \Overtrue\LaravelOpenTelemetry\Watchers\EventWatcher::class
     * - \Overtrue\LaravelOpenTelemetry\Watchers\QueueWatcher::class
     * - \Overtrue\LaravelOpenTelemetry\Watchers\RedisWatcher::class
     */
    'watchers' => [
        \Overtrue\LaravelOpenTelemetry\Watchers\CacheWatcher::class,
        \Overtrue\LaravelOpenTelemetry\Watchers\QueryWatcher::class,
        \Overtrue\LaravelOpenTelemetry\Watchers\HttpClientWatcher::class, // 已添加智能重复检测，可以同时使用
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
        'up',
        'horizon*',         // Laravel Horizon dashboard
        'telescope*',       // Laravel Telescope dashboard
        '_debugbar*',       // Laravel Debugbar
        'health*',          // Health check endpoints
        'ping',             // Simple ping endpoint
        'status',           // Status endpoint
        'metrics',          // Metrics endpoint
        'favicon.ico',      // Browser favicon requests
        'robots.txt',       // SEO robots file
        'sitemap.xml',      // SEO sitemap
        'api/health',       // API health check
        'api/ping',         // API ping
        'admin/health',     // Admin health check
        'internal/*',       // Internal endpoints
        'monitoring/*',     // Monitoring endpoints
        '_profiler/*',      // Symfony profiler (if used)
        '.well-known/*',    // Well-known URIs (RFC 8615)
    ]))),
];

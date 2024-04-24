<?php

use OpenTelemetry\SemConv\ResourceAttributes;
use Overtrue\LaravelOpenTelemetry\Watchers;

return [
    /**
     * Enable or disable the OpenTelemetry Laravel Extension.
     */
    'enabled' => env('OTLE_ENABLED', true),

    /**
     * Default tracer name, you can switch it in runtime.
     * all tracers should be defined in `tracers` section.
     */
    'default' => env('OTLE_DEFAULT_TRACER', 'log'),

    /**
     * Auto Register the MeasureRequest middleware.
     */
    'automatically_trace_requests' => env('OTLE_AUTO_TRACE_REQUESTS', true),

    /**
     * Will be applied to all channels. you can override it in the channel config.
     */
    'global' => [
        /**
         * Service name.
         */
        'service_name' => env('OTLE_SERVICE_NAME', env('APP_NAME', 'laravel')),

        /**
         * Tracer name.
         */
        'name' => env('OTLE_TRACER_NAME', 'app'),

        /**
         * Sampler is used to determine if a span should be recorded.
         */
        'sampler' => \OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler::class,

        'resource' => [
            'attributes' => [
                // ResourceAttributes::SERVICE_VERSION => env('APP_VERSION', '1.0.0'),
                // 'token' => env('OTLE_RESOURCE_TOKEN', 'token'),
            ],
        ],

        /**
         * Watchers to be registered.
         */
        'watchers' => [
            Watchers\CacheWatcher::class,
            Watchers\ExceptionWatcher::class,
            Watchers\LogWatcher::class,
            Watchers\DatabaseQueryWatcher::class,
            Watchers\AuthenticateWatcher::class,
            Watchers\HttpClientRequestWatcher::class,
            // App\Trace\Watchers\YourCustomWatcher::class, // Add your custom watcher here.
        ],

        /**
         * Transport, you can use pre-defined transports: `stream`, `http`, `grpc`.
         * or your custom transport class by implementing `OpenTelemetry\SDK\Trace\TransportInterface` interface:
         * for example: [`App\Trace\Transports\YourCustomTransport::class`, 'arg1', 'arg2']
         */
        'transport' => env('OTLE_DEFAULT_TRANSPORT', 'http'),

        /**
         * Span exporter, you can use pre-defined exporters: `memory`, `console`, `otlp`.
         * or your custom span exporter by implementing `OpenTelemetry\SDK\Trace\SpanExporterInterface` interface.
         * for example: [`App\Trace\Exporters\YourCustomExporter::class`, 'arg1', 'arg2']
         */
        'span_exporter' => env('OTLE_DEFAULT_SPAN_EXPORTER', 'otlp'),

        /**
         * Span processor, you can use your custom span processor by implementing `OpenTelemetry\SDK\Trace\SpanProcessorInterface` interface.
         * for example: [`App\Trace\SpanProcessors\YourCustomSpanProcessor::class`, 'arg1', 'arg2']
         */
        'span_processor' => \OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor::class,

        /**
         * Id generator, you can use your custom id generator by implementing `OpenTelemetry\SDK\Trace\IdGeneratorInterface` interface.
         * for example: [`App\Trace\IdGenerators\YourCustomIdGenerator::class`, 'arg1', 'arg2']
         */
        'id_generator' => \OpenTelemetry\SDK\Trace\RandomIdGenerator::class,

        /**
         * Log exporter, you can use pre-defined exporters: `memory`, `console`, `otlp`.
         */
        'log_exporter' => env('OTLE_DEFAULT_LOG_EXPORTER', 'otlp'),
    ],

    /**
     * Tracers configurations. you can add more tracers here.
     * and all the configurations should overwrite the global configurations.
     * available drivers: `console`, `log`, `http-json`, `http-binary`, `grpc`.
     */
    'tracers' => [
        'console' => [
            'driver' => 'console',
            'transport' => 'stream',
            'span_exporter' => 'console',
        ],

        'log' => [
            'driver' => 'log',
            'transport' => 'stream',
            'span_exporter' => 'console',
            'endpoint' => storage_path('logs/otel.log'),
        ],

        'http-json' => [
            'driver' => 'http-json',
            'transport' => 'http',
            'span_exporter' => 'otlp',
            'endpoint' => env('OTLE_HTTP_JSON_ENDPOINT', 'http://localhost:4318/v1/traces'),
            'content_type' => 'application/json',
        ],

        'http-binary' => [
            'driver' => 'http-binary',
            'transport' => 'http',
            'span_exporter' => 'otlp',
            'endpoint' => env('OTLE_HTTP_BINARY_ENDPOINT', 'http://localhost:4318/v1/traces'),
            'content_type' => 'application/x-protobuf',
        ],

        // You should install php extension `ext-grpc` to use this driver.
        //        'grpc' => [
        //            'driver' => 'grpc',
        //            'transport' => 'grpc',
        //            'span_exporter' => 'otlp',
        //            'endpoint' => env('OTLE_GRPC_ENDPOINT', 'http://localhost:4317/v1/traces'),
        //            'content_type' => 'application/x-protobuf',
        //        ],
    ],
];

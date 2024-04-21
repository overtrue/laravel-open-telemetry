<?php

use OpenTelemetry\SemConv\ResourceAttributes;
use Overtrue\LaravelOpenTelemetry\Watchers;

return [
    'default' => env('OTLE_DEFAULT_TRACER', 'console'),

    'root_tracer_name' => env('OTLE_ROOT_TRACER_NAME', 'app'),

    /**
     * Will be applied to all channels. you can override it in the channel config.
     */
    'global' => [
        /**
         * Sampler is used to determine if a span should be recorded.
         */
        'sampler' => \OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler::class,

        /**
         * Watchers to be registered.
         */
        'watchers' => [
//            Watchers\CacheWatcher::class,
//            Watchers\ExceptionWatcher::class,
//            Watchers\LogWatcher::class,
//            Watchers\QueryWatcher::class,
//            Watchers\AuthenticateWatcher::class,
//            Watchers\HttpClientRequestWatcher::class,

            // App\Trace\Watchers\YourCustomWatcher::class, // Add your custom watcher here.
        ],
        'resource' => [
            'attributes' => [
                ResourceAttributes::SERVICE_NAME => env('APP_NAME', 'laravel'),
                ResourceAttributes::SERVICE_VERSION => env('APP_VERSION', '1.0.0'),
                // 'token' => env('OTLE_RESOURCE_TOKEN', 'token'),
            ],
        ],
        // stream/http/grpc
        'transport' => 'stream',

        // memory/console/otlp
        'span_exporter' => 'otlp',

        /**
         * Span processor, you can use your custom span processor by implementing `OpenTelemetry\SDK\Trace\SpanProcessorInterface` interface.
         */
        'span_processor' => \OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor::class,

        /**
         * Id generator, you can use your custom id generator by implementing `OpenTelemetry\SDK\Trace\IdGeneratorInterface` interface.
         */
        'id_generator' => \OpenTelemetry\SDK\Trace\RandomIdGenerator::class,

        'logs' => [
            // memory/console/otlp
            'exporter' => 'otlp',
        ],
    ],

    'tracers' => [
        'console' => [
            'transport' => 'stream',
            'span_exporter' => 'console',
        ],

        'log' => [
            'transport' => 'stream',
            'span_exporter' => 'console',
            'endpoint' => storage_path('logs/otel.log'),
        ],

        'http-json' => [
            'transport' => 'http',
            'span_exporter' => 'otlp',
            'endpoint' => 'http://localhost:4318/v1/traces',
            'content_type' => 'application/json',
        ],

        'http-binary' => [
            'transport' => 'http',
            'span_exporter' => 'otlp',
            'endpoint' => 'http://localhost:4317/v1/traces',
            'content_type' => 'application/x-protobuf',
        ],

        'grpc' => [
            'transport' => 'http',
            'span_exporter' => 'otlp',
            'endpoint' => 'http://localhost:4317/v1/traces',
            'content_type' => 'application/x-protobuf',
        ],
    ],
];

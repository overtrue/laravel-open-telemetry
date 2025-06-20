<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Watchers;

use Illuminate\Contracts\Foundation\Application;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Contrib\Instrumentation\Laravel\Watchers\Watcher;

/**
 * Event Watcher
 *
 * Listen to all event dispatches, record event name, class, attribute count, listener count
 */
class EventWatcher extends Watcher
{
    public function __construct(
        private readonly CachedInstrumentation $instrumentation,
    ) {
    }

    public function register(Application $app): void
    {
        // Use wildcard to listen to all events
        $app['events']->listen('*', [$this, 'recordEvent']);
    }

    public function recordEvent(string $eventName, array $payload): void
    {
        // Skip OpenTelemetry-related events to avoid infinite loops
        if (str_starts_with($eventName, 'opentelemetry') || str_starts_with($eventName, 'otel')) {
            return;
        }

        // Skip some frequent internal events
        $skipEvents = [
            'Illuminate\Log\Events\MessageLogged',
            'Illuminate\Database\Events\QueryExecuted',
            'Illuminate\Cache\Events\CacheHit',
            'Illuminate\Cache\Events\CacheMissed',
            'Illuminate\Cache\Events\KeyWritten',
            'Illuminate\Cache\Events\KeyForgotten',
        ];

        if (in_array($eventName, $skipEvents)) {
            return;
        }

        $span = $this->instrumentation
            ->tracer()
            ->spanBuilder(sprintf('event %s', $eventName))
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $attributes = [
            'event.name' => $eventName,
            'event.payload_count' => count($payload),
        ];

        // If payload contains objects, record object type
        if (! empty($payload)) {
            $firstPayload = $payload[0] ?? null;
            if (is_object($firstPayload)) {
                $attributes['event.object_type'] = get_class($firstPayload);

                // If it's a model event, record model information
                if (method_exists($firstPayload, 'getTable')) {
                    $attributes['event.model_table'] = $firstPayload->getTable();
                }

                if (method_exists($firstPayload, 'getKey')) {
                    $attributes['event.model_key'] = $firstPayload->getKey();
                }
            }
        }

        // Try to get listener count
        try {
            $dispatcher = app('events');
            if (method_exists($dispatcher, 'getListeners')) {
                $listeners = $dispatcher->getListeners($eventName);
                $attributes['event.listeners_count'] = count($listeners);
            }
        } catch (\Exception $e) {
            // Ignore listener retrieval failures
        }

        $span->setAttributes($attributes);
        $span->end();
    }
}

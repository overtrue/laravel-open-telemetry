<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Watchers;

use Illuminate\Contracts\Foundation\Application;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

/**
 * Event Watcher
 *
 * Listen to all event dispatches, record event name, class, attribute count, listener count
 */
class EventWatcher extends Watcher
{
    /**
     * @var string[]
     */
    protected array $eventsToSkip = [
        'Illuminate\\Log\\Events\\MessageLogged',
        'Illuminate\\Database\\Events\\QueryExecuted',
        'Illuminate\\Cache\\Events\\CacheHit',
        'Illuminate\\Cache\\Events\\CacheMissed',
        'Illuminate\\Cache\\Events\\KeyWritten',
        'Illuminate\\Cache\\Events\\KeyForgotten',
        'Illuminate\\Queue\\Events\\JobProcessing',
        'Illuminate\\Queue\\Events\\JobProcessed',
        'Illuminate\\Queue\\Events\\JobFailed',
        'Illuminate\\Auth\\Events\\Attempting',
        'Illuminate\\Auth\\Events\\Authenticated',
        'Illuminate\\Auth\\Events\\Login',
        'Illuminate\\Auth\\Events\\Failed',
        'Illuminate\\Auth\\Events\\Logout',
        'Illuminate\\Redis\\Events\\CommandExecuted',
        'Illuminate\\Http\\Client\\Events\\RequestSending',
        'Illuminate\\Http\\Client\\Events\\ResponseReceived',
        'Illuminate\\Http\\Client\\Events\\ConnectionFailed',
    ];

    public array $events = [
        // ...
    ];

    public function register(Application $app): void
    {
        $app['events']->listen('*', [$this, 'recordEvent']);
    }

    public function recordEvent($eventName, $payload = []): void
    {
        if ($this->shouldSkip($eventName)) {
            return;
        }

        $attributes = [
            'event.payload_count' => is_array($payload) ? count($payload) : 0,
        ];

        $firstPayload = is_array($payload) ? ($payload[0] ?? null) : null;
        if (is_object($firstPayload)) {
            $attributes['event.object_type'] = get_class($firstPayload);
        }

        Measure::addEvent($eventName, $attributes);
    }

    protected function shouldSkip(string $eventName): bool
    {
        if (str_starts_with($eventName, 'otel.') || str_starts_with($eventName, 'opentelemetry.')) {
            return true;
        }

        return in_array($eventName, $this->eventsToSkip);
    }
}

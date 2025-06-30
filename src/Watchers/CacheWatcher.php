<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Watchers;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Contracts\Foundation\Application;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

class CacheWatcher extends Watcher
{
    public function register(Application $app): void
    {
        $app['events']->listen(CacheHit::class, fn ($event) => $this->recordEvent('cache.hit', $event));
        $app['events']->listen(CacheMissed::class, fn ($event) => $this->recordEvent('cache.miss', $event));
        $app['events']->listen(KeyWritten::class, fn ($event) => $this->recordEvent('cache.set', $event));
        $app['events']->listen(KeyForgotten::class, fn ($event) => $this->recordEvent('cache.forget', $event));
    }

    protected function recordEvent(string $eventName, object $event): void
    {
        $attributes = [
            'cache.key' => $event->key,
            'cache.store' => $this->getStoreName($event),
        ];

        if ($event instanceof KeyWritten) {
            $attributes['cache.ttl'] = property_exists($event, 'seconds') ? $event->seconds : null;
        }

        Measure::addEvent($eventName, $attributes);
    }

    private function getStoreName(object $event): ?string
    {
        return property_exists($event, 'storeName') ? $event->storeName : null;
    }
}

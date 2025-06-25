<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Watchers;

use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Contracts\Foundation\Application;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SemConv\TraceAttributes;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Support\SpanNameHelper;

class CacheWatcher extends Watcher
{
    public function register(Application $app): void
    {
        $app['events']->listen(CacheHit::class, fn ($event) => $this->recordSpan('hit', $event));
        $app['events']->listen(CacheMissed::class, fn ($event) => $this->recordSpan('miss', $event));
        $app['events']->listen(KeyWritten::class, fn ($event) => $this->recordSpan('set', $event));
        $app['events']->listen(KeyForgotten::class, fn ($event) => $this->recordSpan('forget', $event));
    }

    protected function recordSpan(string $operation, object $event): void
    {
        $span = Measure::tracer()
            ->spanBuilder(SpanNameHelper::cache($operation, $event->key))
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $attributes = [
            'cache.key' => $event->key,
            'cache.operation' => $operation,
            'cache.store' => $this->getStoreName($event),
        ];

        if ($event instanceof KeyWritten) {
            $attributes['cache.ttl'] = property_exists($event, 'seconds') ? $event->seconds : null;
        }

        $span->setAttributes($attributes);
        $span->end();
    }

    private function getStoreName(object $event): ?string
    {
        return property_exists($event, 'storeName') ? $event->storeName : null;
    }
}

<?php

namespace Overtrue\LaravelOpenTelemetry\Watchers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

class EventWatcher implements Watcher
{
    protected static array $ignoredEvents = [];

    public function register(Application $app): void
    {
        $app['events']->listen('*', $this->recordEvent(...));
    }

    public function recordEvent($event): void
    {
        if ($this->isInternalLaravelEvent($event) || $this->isIgnoredEvent($event)) {
            return;
        }

        Measure::activeSpan()->addEvent(sprintf('[EVENT] %s fired', $event), [
            'event.name' => $event,
        ]);
    }

    protected function isInternalLaravelEvent(string $event): bool
    {
        return Str::is([
            'Illuminate\*',
            'Laravel\Octane\*',
            'Laravel\Scout\*',
            'eloquent*',
            'bootstrapped*',
            'bootstrapping*',
            'creating*',
            'composing*',
        ], $event);
    }

    protected function isIgnoredEvent(string $event): bool
    {
        return in_array($event, static::$ignoredEvents);
    }
}

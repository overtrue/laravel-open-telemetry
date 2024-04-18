<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Watchers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\Events\MessageLogged;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

class LogWatcher implements Watcher
{
    public function register(Application $app): void
    {
        $app['events']->listen(MessageLogged::class, $this->recordLog(...));
    }

    public function recordLog(MessageLogged $log): void
    {
        $attributes = [
            'level' => $log->level,
        ];

        $attributes['context'] = json_encode(array_filter($log->context));

        $message = $log->message;

        Measure::activeSpan()->addEvent($message, $attributes);
    }
}

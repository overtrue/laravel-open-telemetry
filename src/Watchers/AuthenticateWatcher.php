<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Watchers;

use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\SemConv\TraceAttributes;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

class AuthenticateWatcher implements Watcher
{
    public function register(Application $app): void
    {
        Event::listen(Login::class, function (Login $event) {
            $span = Measure::getCurrentSpan();

            if ($span instanceof Span) {
                $span->setAttribute(TraceAttributes::DB_USER, $event->user->getKey());
            }
        });
    }
}

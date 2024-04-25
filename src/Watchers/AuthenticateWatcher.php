<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Watchers;

use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Foundation\Application;
use OpenTelemetry\API\Trace\Span;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

class AuthenticateWatcher implements Watcher
{
    public function register(Application $app): void
    {
        $app['events']->listen(Login::class, function (Login $event) {
            $span = Measure::activeSpan();

            if ($span instanceof Span) {
                $span->setAttribute('user.id', $event->user->getKey());
            }
        });
    }
}

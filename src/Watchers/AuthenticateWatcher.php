<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Watchers;

use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Foundation\Application;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Support\SpanNameHelper;

/**
 * Authenticate Watcher
 *
 * Listen to authentication events, record authentication results, user ID, guard information
 */
class AuthenticateWatcher extends Watcher
{
    public function register(Application $app): void
    {
        $app['events']->listen(Attempting::class, [$this, 'recordAttempting']);
        $app['events']->listen(Authenticated::class, [$this, 'recordAuthenticated']);
        $app['events']->listen(Login::class, [$this, 'recordLogin']);
        $app['events']->listen(Failed::class, [$this, 'recordFailed']);
        $app['events']->listen(Logout::class, [$this, 'recordLogout']);
    }

    public function recordAttempting(Attempting $event): void
    {
        $span = Measure::tracer()
            ->spanBuilder(SpanNameHelper::auth('attempting'))
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setParent(Context::getCurrent())
            ->startSpan();

        $span->setAttributes([
            'auth.guard' => $event->guard,
            'auth.credentials.count' => count($event->credentials),
            'auth.remember' => $event->remember,
        ]);

        $span->end();
    }

    public function recordAuthenticated(Authenticated $event): void
    {
        $span = Measure::tracer()
            ->spanBuilder(SpanNameHelper::auth('authenticated'))
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setParent(Context::getCurrent())
            ->startSpan();

        $span->setAttributes([
            'auth.guard' => $event->guard,
            TraceAttributes::ENDUSER_ID => $event->user->getAuthIdentifier(),
            'auth.user.type' => get_class($event->user),
        ]);

        $span->end();
    }

    public function recordLogin(Login $event): void
    {
        $span = Measure::tracer()
            ->spanBuilder(SpanNameHelper::auth('login'))
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setParent(Context::getCurrent())
            ->startSpan();

        $span->setAttributes([
            'auth.guard' => $event->guard,
            TraceAttributes::ENDUSER_ID => $event->user->getAuthIdentifier(),
            'auth.user.type' => get_class($event->user),
            'auth.remember' => $event->remember,
        ]);

        $span->end();
    }

    public function recordFailed(Failed $event): void
    {
        $span = Measure::tracer()
            ->spanBuilder(SpanNameHelper::auth('failed'))
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setParent(Context::getCurrent())
            ->startSpan();

        $span->setAttributes([
            'auth.guard' => $event->guard,
            'auth.credentials.count' => count($event->credentials),
            TraceAttributes::ENDUSER_ID => $event->user?->getAuthIdentifier(),
        ]);

        $span->end();
    }

    public function recordLogout(Logout $event): void
    {
        $span = Measure::tracer()
            ->spanBuilder(SpanNameHelper::auth('logout'))
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setParent(Context::getCurrent())
            ->startSpan();

        $span->setAttributes([
            'auth.guard' => $event->guard,
            TraceAttributes::ENDUSER_ID => $event->user?->getAuthIdentifier(),
            'auth.user.type' => $event->user ? get_class($event->user) : null,
        ]);

        $span->end();
    }
}

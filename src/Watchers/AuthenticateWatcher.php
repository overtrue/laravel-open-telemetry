<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Watchers;

use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Foundation\Application;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Contrib\Instrumentation\Laravel\Watchers\Watcher;

/**
 * Authenticate Watcher
 *
 * Listen to authentication events, record authentication results, user ID, guard information
 */
class AuthenticateWatcher extends Watcher
{
    public function __construct(
        private readonly CachedInstrumentation $instrumentation,
    ) {}

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
        $span = $this->instrumentation
            ->tracer()
            ->spanBuilder('auth.attempting')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
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
        $span = $this->instrumentation
            ->tracer()
            ->spanBuilder('auth.authenticated')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $span->setAttributes([
            'auth.guard' => $event->guard,
            'auth.user.id' => $event->user->getAuthIdentifier(),
            'auth.user.type' => get_class($event->user),
        ]);

        $span->end();
    }

    public function recordLogin(Login $event): void
    {
        $span = $this->instrumentation
            ->tracer()
            ->spanBuilder('auth.login')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $span->setAttributes([
            'auth.guard' => $event->guard,
            'auth.user.id' => $event->user->getAuthIdentifier(),
            'auth.user.type' => get_class($event->user),
            'auth.remember' => $event->remember,
        ]);

        $span->end();
    }

    public function recordFailed(Failed $event): void
    {
        $span = $this->instrumentation
            ->tracer()
            ->spanBuilder('auth.failed')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $span->setAttributes([
            'auth.guard' => $event->guard,
            'auth.credentials.count' => count($event->credentials),
            'auth.user.id' => $event->user ? $event->user->getAuthIdentifier() : null,
        ]);

        $span->end();
    }

    public function recordLogout(Logout $event): void
    {
        $span = $this->instrumentation
            ->tracer()
            ->spanBuilder('auth.logout')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $span->setAttributes([
            'auth.guard' => $event->guard,
            'auth.user.id' => $event->user->getAuthIdentifier(),
            'auth.user.type' => get_class($event->user),
        ]);

        $span->end();
    }
}

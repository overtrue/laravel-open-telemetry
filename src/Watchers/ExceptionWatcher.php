<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Watchers;

use Illuminate\Contracts\Foundation\Application;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Contrib\Instrumentation\Laravel\Watchers\Watcher;
use Throwable;

/**
 * Exception Watcher
 *
 * Listen to exception handling, record exception type, message, file, and line number
 */
class ExceptionWatcher extends Watcher
{
    public function __construct(
        private readonly CachedInstrumentation $instrumentation,
    ) {}

    public function register(Application $app): void
    {
        $app['events']->listen('Illuminate\Log\Events\MessageLogged', [$this, 'recordException']);
    }

    public function recordException($event): void
    {
        // Only handle error and critical level logs
        if (! in_array($event->level, ['error', 'critical', 'emergency'])) {
            return;
        }

        // Check if exception information is included
        $exception = $event->context['exception'] ?? null;
        if (! $exception instanceof Throwable) {
            return;
        }

        $span = $this->instrumentation
            ->tracer()
            ->spanBuilder('exception')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $span->recordException($exception, [
            'exception.escaped' => true,
        ]);

        $span->setAttributes([
            'exception.type' => get_class($exception),
            'exception.message' => $exception->getMessage(),
            'exception.file' => $exception->getFile(),
            'exception.line' => $exception->getLine(),
            'log.level' => $event->level,
            'log.channel' => $event->context['log_channel'] ?? 'unknown',
        ]);

        $span->end();
    }
}

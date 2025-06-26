<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Watchers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\Events\MessageLogged;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Support\SpanNameHelper;
use Throwable;

/**
 * Exception Watcher
 *
 * Listen to exception handling, record exception type, message, file, and line number
 */
class ExceptionWatcher extends Watcher
{
    public function register(Application $app): void
    {
        $app['events']->listen(\Illuminate\Log\Events\MessageLogged::class, [$this, 'recordException']);
    }

    public function recordException(MessageLogged $event): void
    {
        if (! isset($event->context['exception']) || ! ($event->context['exception'] instanceof Throwable)) {
            return;
        }

        $exception = $event->context['exception'];
        $tracer = Measure::tracer();

        $span = $tracer->spanBuilder(SpanNameHelper::exception(get_class($exception)))
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setParent(Context::getCurrent())
            ->startSpan();

        $span->recordException($exception, [
            'exception.message' => $exception->getMessage(),
            'exception.code' => $exception->getCode(),
        ]);

        $span->end();
    }
}

<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Watchers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\Events\MessageLogged;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SemConv\TraceAttributes;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Throwable;

class ExceptionWatcher implements Watcher
{
    public function register(Application $app): void
    {
        $app['events']->listen(MessageLogged::class, $this->recordException(...));
    }

    public function recordException(MessageLogged $log): void
    {
        if (! isset($log->context['exception']) ||
            ! $log->context['exception'] instanceof Throwable) {
            return;
        }

        $exception = $log->context['exception'];
        $scope = Measure::activeScope();

        if (! $scope) {
            return;
        }

        $span = Measure::activeSpan();
        $span->recordException($exception, [TraceAttributes::EXCEPTION_ESCAPED => true]);
        $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
    }
}

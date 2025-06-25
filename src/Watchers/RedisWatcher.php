<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Watchers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Redis\Events\CommandExecuted;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SemConv\TraceAttributes;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Support\SpanNameHelper;
use Overtrue\LaravelOpenTelemetry\Watchers\Watcher;

/**
 * Redis Watcher
 *
 * Listen to Redis commands, record command, parameters, and result type
 */
class RedisWatcher extends Watcher
{
    public function register(Application $app): void
    {
        $app['events']->listen(CommandExecuted::class, [$this, 'recordCommand']);
    }

    public function recordCommand(CommandExecuted $event): void
    {
        $now = (int) (microtime(true) * 1e9);
        $startTime = $now - (int) ($event->time * 1e6);

        $span = Measure::tracer()
            ->spanBuilder(SpanNameHelper::redis($event->command))
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setStartTimestamp($startTime)
            ->startSpan();

        $attributes = [
            TraceAttributes::DB_SYSTEM => 'redis',
            TraceAttributes::DB_STATEMENT => $this->formatCommand($event->command, $event->parameters),
            'db.connection' => $event->connectionName,
            'db.command.time_ms' => $event->time,
        ];

        $span->setAttributes($attributes)->end($now);
    }

    protected function formatCommand(string $command, array $parameters): string
    {
        $parameters = implode(' ', array_map(fn ($param) => is_string($param) ? (strlen($param) > 100 ? substr($param, 0, 100) . '...' : $param) : (is_scalar($param) ? strval($param) : gettype($param)), $parameters));

        return "{$command} {$parameters}";
    }
}

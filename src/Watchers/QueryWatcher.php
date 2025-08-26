<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Watchers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Str;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Support\SpanNameHelper;

class QueryWatcher extends Watcher
{
    public function register(Application $app): void
    {
        $app['events']->listen(QueryExecuted::class, [$this, 'recordQuery']);
    }

    public function recordQuery(QueryExecuted $event): void
    {
        $now = (int) (microtime(true) * 1e9);
        $startTime = $now - (int) ($event->time * 1e6);

        $span = Measure::tracer()
            ->spanBuilder(SpanNameHelper::database($this->getOperationName($event->sql), $this->extractTableName($event->sql)))
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setStartTimestamp($startTime)
            ->setParent(Context::getCurrent())
            ->startSpan();

        $span->setAttributes([
            TraceAttributes::DB_SYSTEM => $event->connection->getDriverName(),
            TraceAttributes::DB_NAME => $event->connection->getDatabaseName(),
            TraceAttributes::DB_STATEMENT => $event->sql,
            'db.connection' => $event->connectionName,
            'db.query.time_ms' => $event->time,
        ]);

        $span->end($now);
    }

    protected function getOperationName(string $sql): string
    {
        $name = Str::upper(Str::before($sql, ' '));

        return in_array($name, ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'CREATE', 'ALTER', 'DROP', 'TRUNCATE']) ? $name : 'QUERY';
    }

    protected function extractTableName(string $sql): ?string
    {
        if (preg_match('/(?:from|into|update|join|table)\s+[`"\']?(\w+)[`"\']?/i', $sql, $matches)) {
            return $matches[1];
        }

        return null;
    }
}

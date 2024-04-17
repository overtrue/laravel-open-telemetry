<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Watchers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Str;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SemConv\TraceAttributes;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

class QueryWatcher implements Watcher
{
    public function register(Application $app): void
    {
        $app['events']->listen(QueryExecuted::class, [$this, 'recordQuery']);
    }

    public function recordQuery(QueryExecuted $query): void
    {
        $nowInNs = (int) (microtime(true) * 1E9);

        $operationName = Str::upper(Str::before($query->sql, ' '));
        if (! in_array($operationName, ['SELECT', 'INSERT', 'UPDATE', 'DELETE'])) {
            $operationName = null;
        }

        $span = Measure::getTracer()
            ->spanBuilder('[DB] '.$operationName)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setStartTimestamp($this->calculateQueryStartTime($nowInNs, $query->time))
            ->startSpan();

        $attributes = [
            TraceAttributes::DB_SYSTEM => $query->connection->getDriverName(),
            TraceAttributes::DB_NAME => $query->connection->getDatabaseName(),
            TraceAttributes::DB_OPERATION => $operationName,
            TraceAttributes::DB_USER => $query->connection->getConfig('username'),
        ];

        $attributes[TraceAttributes::DB_STATEMENT] = $query->sql;

        $span->setAttributes($attributes);
        $span->end($nowInNs);
    }

    private function calculateQueryStartTime(int $nowInNs, float $queryTimeMs): int
    {
        return (int) ($nowInNs - ($queryTimeMs * 1E6));
    }
}

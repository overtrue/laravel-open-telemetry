<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Watchers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Str;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SemConv\TraceAttributes;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Traits\InteractWithEventTimestamp;

class QueryWatcher implements Watcher
{
    use InteractWithEventTimestamp;

    public function register(Application $app): void
    {
        $app['events']->listen(QueryExecuted::class, $this->recordQuery(...));
    }

    public function recordQuery(QueryExecuted $query): void
    {
        $nowInNs = (int) (microtime(true) * 1E9);

        $operationName = Str::upper(Str::before($query->sql, ' '));
        if (! in_array($operationName, ['SELECT', 'INSERT', 'UPDATE', 'DELETE'])) {
            $operationName = null;
        }

        $span = Measure::span(sprintf('[DB] %s', $operationName))
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setStartTimestamp($this->getEventStartTimestampNs($query->time))
            ->start();

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
}

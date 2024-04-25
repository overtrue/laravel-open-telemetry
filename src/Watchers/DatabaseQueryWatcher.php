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

class DatabaseQueryWatcher implements Watcher
{
    use InteractWithEventTimestamp;

    public function register(Application $app): void
    {
        $app['events']->listen(QueryExecuted::class, $this->recordQuery(...));
    }

    public function recordQuery(QueryExecuted $query): void
    {
        $operationName = Str::upper(Str::before($query->sql, ' '));

        if (! in_array($operationName, ['SELECT', 'INSERT', 'UPDATE', 'DELETE'])) {
            $operationName = null;
        }

        $table = $this->getTableName($query->sql);
        $rawSql = $query->connection->getQueryGrammar()?->substituteBindingsIntoRawSql($query->sql, $query->bindings);

        $spanName = sprintf('[DB][%s][%s] %s', $operationName, $table, Str::limit($rawSql, 50));

        $span = Measure::span($spanName)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setStartTimestamp($this->getEventStartTimestampNs($query->time))
            ->start();

        $span->setAttributes([
            'db.sql.raw' => $rawSql,
            TraceAttributes::DB_SYSTEM => $query->connection->getDriverName(),
            TraceAttributes::DB_NAME => $query->connection->getDatabaseName(),
            TraceAttributes::DB_OPERATION => $operationName,
            TraceAttributes::DB_USER => $query->connection->getConfig('username'),
            TraceAttributes::DB_STATEMENT => $query->sql,
            TraceAttributes::DB_SQL_TABLE => $table,
        ]);

        $span->end();
    }

    protected function getTableName(string $sql): string
    {
        // update
        if (preg_match('/update\s+`?(\w+)`?/i', $sql, $matches)) {
            return $matches[1];
        }

        // insert
        if (preg_match('/insert\s+into\s+`?(\w+)`?/i', $sql, $matches)) {
            return $matches[1];
        }

        if (preg_match('/from\s+`?(\w+)`?/i', $sql, $matches)) {
            return $matches[1];
        }

        return 'unknown';
    }
}

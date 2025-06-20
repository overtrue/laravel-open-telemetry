<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Watchers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Redis\Events\CommandExecuted;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Contrib\Instrumentation\Laravel\Watchers\Watcher;

/**
 * Redis Watcher
 *
 * Listen to Redis commands, record command, parameters, and result type
 */
class RedisWatcher extends Watcher
{
    public function __construct(
        private readonly CachedInstrumentation $instrumentation,
    ) {
    }

    public function register(Application $app): void
    {
        $app['events']->listen(CommandExecuted::class, [$this, 'recordCommand']);
    }

    public function recordCommand(CommandExecuted $event): void
    {
        $span = $this->instrumentation
            ->tracer()
            ->spanBuilder(sprintf('redis %s', strtolower($event->command)))
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->startSpan();

        $attributes = [
            'db.system' => 'redis',
            'db.operation' => strtolower($event->command),
            'redis.command' => strtoupper($event->command),
            'redis.connection_name' => $event->connectionName,
            'redis.time' => $event->time,
        ];

        // Record parameters (limit length to avoid oversized spans)
        if (! empty($event->parameters)) {
            $argsString = implode(' ', array_map(function ($arg) {
                return is_string($arg) ? (strlen($arg) > 100 ? substr($arg, 0, 100).'...' : $arg) :
                       (is_scalar($arg) ? (string) $arg : gettype($arg));
            }, array_slice($event->parameters, 0, 5))); // Only record first 5 parameters
            $attributes['redis.args'] = $argsString;
            $attributes['redis.args_count'] = count($event->parameters);
        }

        $span->setAttributes($attributes);
        $span->end();
    }
}

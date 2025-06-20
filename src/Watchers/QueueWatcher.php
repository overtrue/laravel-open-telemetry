<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Watchers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Contrib\Instrumentation\Laravel\Watchers\Watcher;

/**
 * Queue Watcher
 *
 * Listen to queue job processing and enqueueing, record connection, queue name, job information, status
 */
class QueueWatcher extends Watcher
{
    public function __construct(
        private readonly CachedInstrumentation $instrumentation,
    ) {
    }

    public function register(Application $app): void
    {
        $app['events']->listen(JobQueued::class, [$this, 'recordJobQueued']);
        $app['events']->listen(JobProcessing::class, [$this, 'recordJobProcessing']);
        $app['events']->listen(JobProcessed::class, [$this, 'recordJobProcessed']);
        $app['events']->listen(JobFailed::class, [$this, 'recordJobFailed']);
    }

    public function recordJobQueued(JobQueued $event): void
    {
        $span = $this->instrumentation
            ->tracer()
            ->spanBuilder('queue.queued')
            ->setSpanKind(SpanKind::KIND_PRODUCER)
            ->startSpan();

        $attributes = [
            'queue.connection' => $event->connectionName,
            'queue.name' => $event->queue,
            'queue.job.class' => $event->job::class,
            'queue.job.id' => $event->id,
        ];

        // Record delay time
        if (method_exists($event->job, 'delay') && $event->job->delay) {
            $attributes['queue.job.delay'] = $event->job->delay;
        }

        $span->setAttributes($attributes);
        $span->end();
    }

    public function recordJobProcessing(JobProcessing $event): void
    {
        $span = $this->instrumentation
            ->tracer()
            ->spanBuilder('queue.processing')
            ->setSpanKind(SpanKind::KIND_CONSUMER)
            ->startSpan();

        $payload = $event->job->payload();

        $attributes = [
            'queue.connection' => $event->connectionName,
            'queue.name' => $event->job->getQueue(),
            'queue.job.class' => $payload['displayName'] ?? 'unknown',
            'queue.job.id' => $event->job->getJobId(),
            'queue.job.attempts' => $event->job->attempts(),
            'queue.job.max_tries' => $payload['maxTries'] ?? null,
            'queue.job.timeout' => $payload['timeout'] ?? null,
        ];

        // Record job data size
        if (isset($payload['data'])) {
            $attributes['queue.job.data_size'] = strlen(serialize($payload['data']));
        }

        $span->setAttributes($attributes);
        $span->end();
    }

    public function recordJobProcessed(JobProcessed $event): void
    {
        $span = $this->instrumentation
            ->tracer()
            ->spanBuilder('queue.processed')
            ->setSpanKind(SpanKind::KIND_CONSUMER)
            ->startSpan();

        $payload = $event->job->payload();

        $attributes = [
            'queue.connection' => $event->connectionName,
            'queue.name' => $event->job->getQueue(),
            'queue.job.class' => $payload['displayName'] ?? 'unknown',
            'queue.job.id' => $event->job->getJobId(),
            'queue.job.attempts' => $event->job->attempts(),
            'queue.job.status' => 'completed',
        ];

        $span->setAttributes($attributes);
        $span->end();
    }

    public function recordJobFailed(JobFailed $event): void
    {
        $span = $this->instrumentation
            ->tracer()
            ->spanBuilder('queue.failed')
            ->setSpanKind(SpanKind::KIND_CONSUMER)
            ->startSpan();

        $payload = $event->job->payload();

        $attributes = [
            'queue.connection' => $event->connectionName,
            'queue.name' => $event->job->getQueue(),
            'queue.job.class' => $payload['displayName'] ?? 'unknown',
            'queue.job.id' => $event->job->getJobId(),
            'queue.job.attempts' => $event->job->attempts(),
            'queue.job.status' => 'failed',
            'queue.job.error' => $event->exception->getMessage(),
            'queue.job.error_type' => get_class($event->exception),
        ];

        $span->recordException($event->exception);
        $span->setAttributes($attributes);
        $span->end();
    }
}

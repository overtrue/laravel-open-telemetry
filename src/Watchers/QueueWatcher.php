<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Watchers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SemConv\TraceAttributes;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Support\SpanNameHelper;
use Overtrue\LaravelOpenTelemetry\Watchers\Watcher;

/**
 * Queue Watcher
 *
 * Listen to queue job processing and enqueueing, record connection, queue name, job information, status
 */
class QueueWatcher extends Watcher
{
    public function register(Application $app): void
    {
        $app['events']->listen(JobQueued::class, [$this, 'recordJobQueued']);
        $app['events']->listen(JobProcessing::class, [$this, 'recordJobProcessing']);
        $app['events']->listen(JobProcessed::class, [$this, 'recordJobProcessed']);
        $app['events']->listen(JobFailed::class, [$this, 'recordJobFailed']);
    }

    public function recordJobQueued(JobQueued $event): void
    {
        $jobClass = is_object($event->job) ? get_class($event->job) : $event->job;

        $span = Measure::tracer()
            ->spanBuilder(SpanNameHelper::queue('publish', $jobClass))
            ->setSpanKind(SpanKind::KIND_PRODUCER)
            ->startSpan();

        $attributes = [
            TraceAttributes::MESSAGING_SYSTEM => $event->connectionName,
            TraceAttributes::MESSAGING_DESTINATION_NAME => $event->queue,
            TraceAttributes::MESSAGING_MESSAGE_ID => $event->id,
            'messaging.job.class' => $jobClass,
        ];

        if (is_object($event->job) && method_exists($event->job, 'delay') && $event->job->delay) {
            $attributes['messaging.job.delay_seconds'] = $event->job->delay;
        }

        $span->setAttributes($attributes);
        $span->end();
    }

    public function recordJobProcessing(JobProcessing $event): void
    {
        $payload = $event->job->payload();
        $jobClass = $payload['displayName'] ?? 'unknown';

        $span = Measure::tracer()
            ->spanBuilder(SpanNameHelper::queue('process', $jobClass))
            ->setSpanKind(SpanKind::KIND_CONSUMER)
            ->startSpan();

        $attributes = [
            TraceAttributes::MESSAGING_SYSTEM => $event->connectionName,
            TraceAttributes::MESSAGING_DESTINATION_NAME => $event->job->getQueue(),
            TraceAttributes::MESSAGING_MESSAGE_ID => $event->job->getJobId(),
            'messaging.job.class' => $jobClass,
            'messaging.job.attempts' => $event->job->attempts(),
            'messaging.job.max_tries' => $payload['maxTries'] ?? null,
            'messaging.job.timeout' => $payload['timeout'] ?? null,
        ];

        if (isset($payload['data'])) {
            $attributes['messaging.job.data_size'] = strlen(serialize($payload['data']));
        }

        $span->setAttributes($attributes)->end();
    }

    public function recordJobProcessed(JobProcessed $event): void
    {
        $jobClass = $event->job->payload()['displayName'] ?? 'unknown';

        Measure::addEvent('queue.job.processed', [
            'messaging.job.id' => $event->job->getJobId(),
            'messaging.job.class' => $jobClass,
            'messaging.job.status' => 'completed',
        ]);
    }

    public function recordJobFailed(JobFailed $event): void
    {
        $jobClass = $event->job->payload()['displayName'] ?? 'unknown';

        Measure::recordException($event->exception, [
            'messaging.job.id' => $event->job->getJobId(),
            'messaging.job.class' => $jobClass,
            'messaging.job.status' => 'failed',
        ]);
    }
}

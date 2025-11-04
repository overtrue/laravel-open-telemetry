<?php

namespace Overtrue\LaravelOpenTelemetry\Tests\Watchers;

use Exception;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Jobs\Job;
use Mockery;
use OpenTelemetry\SemConv\TraceAttributes;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Support\MeasureDataFlusher;
use Overtrue\LaravelOpenTelemetry\Tests\TestCase;
use Overtrue\LaravelOpenTelemetry\Watchers\QueueWatcher;

class QueueWatcherTest extends TestCase
{
    private QueueWatcher $watcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->watcher = new QueueWatcher;
    }

    public function test_registers_queue_event_listeners()
    {
        $events = Mockery::mock();
        $events->shouldReceive('listen')->with(JobQueued::class, [$this->watcher, 'recordJobQueued'])->once();
        $events->shouldReceive('listen')->with(JobProcessing::class, [$this->watcher, 'recordJobProcessing'])->once();
        $events->shouldReceive('listen')->with(JobProcessed::class, [$this->watcher, 'recordJobProcessed'])->once();
        $events->shouldReceive('listen')->with(JobFailed::class, [$this->watcher, 'recordJobFailed'])->once();

        $app = Mockery::mock(\Illuminate\Contracts\Foundation\Application::class, \ArrayAccess::class);
        $app->shouldReceive('offsetGet')->with('events')->andReturn($events);

        $this->watcher->register($app);
    }

    public function test_records_job_queued_event_with_object_job()
    {
        $job = new \stdClass;
        $event = new JobQueued('redis', 'test-queue', 'job-123', $job, '', null);

        Measure::shouldReceive('addEvent')
            ->once()
            ->with('queue.job.queued', [
                TraceAttributes::MESSAGING_SYSTEM => 'redis',
                TraceAttributes::MESSAGING_DESTINATION_NAME => 'test-queue',
                TraceAttributes::MESSAGING_MESSAGE_ID => 'job-123',
                'messaging.job.class' => 'stdClass',
            ]);

        $this->watcher->recordJobQueued($event);
    }

    public function test_records_job_queued_event_with_string_job()
    {
        $event = new JobQueued('database', 'default', 'job-456', 'App\\Jobs\\TestJob', '', null);

        Measure::shouldReceive('addEvent')
            ->once()
            ->with('queue.job.queued', [
                TraceAttributes::MESSAGING_SYSTEM => 'database',
                TraceAttributes::MESSAGING_DESTINATION_NAME => 'default',
                TraceAttributes::MESSAGING_MESSAGE_ID => 'job-456',
                'messaging.job.class' => 'App\\Jobs\\TestJob',
            ]);

        $this->watcher->recordJobQueued($event);
    }

    public function test_records_job_processing_event()
    {
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('payload')->andReturn([
            'displayName' => 'App\\Jobs\\ProcessPayment',
            'maxTries' => 3,
            'timeout' => 60,
            'data' => ['user_id' => 123, 'amount' => 100],
        ]);
        $job->shouldReceive('getQueue')->andReturn('payments');
        $job->shouldReceive('getJobId')->andReturn('job-abc');
        $job->shouldReceive('attempts')->andReturn(1);

        $event = new JobProcessing('redis', $job);

        Measure::shouldReceive('addEvent')
            ->once()
            ->with('queue.job.processing', [
                TraceAttributes::MESSAGING_SYSTEM => 'redis',
                TraceAttributes::MESSAGING_DESTINATION_NAME => 'payments',
                TraceAttributes::MESSAGING_MESSAGE_ID => 'job-abc',
                'messaging.job.class' => 'App\\Jobs\\ProcessPayment',
                'messaging.job.attempts' => 1,
                'messaging.job.max_tries' => 3,
                'messaging.job.timeout' => 60,
                'messaging.job.data_size' => strlen(serialize(['user_id' => 123, 'amount' => 100])),
            ]);

        $this->watcher->recordJobProcessing($event);
    }

    public function test_records_job_processed_event()
    {
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('payload')->andReturn(['displayName' => 'App\\Jobs\\SendEmail']);
        $job->shouldReceive('getJobId')->andReturn('job-ghi');

        $event = new JobProcessed('redis', $job);

        Measure::shouldReceive('addEvent')
            ->once()
            ->with('queue.job.processed', [
                'messaging.job.id' => 'job-ghi',
                'messaging.job.class' => 'App\\Jobs\\SendEmail',
                'messaging.job.status' => 'completed',
            ]);

        $flusher = Mockery::mock('alias:'.MeasureDataFlusher::class);
        $flusher->shouldReceive('flush')->once();

        $this->watcher->recordJobProcessed($event);
    }

    public function test_records_job_failed_event()
    {
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('payload')->andReturn(['displayName' => 'App\\Jobs\\FailingJob']);
        $job->shouldReceive('getJobId')->andReturn('job-jkl');

        $exception = new Exception('Job failed');
        $event = new JobFailed('redis', $job, $exception);

        Measure::shouldReceive('recordException')
            ->once()
            ->with($exception, [
                'messaging.job.id' => 'job-jkl',
                'messaging.job.class' => 'App\\Jobs\\FailingJob',
                'messaging.job.status' => 'failed',
            ]);

        $flusher = Mockery::mock('alias:'.MeasureDataFlusher::class);
        $flusher->shouldReceive('flush')->once();

        $this->watcher->recordJobFailed($event);
    }

    public function test_records_job_processing_event_with_unknown_job()
    {
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('payload')->andReturn([]);
        $job->shouldReceive('getQueue')->andReturn('default');
        $job->shouldReceive('getJobId')->andReturn('job-def');
        $job->shouldReceive('attempts')->andReturn(2);

        $event = new JobProcessing('database', $job);

        Measure::shouldReceive('addEvent')
            ->once()
            ->with('queue.job.processing', [
                TraceAttributes::MESSAGING_SYSTEM => 'database',
                TraceAttributes::MESSAGING_DESTINATION_NAME => 'default',
                TraceAttributes::MESSAGING_MESSAGE_ID => 'job-def',
                'messaging.job.class' => 'unknown',
                'messaging.job.attempts' => 2,
                'messaging.job.max_tries' => null,
                'messaging.job.timeout' => null,
            ]);

        $this->watcher->recordJobProcessing($event);
    }

    public function test_records_job_queued_event_with_delay()
    {
        $job = Mockery::mock();
        $job->delay = 300;

        $event = new JobQueued('redis', 'delayed', 'job-789', $job, '', null);

        Measure::shouldReceive('addEvent')
            ->once()
            ->with('queue.job.queued', Mockery::on(function ($attributes) {
                return $attributes[TraceAttributes::MESSAGING_SYSTEM] === 'redis'
                    && $attributes[TraceAttributes::MESSAGING_DESTINATION_NAME] === 'delayed'
                    && $attributes[TraceAttributes::MESSAGING_MESSAGE_ID] === 'job-789'
                    && str_starts_with($attributes['messaging.job.class'], 'Mockery_');
            }));

        $this->watcher->recordJobQueued($event);
    }
}

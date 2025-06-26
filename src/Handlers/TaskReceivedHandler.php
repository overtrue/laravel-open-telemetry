<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Handlers;

use Laravel\Octane\Events\TaskReceived;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

class TaskReceivedHandler
{
    /**
     * Handle the event.
     */
    public function handle(TaskReceived $event): void
    {
        // Create child span to track task
        $span = Measure::start(sprintf('TASK %s', $event->name));

        $span->setAttributes([
            'task.name' => $event->name,
            'task.payload' => json_encode($event->payload),
        ]);

        // Task completion ends span
        // Note: This should call $span->end() after task execution
        // But due to Octane's event mechanism, we let it end automatically for now
    }
}

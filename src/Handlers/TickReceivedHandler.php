<?php

namespace Overtrue\LaravelOpenTelemetry\Handlers;

use Laravel\Octane\Events\TickReceived;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

class TickReceivedHandler
{
    /**
     * Handle the event.
     */
    public function handle(TickReceived $event): void
    {
        // 创建子 span 来跟踪定时任务
        $span = Measure::start('octane.tick', function ($spanBuilder) {
            $spanBuilder->setAttributes([
                'tick.timestamp' => time(),
                'tick.type' => 'scheduled',
            ]);
        });

        // 定时任务通常很快完成，立即结束 span
        $span->end();
    }
}

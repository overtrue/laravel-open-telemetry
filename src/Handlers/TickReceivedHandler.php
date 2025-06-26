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
        // Create child span to track tick event
        $span = Measure::start('octane.tick', function ($spanBuilder) {
            $spanBuilder->setAttributes([
                'tick.timestamp' => time(),
                'tick.type' => 'scheduled',
            ]);
        });

        // Tick events are usually quick, end span immediately
        $span->end();
    }
}

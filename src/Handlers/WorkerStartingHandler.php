<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Handlers;

use Illuminate\Support\Facades\Log;
use Laravel\Octane\Events\WorkerStarting;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

class WorkerStartingHandler
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle(WorkerStarting $event): void
    {
        // Only handle in Octane mode
        if (! Measure::isOctane()) {
            return;
        }

        // Validate OTEL environment variables
        if (! config('otel.enabled', true)) {
            return;
        }

        // Reset state only for worker initialization in Octane mode
        Measure::reset();

        // Worker initialization logic can be added here
        // For example, setting up worker-specific spans or contexts

        Log::debug('OpenTelemetry Octane: Worker starting handler called');

        // 验证OTEL环境变量
        $otelVars = [
            'OTEL_SERVICE_NAME',
            'OTEL_EXPORTER_OTLP_ENDPOINT',
            'OTEL_EXPORTER_OTLP_PROTOCOL',
        ];

        foreach ($otelVars as $var) {
            if (! isset($_ENV[$var]) && ! isset($_SERVER[$var])) {
                Log::warning('OpenTelemetry Octane: Missing required environment variable', [
                    'variable' => $var,
                ]);
            } else {
                $value = $_ENV[$var] ?? $_SERVER[$var] ?? 'unknown';
                Log::debug('OpenTelemetry Octane: Environment variable configured', [
                    'variable' => $var,
                    'value' => $value,
                ]);
            }
        }
    }
}

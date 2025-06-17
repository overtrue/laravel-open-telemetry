<?php

namespace Overtrue\LaravelOpenTelemetry\Console\Commands;

use Illuminate\Console\Command;
use OpenTelemetry\API\Trace\StatusCode;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

class TestCommand extends Command
{
    /**
     * Command name and signature
     */
    protected $signature = 'otel:test';

    /**
     * Command description
     */
    protected $description = 'Create a test span and output the result';

    /**
     * Execute the command
     */
    public function handle(): int
    {
        $this->info('Creating test span...');

        // Create root span
        $rootSpan = Measure::start('Test Span');
        $rootSpan->span->setAttribute('test.attribute', 'test_value');
        $rootSpan->span->setAttribute('timestamp', time());

        // Simulate delay
        sleep(1);

        // Add child span
        $childSpan = Measure::start('Child Operation');
        $childSpan->span->setAttribute('child.attribute', 'child_value');

        sleep(1);

        // End child span
        Measure::end('Child Operation');

        // Record event
        $rootSpan->span->addEvent('Test Event', [
            'detail' => 'This is a test event',
        ]);

        // Set status
        $rootSpan->span->setStatus(StatusCode::STATUS_OK);

        // Get trace ID before ending the root span
        $traceId = $rootSpan->span->getContext()->getTraceId();

        // End root span
        Measure::end('Test Span');

        // Output result
        $this->info('Test completed!');
        $this->info('Trace ID: ' . $traceId);

        // Display information table
        $this->table(
            ['Span Name', 'Status', 'Attributes'],
            [
                ['Test Span', 'OK', 'test.attribute=test_value, timestamp=' . $rootSpan->span->getAttribute('timestamp')],
                ['Child Operation', 'OK', 'child.attribute=child_value'],
            ]
        );

        return Command::SUCCESS;
    }
}
<?php

namespace Overtrue\LaravelOpenTelemetry\Console\Commands;

use Illuminate\Console\Command;
use OpenTelemetry\API\Trace\NonRecordingSpan;
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
        $this->info('=== OpenTelemetry Test Command ===');
        $this->info('');

        if (! config('otel.enabled')) {
            $this->error('OpenTelemetry is disabled in config.');
            $this->info('Set OTEL_ENABLED=true in your .env file.');

            return Command::FAILURE;
        }

        // 获取详细状态信息
        $status = Measure::getStatus();

        $this->info('📊 Current Status:');
        $this->line('  Recording: '.($status['is_recording'] ? '<info>Yes</info>' : '<comment>No</comment>'));
        $this->line("  TracerProvider: <comment>{$status['tracer_provider']['class']}</comment>");
        $this->line("  Active Spans: <info>{$status['active_spans_count']}</info>");
        $this->info('');

        // Create a test span to check what type we get
        $rootSpan = Measure::start('Test Span');
        $spanClass = get_class($rootSpan->span);

        $this->info("Current Span type: {$spanClass}");
        $this->info('');

        // Check if we have a recording span
        if ($rootSpan->span instanceof NonRecordingSpan || ! Measure::isRecording()) {
            $this->warn('⚠️  OpenTelemetry is using NonRecordingSpan!');
            $this->info('');
            $this->info('This means OpenTelemetry SDK is not properly configured.');
            $this->info('');
            $this->info('📋 Required environment variables for OpenTelemetry:');
            $this->info('');
            $this->line('  <comment>OTEL_PHP_AUTOLOAD_ENABLED=true</comment>     # Enable PHP auto-instrumentation');
            $this->line('  <comment>OTEL_SERVICE_NAME=my-app</comment>           # Your service name');
            $this->line('  <comment>OTEL_TRACES_EXPORTER=console</comment>       # Export to console (for testing)');
            $this->line('  <comment># OR</comment>');
            $this->line('  <comment>OTEL_TRACES_EXPORTER=otlp</comment>          # Export to OTLP collector');
            $this->line('  <comment>OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4318</comment>');
            $this->info('');
            $this->info('💡 For testing with this package (manual instrumentation), add this to your .env file:');
            $this->info('');
            $this->line('  <info>OTEL_ENABLED=true</info>');
            $this->line('  <info>OTEL_SDK_AUTO_INITIALIZE=true</info>');
            $this->line('  <info>OTEL_SERVICE_NAME=laravel-otel-test</info>');
            $this->line('  <info>OTEL_TRACES_EXPORTER=console</info>');
            $this->info('');
            $this->warn('After adding these variables, restart your application and try again.');

            // Still continue with the test to show what would happen
            $this->info('');
            $this->info('Continuing with test (spans will not be recorded)...');
        } else {
            $this->info('✅ OpenTelemetry is properly configured!');
            $this->info('Creating test spans...');
        }

        $this->info('');

        $rootSpan->span->setAttribute('test.attribute', 'test_value');
        $timestamp = time();
        $rootSpan->span->setAttribute('timestamp', $timestamp);

        // Simulate delay
        $this->info('Creating child span...');
        sleep(1);

        // Add child span
        $childSpan = Measure::start('Child Operation');
        $childSpan->span->setAttribute('child.attribute', 'child_value');

        sleep(1);

        // End child span
        Measure::end('Child Operation');
        $this->info('Child span completed.');

        // Record event
        $rootSpan->span->addEvent('Test Event', [
            'detail' => 'This is a test event',
            'timestamp' => $timestamp,
        ]);

        // Set status
        $rootSpan->span->setStatus(StatusCode::STATUS_OK);

        // Get trace ID before ending the root span
        $traceId = $rootSpan->span->getContext()->getTraceId();

        // End root span
        Measure::end('Test Span');

        // Output result
        $this->info('');
        $this->info('✅ Test completed!');
        $this->info("📊 Trace ID: {$traceId}");

        if ($traceId === '00000000000000000000000000000000') {
            $this->warn('⚠️  Trace ID is all zeros - this indicates NonRecordingSpan');
        }

        // Display summary table
        $this->info('');
        $this->table(
            ['Span Name', 'Status', 'Attributes'],
            [
                ['Test Span', 'OK', "test.attribute=test_value, timestamp={$timestamp}"],
                ['Child Operation', 'OK', 'child.attribute=child_value'],
            ]
        );

        // 显示最终状态
        $finalStatus = Measure::getStatus();
        $this->info('');
        $this->info('📈 Final Status:');
        $this->line('  Recording: '.($finalStatus['is_recording'] ? '<info>Yes</info>' : '<comment>No</comment>'));
        $this->line("  Active Spans: <info>{$finalStatus['active_spans_count']}</info>");
        $this->line('  Current Trace ID: '.($finalStatus['current_trace_id'] ? "<info>{$finalStatus['current_trace_id']}</info>" : '<comment>None</comment>'));

        $this->info('');
        $this->info('🔍 Environment Check:');
        $envVars = [
            'OTEL_ENABLED' => config('otel.enabled') ? 'true' : 'false',
            'OTEL_SDK_AUTO_INITIALIZE' => config('otel.sdk.auto_initialize') ? 'true' : 'false',
            'OTEL_SERVICE_NAME' => config('otel.sdk.service_name', 'not set'),
            'OTEL_TRACES_EXPORTER' => config('otel.exporters.traces', 'not set'),
        ];

        foreach ($envVars as $key => $value) {
            $status = $value === 'not set' ? '<comment>not set</comment>' : "<info>{$value}</info>";
            $this->line("  {$key}: {$status}");
        }

        return Command::SUCCESS;
    }
}

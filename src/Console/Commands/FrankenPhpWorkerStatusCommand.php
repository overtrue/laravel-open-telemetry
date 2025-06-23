<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Console\Commands;

use Illuminate\Console\Command;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Watchers\FrankenPhpWorkerWatcher;

class FrankenPhpWorkerStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'otel:frankenphp-status';

    /**
     * The console command description.
     */
    protected $description = 'Display FrankenPHP worker mode status and OpenTelemetry integration information';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 FrankenPHP Worker Mode Status Check');
        $this->line('');

        // 检查 FrankenPHP 环境
        $this->checkFrankenPhpEnvironment();

        // 检查 Worker 模式状态
        $this->checkWorkerModeStatus();

        // 检查 OpenTelemetry 集成状态
        $this->checkOpenTelemetryIntegration();

        // 显示 Worker 统计信息
        $this->displayWorkerStats();

        // 显示内存使用情况
        $this->displayMemoryUsage();

        return Command::SUCCESS;
    }

    /**
     * 检查 FrankenPHP 环境
     */
    private function checkFrankenPhpEnvironment(): void
    {
        $this->info('🚀 FrankenPHP Environment:');

        $checks = [
            'FrankenPHP Function Available' => function_exists('frankenphp_handle_request'),
            'PHP SAPI is FrankenPHP' => php_sapi_name() === 'frankenphp',
            'Worker Mode Enabled' => (bool) ($_SERVER['FRANKENPHP_WORKER'] ?? false),
            'Worker Script' => $_SERVER['FRANKENPHP_WORKER_SCRIPT'] ?? 'Not set',
        ];

        foreach ($checks as $check => $status) {
            if (is_bool($status)) {
                $icon = $status ? '✅' : '❌';
                $statusText = $status ? 'Yes' : 'No';
            } else {
                $icon = '📄';
                $statusText = $status;
            }

            $this->line("  {$icon} {$check}: <info>{$statusText}</info>");
        }

        $this->line('');
    }

    /**
     * 检查 Worker 模式状态
     */
    private function checkWorkerModeStatus(): void
    {
        $this->info('⚡ Worker Mode Status:');

        $workerStatus = Measure::getWorkerStatus();

        if (!$workerStatus['worker_mode']) {
            $this->line('  ⚠️  Not running in FrankenPHP worker mode');
            $this->line('');
            return;
        }

        $this->line("  🔄 Worker Mode: <info>Active</info>");
        $this->line("  🆔 Process ID: <info>{$workerStatus['pid']}</info>");
        $this->line("  📊 Memory Usage: <info>" . $this->formatBytes($workerStatus['memory_usage']) . "</info>");
        $this->line("  📈 Peak Memory: <info>" . $this->formatBytes($workerStatus['peak_memory']) . "</info>");
        $this->line("  🎯 Current Span Recording: <info>" . ($workerStatus['current_span_recording'] ? 'Yes' : 'No') . "</info>");
        $this->line("  🔗 Trace ID: <info>{$workerStatus['trace_id']}</info>");

        $this->line('');
    }

    /**
     * 检查 OpenTelemetry 集成状态
     */
    private function checkOpenTelemetryIntegration(): void
    {
        $this->info('🔬 OpenTelemetry Integration:');

        $integrationChecks = [
            'OpenTelemetry API Available' => class_exists('\OpenTelemetry\API\Globals'),
            'Tracer Available' => method_exists(Measure::class, 'tracer'),
            'Context Storage Available' => class_exists('\OpenTelemetry\Context\Context'),
            'FrankenPhp Worker Watcher Loaded' => class_exists(FrankenPhpWorkerWatcher::class),
        ];

        foreach ($integrationChecks as $check => $status) {
            $icon = $status ? '✅' : '❌';
            $statusText = $status ? 'Available' : 'Not Available';
            $this->line("  {$icon} {$check}: <info>{$statusText}</info>");
        }

        $this->line('');
    }

    /**
     * 显示 Worker 统计信息
     */
    private function displayWorkerStats(): void
    {
        if (!class_exists(FrankenPhpWorkerWatcher::class)) {
            return;
        }

        $this->info('📊 Worker Statistics:');

        try {
            $stats = FrankenPhpWorkerWatcher::getWorkerStats();

            $this->line("  📈 Request Count: <info>{$stats['request_count']}</info>");
            $this->line("  💾 Current Memory: <info>" . $this->formatBytes($stats['current_memory']) . "</info>");
            $this->line("  📊 Peak Memory: <info>" . $this->formatBytes($stats['peak_memory']) . "</info>");
            $this->line("  🔺 Memory Increase: <info>" . $this->formatBytes($stats['memory_increase']) . "</info>");
            $this->line("  🏁 Initial Memory: <info>" . $this->formatBytes($stats['initial_memory']) . "</info>");

            // 内存增长警告
            if ($stats['memory_increase'] > 50 * 1024 * 1024) { // 50MB
                $this->warn("  ⚠️  High memory increase detected!");
            }

        } catch (\Throwable $e) {
            $this->line("  ❌ Unable to retrieve worker stats: {$e->getMessage()}");
        }

        $this->line('');
    }

    /**
     * 显示内存使用情况
     */
    private function displayMemoryUsage(): void
    {
        $this->info('💾 Memory Usage:');

        $memoryLimit = ini_get('memory_limit');
        $currentMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        $this->line("  📊 Memory Limit: <info>{$memoryLimit}</info>");
        $this->line("  📈 Current Usage: <info>" . $this->formatBytes($currentMemory) . "</info>");
        $this->line("  🔝 Peak Usage: <info>" . $this->formatBytes($peakMemory) . "</info>");

        // 计算内存使用率
        if ($memoryLimit !== '-1') {
            $limitBytes = $this->parseMemoryLimit($memoryLimit);
            if ($limitBytes > 0) {
                $usagePercent = round(($currentMemory / $limitBytes) * 100, 2);
                $this->line("  📊 Usage Percentage: <info>{$usagePercent}%</info>");

                if ($usagePercent > 80) {
                    $this->warn("  ⚠️  High memory usage detected!");
                }
            }
        }

        $this->line('');

        // 显示建议
        $this->displayRecommendations();
    }

    /**
     * 显示建议
     */
    private function displayRecommendations(): void
    {
        $this->info('💡 Recommendations:');

        $workerStatus = Measure::getWorkerStatus();

        if ($workerStatus['worker_mode']) {
            $this->line('  ✨ FrankenPHP worker mode is active and properly integrated');
            $this->line('  🔄 Monitor memory usage regularly to prevent leaks');
            $this->line('  🧹 The system will automatically clean up resources between requests');
        } else {
            $this->line('  📝 To enable FrankenPHP worker mode:');
            $this->line('    1. Set FRANKENPHP_CONFIG="worker /path/to/worker.php"');
            $this->line('    2. Set FRANKENPHP_WORKER=true in your environment');
            $this->line('    3. Restart your FrankenPHP server');
        }
    }

    /**
     * 格式化字节数
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * 解析内存限制
     */
    private function parseMemoryLimit(string $limit): int
    {
        if ($limit === '-1') {
            return -1;
        }

        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }
}

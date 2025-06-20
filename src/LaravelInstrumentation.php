<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry;

use Overtrue\LaravelOpenTelemetry\Hooks;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;

/**
 * Laravel Enhanced Automatic Instrumentation
 *
 * This class builds upon the official opentelemetry-auto-laravel package,
 * registering additional Laravel-specific Watcher functionality
 */
class LaravelInstrumentation
{
    private static ?CachedInstrumentation $instrumentation = null;

    /**
     * Register enhancement Hooks
     */
    public static function register(): void
    {
        // Register ApplicationHook for Watchers
        $applicationHookClass = Hooks\Illuminate\Foundation\Application::class;
        if (class_exists($applicationHookClass)) {
            $hook = $applicationHookClass::hook(self::instrumentation());
            $hook->instrument();
        }

        // Register KernelHook for Response Trace ID
        $kernelHookClass = Hooks\Illuminate\Http\Kernel::class;
        if (class_exists($kernelHookClass)) {
            $hook = $kernelHookClass::hook(self::instrumentation());
            $hook->instrument();
        }
    }

    /**
     * Get instrumentation instance
     */
    private static function instrumentation(): CachedInstrumentation
    {
        if (self::$instrumentation === null) {
            self::$instrumentation = new CachedInstrumentation('io.opentelemetry.contrib.php.laravel');
        }

        return self::$instrumentation;
    }
}

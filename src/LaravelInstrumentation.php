<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry;

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
        Hooks\Illuminate\Foundation\Application::hook(self::instrumentation());
        Hooks\Illuminate\Contracts\Http\Kernel::hook(self::instrumentation());
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

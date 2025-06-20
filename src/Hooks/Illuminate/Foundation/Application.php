<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Hooks\Illuminate\Foundation;

use Illuminate\Foundation\Application as FoundationApplication;
use OpenTelemetry\Contrib\Instrumentation\Laravel\Hooks\LaravelHook;
use OpenTelemetry\Contrib\Instrumentation\Laravel\Hooks\LaravelHookTrait;
use function OpenTelemetry\Instrumentation\hook;

class Application implements LaravelHook
{
    use LaravelHookTrait;

    public function instrument(): void
    {
        hook(
            class: FoundationApplication::class,
            function: 'boot',
            post: function (FoundationApplication $app) {
                $this->registerWatchers($app);
            }
        );
    }

    /**
     * Register all configured Watchers
     */
    private function registerWatchers(FoundationApplication $app): void
    {
        $watchers = $app['config']->get('otel.watchers', []);

        foreach ($watchers as $watcherClass) {
            if (class_exists($watcherClass)) {
                $watcher = new $watcherClass($this->instrumentation);
                $watcher->register($app);
            }
        }
    }
}

<?php

namespace Overtrue\LaravelOpenTelemetry\Watchers;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Foundation\Application;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\Context;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

class CommandWatcher implements Watcher
{
    protected SpanInterface $span;

    public function register(Application $app): void
    {
        $app['events']->listen(CommandStarting::class, function (CommandStarting $event) {
            Measure::activeSpan()->updateName('[Command] '.$event->command);

            $this->span = Measure::span('[Command] '.$event->command)
                ->setAttributes([
                    'command' => $event->command,
                    'arguments' => $event->input->getArguments(),
                    'options' => $event->input->getOptions(),
                ])
                ->start();
            $this->span->storeInContext(Context::getCurrent());
        });

        $app['events']->listen(CommandFinished::class, function (CommandFinished $event) {
            $this->span->end();
        });
    }
}

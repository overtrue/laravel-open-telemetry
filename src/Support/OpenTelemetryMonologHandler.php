<?php

namespace Overtrue\LaravelOpenTelemetry\Support;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Overtrue\LaravelOpenTelemetry\Facades\Log;

class OpenTelemetryMonologHandler extends AbstractProcessingHandler
{
    protected function write(LogRecord $record): void
    {
        $level = $record->level->toPsrLogLevel();

        Log::log($level, $record->message, $record->context);
    }
}

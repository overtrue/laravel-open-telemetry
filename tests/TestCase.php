<?php

namespace Overtrue\LaravelOpenTelemetry\Tests;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\OpenTelemetryServiceProvider;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            OpenTelemetryServiceProvider::class,
        ];
    }
}

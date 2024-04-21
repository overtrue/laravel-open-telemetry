<?php

namespace Overtrue\LaravelOpenTelemetry\Tests;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use OpenTelemetry\Context\Context;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use Overtrue\LaravelOpenTelemetry\Middlewares\StartTracing;
use Overtrue\LaravelOpenTelemetry\OpenTelemetryServiceProvider;

class FooBarTestJob implements \Illuminate\Contracts\Queue\ShouldQueue
{
    public function handle()
    {
        DB::select('SELECT 1+3');
        sleep(1);
    }
}

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            OpenTelemetryServiceProvider::class,
        ];
    }

    protected function defineRoutes($router): void
    {
        config(['otle.default' => 'http-json']);
        config(['otle.default' => 'console']);

        $router->middleware(StartTracing::class)->get('/', function () {
            Measure::span(1)->setAttributes(['foo' => 'bar'])->start();
            sleep(1);
            Measure::end();

            return 'Hello, Laravel OpenTelemetry!';
        });
    }
}

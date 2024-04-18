<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry;

use Illuminate\Support\Manager;

class TracerManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return 'console';
    }

    public function createConsoleDriver(): Tracer
    {
        return $this->app(TracerFactory::class)->create('console');
    }

    public function createLogDriver(): Tracer
    {
        return $this->app(TracerFactory::class)->create('log');
    }

    public function createHttpJsonDriver(): Tracer
    {
        return $this->app(TracerFactory::class)->create('http-json');
    }

    public function createHttpBinaryDriver(): Tracer
    {
        return $this->app(TracerFactory::class)->create('http-binary');
    }

    public function createGrpcDriver(): Tracer
    {
        return $this->app(TracerFactory::class)->create('grpc');
    }
}

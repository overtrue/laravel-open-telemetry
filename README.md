# Laravel OpenTelemetry

[![Latest Version on Packagist](https://img.shields.io/packagist/v/overtrue/laravel-open-telemetry.svg?style=flat-square)](https://packagist.org/packages/overtrue/laravel-open-telemetry)
[![Total Downloads](https://img.shields.io/packagist/dt/overtrue/laravel-open-telemetry.svg?style=flat-square)](https://packagist.org/packages/overtrue/laravel-open-telemetry)

This package provides a simple way to add OpenTelemetry to your Laravel application.

## Features

- ✅ **Zero Configuration**: Works out of the box with sensible defaults.
- ✅ **Laravel Native**: Deep integration with Laravel's lifecycle and events.
- ✅ **Octane & FPM Support**: Full compatibility with Laravel Octane and traditional FPM setups.
- ✅ **Powerful `Measure` Facade**: Provides an elegant API for manual, semantic tracing.
- ✅ **Automatic Tracing**: Built-in watchers for cache, database, HTTP clients, queues, and more.
- ✅ **Flexible Configuration**: Control traced paths, headers, and watchers to fit your needs.
- ✅ **Standards Compliant**: Adheres to OpenTelemetry Semantic Conventions.

## Installation

You can install the package via composer:

```bash
composer require overtrue/laravel-open-telemetry
```

## Configuration

> **Important Note for Octane Users**
>
> When using Laravel Octane, it is **highly recommended** to set `OTEL_*` environment variables at the machine or process level (e.g., in your Dockerfile, `docker-compose.yml`, or Supervisor configuration) rather than relying solely on the `.env` file.
>
> This is because some OpenTelemetry components, especially those enabled by `OTEL_PHP_AUTOLOAD_ENABLED`, are initialized before the Laravel application fully boots and reads the `.env` file. Setting them as system-level environment variables ensures they are available to the PHP process from the very beginning.

This package uses the standard OpenTelemetry environment variables for configuration. Add these to your `.env` file for basic setup:

### Basic Configuration

```env
# Enable OpenTelemetry PHP SDK auto-loading
OTEL_PHP_AUTOLOAD_ENABLED=true

# Service identification
OTEL_SERVICE_NAME=my-laravel-app
OTEL_SERVICE_VERSION=1.0.0

# Exporter configuration (console for dev, otlp for prod)
OTEL_TRACES_EXPORTER=console
OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4318
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf

# Context propagation
OTEL_PROPAGATORS=tracecontext,baggage
```

### Package Configuration

For package-specific settings, publish the configuration file:

```bash
php artisan vendor:publish --provider="Overtrue\LaravelOpenTelemetry\OpenTelemetryServiceProvider" --tag=config
```

This will create a `config/otel.php` file. Here are the key options:

#### Enabling/Disabling Tracing

You can completely enable or disable tracing for the entire application. This is useful for performance tuning or disabling tracing in certain environments.

```php
// config/otel.php
'enabled' => env('OTEL_ENABLED', true),
```
Set `OTEL_ENABLED=false` in your `.env` file to disable all tracing.

#### Filtering Requests and Headers

You can control which requests are traced and which headers are recorded to enhance performance and protect sensitive data. All patterns support wildcards (`*`) and are case-insensitive.

- **`ignore_paths`**: A list of request paths to exclude from tracing. Useful for health checks, metrics endpoints, etc.
  ```php
  'ignore_paths' => ['health*', 'telescope*', 'horizon*'],
  ```
- **`allowed_headers`**: A list of HTTP header patterns to include in spans. If empty, no headers are recorded.
  ```php
  'allowed_headers' => ['x-request-id', 'user-agent', 'authorization'],
  ```
- **`sensitive_headers`**: A list of header patterns whose values will be masked (replaced with `***`).
  ```php
  'sensitive_headers' => ['authorization', 'cookie', 'x-api-key', '*-token'],
  ```

#### Watchers

You can enable or disable specific watchers to trace different parts of your application.

```php
// config/otel.php
'watchers' => [
    \Overtrue\LaravelOpenTelemetry\Watchers\CacheWatcher::class => env('OTEL_CACHE_WATCHER_ENABLED', true),
    \Overtrue\LaravelOpenTelemetry\Watchers\QueryWatcher::class => env('OTEL_QUERY_WATCHER_ENABLED', true),
    // ...
],
```

## Usage

The package is designed to work with minimal manual intervention, but it also provides a powerful `Measure` facade for creating custom spans.

### Automatic Tracing

With the default configuration, the package automatically traces:
- Incoming HTTP requests.
- Database queries (`QueryWatcher`).
- Cache operations (`CacheWatcher`).
- Outgoing HTTP client requests (`HttpClientWatcher`).
- Thrown exceptions (`ExceptionWatcher`).
- Queue jobs (`QueueWatcher`).
- ...and more, depending on the enabled [watchers](#watchers).

### Creating Custom Spans with `Measure::trace()`

For tracing specific blocks of code, the `Measure::trace()` method is the recommended approach. It automatically handles span creation, activation, exception recording, and completion.

```php
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

Measure::trace('process-user-data', function ($span) use ($user) {
    // Add attributes to the span
    $span->setAttribute('user.id', $user->id);

    // Your business logic here
    $this->process($user);

    // Add an event to mark a point in time within the span
    $span->addEvent('User processing finished');
});
```

The `trace` method will:
- Start a new span.
- Execute the callback.
- Automatically record and re-throw any exceptions that occur within the callback.
- End the span when the callback completes.

### Using Semantic Spans

To promote standardization, the package provides semantic helper methods that create spans with attributes conforming to OpenTelemetry's [Semantic Conventions](https://opentelemetry.io/docs/specs/semconv/).

#### Database Spans
```php
use OpenTelemetry\SemConv\TraceAttributes;

// Manually trace a block of database operations
$user = Measure::database('repository:find-user', function ($span) use ($userId) {
    $span->setAttribute(TraceAttributes::DB_STATEMENT, "SELECT * FROM users WHERE id = ?");
    return User::find($userId);
});
```
*Note: If `QueryWatcher` is enabled, individual queries are already traced. This is useful for tracing a larger transaction or a specific business operation involving multiple queries.*

#### Cache Spans
```php
$user = Measure::cache('fetch-user-from-cache', function ($span) use ($userId) {
    $span->setAttributes([
        'cache.key' => "user:{$userId}",
        'cache.ttl' => 3600,
    ]);
    return Cache::remember("user:{$userId}", 3600, fn() => User::find($userId));
});
```

#### Queue Spans
```php
// Manually trace putting a job on the queue
Measure::queue('dispatch-welcome-email', function() use ($user) {
    WelcomeEmailJob::dispatch($user);
});
```

### Retrieving the Current Span

You can access the currently active span anywhere in your code.

```php
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

$currentSpan = Measure::activeSpan();
$currentSpan->setAttribute('custom.attribute', 'some_value');
```

### Watchers

The package includes several watchers that automatically create spans for common Laravel operations. You can enable or disable them in `config/otel.php`.

- **`CacheWatcher`**: Traces cache hits, misses, writes, and forgets.
- **`QueryWatcher`**: Traces the execution of every database query.
- **`HttpClientWatcher`**: Traces all outgoing HTTP requests made with Laravel's `Http` facade.
- **`ExceptionWatcher`**: Traces all exceptions thrown in your application.
- **`QueueWatcher`**: Traces jobs being dispatched, processed, and failing.
- **`RedisWatcher`**: Traces Redis commands.
- **`AuthenticateWatcher`**: Traces authentication events like login, logout, and failed attempts.


### Trace ID Injection Middleware

The package includes middleware to add a `X-Trace-Id` header to your HTTP responses, which is useful for debugging.

You can apply it to specific routes:
```php
// In your routes/web.php or routes/api.php
Route::middleware('otel.traceid')->group(function () {
    Route::get('/api/users', [UserController::class, 'index']);
});
```

Or apply it globally in `app/Http/Kernel.php`:
```php
// app/Http/Kernel.php

// In the $middlewareGroups property for 'web' or 'api'
protected $middlewareGroups = [
    'web' => [
        // ...
        \Overtrue\LaravelOpenTelemetry\Http\Middleware\TraceIdMiddleware::class,
    ],
    // ...
];
```

## Environment Variables Reference

### Core OpenTelemetry Variables

| Variable | Description | Default | Example |
|----------|-------------|---------|---------|
| `OTEL_PHP_AUTOLOAD_ENABLED` | Enable PHP SDK auto-loading | `false` | `true` |
| `OTEL_SERVICE_NAME` | Service name | `unknown_service` | `my-laravel-app` |
| `OTEL_SERVICE_VERSION` | Service version | `null` | `1.0.0` |
| `OTEL_TRACES_EXPORTER` | Trace exporter type | `otlp` | `console`, `otlp` |
| `OTEL_EXPORTER_OTLP_ENDPOINT` | OTLP endpoint URL | `http://localhost:4318` | `https://api.honeycomb.io` |
| `OTEL_EXPORTER_OTLP_PROTOCOL` | OTLP protocol | `http/protobuf` | `http/protobuf`, `grpc` |
| `OTEL_PROPAGATORS` | Context propagators | `tracecontext,baggage` | `tracecontext,baggage,b3` |
| `OTEL_TRACES_SAMPLER` | Sampling strategy | `parentbased_always_on` | `always_on`, `traceidratio` |
| `OTEL_TRACES_SAMPLER_ARG` | Sampler argument | `null` | `0.1` |
| `OTEL_RESOURCE_ATTRIBUTES` | Resource attributes | `null` | `key1=value1,key2=value2` |

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [overtrue](https://github.com/overtrue)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

# Laravel OpenTelemetry

This package provides a simple way to add [OpenTelemetry](https://opentelemetry.io/) to your Laravel application.

[![CI](https://github.com/overtrue/laravel-open-telemetry/workflows/Test/badge.svg)](https://github.com/overtrue/laravel-open-telemetry/actions)
[![Latest Stable Version](https://poser.pugx.org/overtrue/laravel-open-telemetry/v/stable.svg)](https://packagist.org/packages/overtrue/laravel-open-telemetry)
[![Latest Unstable Version](https://poser.pugx.org/overtrue/laravel-open-telemetry/v/unstable.svg)](https://packagist.org/packages/overtrue/laravel-open-telemetry)
[![Total Downloads](https://poser.pugx.org/overtrue/laravel-open-telemetry/downloads)](https://packagist.org/packages/overtrue/laravel-open-telemetry)
[![License](https://poser.pugx.org/overtrue/laravel-open-telemetry/license)](https://packagist.org/packages/overtrue/laravel-open-telemetry)

## Installation

You can install the package via composer:

```bash
composer require overtrue/laravel-open-telemetry
```

## Usage

### Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Overtrue\LaravelOpenTelemetry\OpenTelemetryServiceProvider" --tag="config"
```

### Update the environment variables

> You can refer to [OpenTelemetry SDK Configuration Instructions](https://opentelemetry.io/docs/languages/sdk-configuration/general)

```dotenv
OTEL_ENABLED=true
OTEL_AUTO_TRACE_REQUESTS=true
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_PHP_TRACE_CLI_ENABLED=true
OTEL_SERVICE_NAME=my-app
OTEL_TRACES_EXPORTER=otlp
#OTEL_EXPORTER_OTLP_PROTOCOL=grpc
OTEL_EXPORTER_OTLP_ENDPOINT=http://collector:4318
OTEL_PROPAGATORS=baggage,tracecontext

OTEL_ALLOWED_HEADERS=*
OTEL_SENSITIVE_HEADERS=authorization,authorization,proxy-authorization
OTEL_IGNORE_PATHS=/foo,/bar*
```
and other environment variables, you can find them in the configuration file: `config/otle.php`.

### Register the middleware

you can register the middleware in the `app/Http/Kernel.php`:

```php
protected $middleware = [
    \Overtrue\LaravelOpenTelemetry\Middlewares\MeasureRequest::class,
    // ...
];
```

or you can set the env variable `OTEL_AUTO_TRACE_REQUESTS` to `true` to enable it automatically.

### Custom span

You can create a custom span by using the `Overtrue\LaravelOpenTelemetry\Facades\Measure` facade:

```php
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

Measure::span('your-span-name')->measure(function() {
    // ...
});
```

or manually start and end a span:

```php
Measure::start('your-span-name');

// ...

Measure::end();
```

and you can modify the span attributes by using a closure:

```php
Measure::start('your-span-name', function($span) {
    $span->setAttribute('key', 'value');
    // ...
});

// ...
Measure::end();
```

of course, you can get the span instance by using the `Measure::span()` method:

```php
$span = Measure::span('your-span-name');
$span->setAttribute('key', 'value');
$scope = $span->activate();

// ...

$span->end();
$scope->detach();
```

## Contributing

You can contribute in one of three ways:

1. File bug reports using the [issue tracker](https://github.com/overtrue/laravel-opentelemetry/issues).
2. Answer questions or fix bugs on the [issue tracker](https://github.com/overtrue/laravel-opentelemetry/issues).
3. Contribute new features or update the wiki.

_The code contribution process is not very formal. You just need to make sure that you follow the PSR-0, PSR-1, and PSR-2 coding guidelines. Any new code contributions must be accompanied by unit tests where applicable._

## :heart: Sponsor me

如果你喜欢我的项目并想支持它，[点击这里 :heart:](https://github.com/sponsors/overtrue)

## Project supported by JetBrains

Many thanks to Jetbrains for kindly providing a license for me to work on this and other open-source projects.

[![](https://resources.jetbrains.com/storage/products/company/brand/logos/jb_beam.svg)](https://www.jetbrains.com/?from=https://github.com/overtrue)


## License

MIT
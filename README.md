# Laravel OpenTelemetry

This package provides a simple way to add [OpenTelemetry](https://opentelemetry.io/) **manual instrumentation** to your Laravel application.

[![CI](https://github.com/overtrue/laravel-open-telemetry/workflows/Test/badge.svg)](https://github.com/overtrue/laravel-open-telemetry/actions)
[![Latest Stable Version](https://poser.pugx.org/overtrue/laravel-open-telemetry/v/stable.svg)](https://packagist.org/packages/overtrue/laravel-open-telemetry)
[![Latest Unstable Version](https://poser.pugx.org/overtrue/laravel-open-telemetry/v/unstable.svg)](https://packagist.org/packages/overtrue/laravel-open-telemetry)
[![Total Downloads](https://poser.pugx.org/overtrue/laravel-open-telemetry/downloads)](https://packagist.org/packages/overtrue/laravel-open-telemetry)
[![License](https://poser.pugx.org/overtrue/laravel-open-telemetry/license)](https://packagist.org/packages/overtrue/laravel-open-telemetry)

## 🎯 Package Positioning

### Built on Official Auto-Instrumentation

This package is **built on top of** the official [`open-telemetry/opentelemetry-auto-laravel`](https://packagist.org/packages/open-telemetry/opentelemetry-auto-laravel) package, providing additional manual instrumentation capabilities.

### Package Relationship

- **[Official Package](https://packagist.org/packages/open-telemetry/opentelemetry-auto-laravel)**: Foundation auto-instrumentation (automatically installed as dependency)
- **This Package**: Additional manual instrumentation with Laravel-style APIs and enhanced features

### When to Use This Package

- ✅ Need both automatic AND manual instrumentation
- ✅ Want precise control over span attributes and lifecycle
- ✅ Need to integrate deeply with Laravel events and services
- ✅ Prefer explicit instrumentation with Laravel facades
- ✅ Need custom watchers and middleware
- ✅ Building complex tracing scenarios

### When to Use Official Package Only

- ✅ Want zero-code instrumentation only
- ✅ Need basic request/response tracing
- ✅ Prefer minimal setup

## Installation

You can install the package via composer:

```bash
composer require overtrue/laravel-open-telemetry
```

## Configuration

### Publish Configuration File

```bash
php artisan vendor:publish --provider="Overtrue\LaravelOpenTelemetry\OpenTelemetryServiceProvider" --tag="config"
```

### ⚠️ Important: Environment Variable Configuration

**OpenTelemetry SDK initializes before Laravel**, which means OpenTelemetry environment variables **cannot be set in Laravel's `.env` file**. They must be set as **server environment variables**.

**Reference**: [OpenTelemetry PHP Issue #1436](https://github.com/open-telemetry/opentelemetry-php/issues/1436)

#### ✅ Correct Ways to Set Environment Variables

**Option 1: Server Environment Variables**
```bash
# Set in your server environment
export OTEL_PHP_AUTOLOAD_ENABLED=true
export OTEL_SERVICE_NAME=my-laravel-app
export OTEL_TRACES_EXPORTER=console
```

**Option 2: Docker Environment**
```yaml
# docker-compose.yml
services:
  app:
    environment:
      - OTEL_PHP_AUTOLOAD_ENABLED=true
      - OTEL_SERVICE_NAME=my-laravel-app
      - OTEL_TRACES_EXPORTER=console
```

**Option 3: PHP-FPM Configuration**
```nginx
# In your nginx server block
location ~ \.php$ {
    fastcgi_param OTEL_PHP_AUTOLOAD_ENABLED "true";
    fastcgi_param OTEL_SERVICE_NAME "my-laravel-app";
    fastcgi_param OTEL_TRACES_EXPORTER "console";
    # ... other fastcgi_param directives
}
```

**Option 4: Apache Environment**
```apache
# In your Apache virtual host or .htaccess
SetEnv OTEL_PHP_AUTOLOAD_ENABLED "true"
SetEnv OTEL_SERVICE_NAME "my-laravel-app"
SetEnv OTEL_TRACES_EXPORTER "console"
```

#### ❌ This Will NOT Work

```dotenv
# ❌ These in Laravel's .env file will be IGNORED by OpenTelemetry SDK
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=my-laravel-app
OTEL_TRACES_EXPORTER=console
```

#### 💡 Laravel-Specific Configuration

Laravel-specific configurations (this package's features) **can** be set in `.env` because they are read by Laravel after initialization:

```dotenv
# ✅ These work in Laravel's .env file (this package's configurations)
OTEL_ENABLED=true
OTEL_AUTO_TRACE_REQUESTS=true
OTEL_IGNORE_PATHS=horizon*,telescope*
OTEL_SENSITIVE_HEADERS=authorization,cookie
```

### 📋 Configuration Categories

#### 🟢 OpenTelemetry SDK Configuration (Server Environment Variables)

These **must be set as server environment variables**, not in Laravel's `.env` file:

```bash
# Core OpenTelemetry SDK Configuration - Set as server environment variables
export OTEL_PHP_AUTOLOAD_ENABLED=true              # Enable auto-instrumentation
export OTEL_SERVICE_NAME=my-laravel-app            # Service name for identification
export OTEL_TRACES_EXPORTER=console                # Trace export method
# OR for production
export OTEL_TRACES_EXPORTER=otlp                   # Use OTLP exporter
export OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4318  # OTLP endpoint
```

#### 🟡 Laravel Package Configuration (Laravel .env File)

These **can be set in Laravel's `.env` file** because they are read by this package:

```dotenv
# This Package's Configuration - Can be set in Laravel's .env
OTEL_ENABLED=true                    # Enable/disable this package's functionality
OTEL_SDK_AUTO_INITIALIZE=false      # Auto-initialize SDK fallback (default: false)
OTEL_SERVICE_VERSION=1.0.0          # Service version (default: 1.0.0)
```

#### 🔵 Fully Optional Configuration

These have sensible defaults and work without configuration:

```dotenv
# Request Tracing Control
OTEL_AUTO_TRACE_REQUESTS=true       # Auto-trace HTTP requests (default: true)

# Header Handling
OTEL_ALLOWED_HEADERS=*              # Allowed headers (default: common headers)
OTEL_SENSITIVE_HEADERS=authorization,cookie  # Sensitive headers (default: common sensitive headers)

# Path Filtering
OTEL_IGNORE_PATHS=horizon*,telescope*  # Ignored paths (default: common admin paths)

# Response Headers
OTEL_RESPONSE_TRACE_HEADER_NAME=X-Trace-Id  # Trace ID in response header (default: X-Trace-Id)

# OTLP Detailed Configuration (only needed when using OTLP)
OTEL_EXPORTER_OTLP_HEADERS=         # OTLP headers (default: empty)
OTEL_EXPORTER_OTLP_TIMEOUT=10       # OTLP timeout (default: 10 seconds)

# Other Exporters (usually not needed)
OTEL_METRICS_EXPORTER=none          # Metrics export (default: none)
OTEL_LOGS_EXPORTER=none             # Logs export (default: none)
```

### 🚀 Configuration Examples by Use Case

#### Scenario 1: Quick Start/Testing

**Server Environment Variables:**
```bash
export OTEL_PHP_AUTOLOAD_ENABLED=true
export OTEL_SERVICE_NAME=my-app
export OTEL_TRACES_EXPORTER=console
```

**Laravel .env file:**
```dotenv
OTEL_ENABLED=true
```

#### Scenario 2: Production Environment (External Collector)

**Server Environment Variables:**
```bash
export OTEL_PHP_AUTOLOAD_ENABLED=true
export OTEL_SERVICE_NAME=my-production-app
export OTEL_TRACES_EXPORTER=otlp
export OTEL_EXPORTER_OTLP_ENDPOINT=https://your-collector.com:4318
```

**Laravel .env file:**
```dotenv
OTEL_ENABLED=true
OTEL_SERVICE_VERSION=2.1.0
OTEL_AUTO_TRACE_REQUESTS=true
```

#### Scenario 3: Development Environment (Detailed Configuration)

**Server Environment Variables:**
```bash
export OTEL_PHP_AUTOLOAD_ENABLED=true
export OTEL_SERVICE_NAME=my-dev-app
export OTEL_TRACES_EXPORTER=console
```

**Laravel .env file:**
```dotenv
OTEL_ENABLED=true
OTEL_AUTO_TRACE_REQUESTS=true
OTEL_IGNORE_PATHS=_debugbar*,telescope*,horizon*
OTEL_SENSITIVE_HEADERS=authorization,cookie,x-api-key
```

#### Scenario 4: Disabled/Testing Environment

**Server Environment Variables:**
```bash
# Can leave OpenTelemetry variables unset or set to false
export OTEL_PHP_AUTOLOAD_ENABLED=false
```

**Laravel .env file:**
```dotenv
OTEL_ENABLED=false
```

### Official Auto-Instrumentation Configuration

This package automatically installs `open-telemetry/opentelemetry-auto-laravel` as a dependency. To enable the official auto-instrumentation features:

```dotenv
# Enable PHP auto-instrumentation (requires ext-opentelemetry)
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_PHP_TRACE_CLI_ENABLED=true
OTEL_PROPAGATORS=baggage,tracecontext

# Basic OpenTelemetry configuration (used by both packages)
OTEL_SERVICE_NAME=my-laravel-app
OTEL_TRACES_EXPORTER=console
```

**Note**: The official package provides automatic instrumentation, while this package adds manual instrumentation capabilities on top.

## Usage

### Register the middleware

You can register the middleware in the `app/Http/Kernel.php`:

```php
protected $middleware = [
    \Overtrue\LaravelOpenTelemetry\Middlewares\MeasureRequest::class,
    // ...
];
```

or you can set the env variable `OTEL_AUTO_TRACE_REQUESTS` to `true` to enable it automatically.

### Manual Span Creation

You can create custom spans using the `Overtrue\LaravelOpenTelemetry\Facades\Measure` facade:

#### Simple Span

```php
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

Measure::span('your-span-name')->measure(function() {
    // Your code here
});
```

#### Manual Start/End

```php
$span = Measure::start('your-span-name');

// Your code here

Measure::end('your-span-name');
```

#### With Attributes

```php
Measure::start('your-span-name', function($spanBuilder) {
    $spanBuilder->setAttribute('key', 'value');
    $spanBuilder->setSpanKind(\OpenTelemetry\API\Trace\SpanKind::KIND_CLIENT);
});

// Your code here

Measure::end('your-span-name');
```

#### Direct Span Control

```php
$spanBuilder = Measure::span('your-span-name');
$spanBuilder->setAttribute('key', 'value');
$span = $spanBuilder->start();

// Your code here

$span->end();
```

### Check Tracing Status

```php
// Check if tracing is recording
if (Measure::isRecording()) {
    // Add expensive tracing operations
}

// Get detailed status
$status = Measure::getStatus();
// Returns: ['is_recording' => bool, 'active_spans_count' => int, ...]
```

### Test Tracing Setup

You can test your tracing setup using the command:

```bash
php artisan otel:test
```

This command will:
- Check your OpenTelemetry configuration
- Create test spans with attributes and events
- Display trace information and diagnostics
- Show environment variable status

## Troubleshooting

### NonRecordingSpan Issue

If you see `NonRecordingSpan` or traces not being recorded:

1. **Check OpenTelemetry SDK environment variables (server-level):**
   ```bash
   # These must be server environment variables, NOT in .env
   export OTEL_PHP_AUTOLOAD_ENABLED=true
   export OTEL_SERVICE_NAME=my-app
   export OTEL_TRACES_EXPORTER=console
   ```

2. **Check Laravel package configuration (.env file):**
   ```dotenv
   # These can be in Laravel's .env file
   OTEL_ENABLED=true
   OTEL_SDK_AUTO_INITIALIZE=false
   ```

3. **Run the test command:**
   ```bash
   php artisan otel:test
   ```

4. **Check the output for specific guidance on missing configuration**

### Common Issues

- **Environment variables ignored**: Remember that OpenTelemetry SDK variables **must be server environment variables**, not in Laravel's `.env` file. See [issue #1436](https://github.com/open-telemetry/opentelemetry-php/issues/1436)
- **Works with `php artisan serve` but not with nginx/apache**: This is because `artisan serve` reads `.env` differently. Set OpenTelemetry variables as server environment variables
- **Traces not appearing**: Ensure `OTEL_TRACES_EXPORTER` is set to `console` or `otlp` as a **server environment variable**
- **Performance concerns**: Set `OTEL_ENABLED=false` in Laravel's `.env` file in non-production environments
- **Too many traces**: Use `OTEL_IGNORE_PATHS` in Laravel's `.env` file to filter out unwanted requests

## Advanced Features

### Custom Watchers

Create custom watchers to trace specific events:

```php
use Overtrue\LaravelOpenTelemetry\Watchers\Watcher;
use Illuminate\Contracts\Foundation\Application;

class CustomWatcher implements Watcher
{
    public function register(Application $app): void
    {
        // Register your event listeners
    }
}
```

Then add it to `config/otel.php`:

```php
'watchers' => [
    // ... existing watchers
    App\Watchers\CustomWatcher::class,
],
```

### Context Propagation

```php
// Extract context from headers (useful for incoming requests)
$context = Measure::extractContextFromPropagationHeaders($headers);

// Get propagation headers (useful for outgoing requests)
$headers = Measure::propagationHeaders();
```

## Requirements

- PHP 8.4+
- Laravel 10.0+ | 11.0+
- OpenTelemetry SDK

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

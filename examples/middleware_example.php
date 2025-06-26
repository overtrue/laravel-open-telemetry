<?php

/**
 * Laravel OpenTelemetry Middleware Usage Examples
 *
 * This example demonstrates how to use standard OpenTelemetry environment variables
 * and middleware functionality
 */

// 1. First, configure OpenTelemetry in .env file (using standard environment variables)
/*
# Enable OpenTelemetry PHP SDK auto-loading
OTEL_PHP_AUTOLOAD_ENABLED=true

# Service identification
OTEL_SERVICE_NAME=my-laravel-app
OTEL_SERVICE_VERSION=1.0.0

# Exporter configuration
OTEL_TRACES_EXPORTER=console  # Use console for development, otlp for production
OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4318
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf

# Context propagation
OTEL_PROPAGATORS=tracecontext,baggage

# Laravel package specific configuration
OTEL_TRACE_ID_MIDDLEWARE_ENABLED=true
OTEL_TRACE_ID_MIDDLEWARE_GLOBAL=false
OTEL_TRACE_ID_HEADER_NAME=X-Trace-Id
OTEL_HTTP_CLIENT_PROPAGATION_ENABLED=true
*/

// 2. Publish configuration file (optional)
// php artisan vendor:publish --provider="Overtrue\LaravelOpenTelemetry\OpenTelemetryServiceProvider" --tag=config

// Note: In non-Octane mode, OpenTelemetry root span middleware is automatically enabled globally
// This middleware is registered using prependMiddleware to ensure it executes before all other middleware
// In Octane mode, root spans are automatically created by event handlers
// You don't need to manually add any middleware to create root spans

// 3. Using tracing in controllers
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

class UserController extends Controller
{
    public function index()
    {
        // Create custom span
        $span = Measure::start('user.list');

        try {
            // Add custom attributes
            $span->setAttributes([
                'user.count' => User::count(),
                'request.ip' => request()->ip(),
            ]);

            // Execute business logic
            $users = User::paginate(15);

            // Add events
            $span->addEvent('users.fetched', [
                'count' => $users->count(),
            ]);

            return response()->json($users);

        } catch (\Exception $e) {
            // Record exception
            $span->recordException($e);
            throw $e;
        } finally {
            // End span
            $span->end();
        }
    }

    public function show($id)
    {
        // Use callback approach to create span
        return Measure::start('user.show', function ($span) use ($id) {
            $span->setAttributes(['user.id' => $id]);

            $user = User::findOrFail($id);

            $span->addEvent('user.found', [
                'user.email' => $user->email,
            ]);

            return response()->json($user);
        });
    }
}

// 4. Using nested tracing in service classes
class UserService
{
    public function createUser(array $data)
    {
        return Measure::start('user.create', function ($span) use ($data) {
            $span->setAttributes([
                'user.email' => $data['email'],
            ]);

            // Create nested span
            $validationSpan = Measure::start('user.validate');
            $this->validateUserData($data);
            $validationSpan->end();

            // Another nested span
            $dbSpan = Measure::start('user.save');
            $user = User::create($data);
            $dbSpan->setAttributes(['user.id' => $user->id]);
            $dbSpan->end();

            $span->addEvent('user.created', [
                'user.id' => $user->id,
            ]);

            return $user;
        });
    }

    private function validateUserData(array $data)
    {
        // Validation logic...
    }
}

// 5. Getting current trace information
class ApiController extends Controller
{
    public function status()
    {
        return response()->json([
            'status' => 'ok',
            'trace_id' => Measure::traceId(),
            'timestamp' => now(),
        ]);
    }
}

// 6. Using in middleware
class CustomMiddleware
{
    public function handle($request, Closure $next)
    {
        $span = Measure::start('middleware.custom');
        $span->setAttributes([
            'http.method' => $request->method(),
            'http.url' => $request->fullUrl(),
        ]);

        try {
            $response = $next($request);

            $span->setAttributes([
                'http.status_code' => $response->getStatusCode(),
            ]);

            return $response;
        } finally {
            $span->end();
        }
    }
}

// 7. Production environment configuration example
/*
# Production .env configuration
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=my-production-app
OTEL_SERVICE_VERSION=2.1.0
OTEL_TRACES_EXPORTER=otlp
OTEL_EXPORTER_OTLP_ENDPOINT=https://otel-collector.company.com:4318
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_PROPAGATORS=tracecontext,baggage

# Sampling configuration
OTEL_TRACES_SAMPLER=traceidratio
OTEL_TRACES_SAMPLER_ARG=0.1

# Resource attributes
OTEL_RESOURCE_ATTRIBUTES=service.namespace=production,deployment.environment=prod
*/

// 8. Development environment configuration example
/*
# Development .env configuration
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=my-dev-app
OTEL_TRACES_EXPORTER=console
OTEL_PROPAGATORS=tracecontext,baggage

# Show all traces during development
OTEL_TRACES_SAMPLER=always_on
*/

// 9. Automatic HTTP Client Tracing
/*
With the new automatic HTTP client tracing, all HTTP requests made through Laravel's Http facade
are automatically traced with proper context propagation. No manual configuration needed!

Example:
*/
use Illuminate\Support\Facades\Http;

class ExternalApiService
{
    public function fetchUsers()
    {
        // This request is automatically traced with context propagation
        $response = Http::get('https://api.example.com/users');

        return $response->json();
    }

    public function createUser(array $userData)
    {
        // This POST request is also automatically traced
        $response = Http::post('https://api.example.com/users', $userData);

        return $response->json();
    }
}

// 10. Disabling automatic HTTP client propagation (if needed)
/*
If you need to disable automatic HTTP client propagation for specific scenarios:

In .env:
OTEL_HTTP_CLIENT_PROPAGATION_ENABLED=false

Or in config/otel.php:
'http_client' => [
    'propagation_middleware' => [
        'enabled' => false,
    ],
],
*/

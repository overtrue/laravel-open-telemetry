<?php

/**
 * Improved Measure API Usage Examples
 * Demonstrates more flexible and semantic tracing approaches using OpenTelemetry standard semantic conventions
 */

use Illuminate\Http\Request;
use OpenTelemetry\SemConv\TraceAttributes;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

// ======================= Previous Usage Pattern =======================

// Before: Manually creating and managing spans
$span = Measure::start('user.create');
$span->setAttributes(['user.id' => 123]);
// ... business logic
$span->end();

// ======================= Improved Usage Patterns =======================

// 1. Use trace() method for automatic span lifecycle management
$user = Measure::trace('user.create', function ($span) {
    $span->setAttributes([
        TraceAttributes::ENDUSER_ID => 123,
        'user.action' => 'registration',
    ]);

    // Business logic
    $user = new User;
    $user->save();

    return $user;
}, ['initial.context' => 'registration']);

// 2. Semantic HTTP request tracing
Route::middleware('api')->group(function () {
    Route::get('/users', function (Request $request) {
        // Automatically create HTTP span and set related attributes
        $span = Measure::http($request, function ($spanBuilder) {
            $spanBuilder->setAttributes([
                'user.authenticated' => auth()->check(),
                'api.version' => 'v1',
            ]);
        });

        $users = User::all();
        $span->end();

        return response()->json($users);
    });
});

// 3. Database operation tracing (using standard semantic conventions)
$users = Measure::trace('user.query', function ($span) {
    // Use standard database semantic convention attributes
    $span->setAttributes([
        TraceAttributes::DB_SYSTEM => 'mysql',
        TraceAttributes::DB_NAMESPACE => 'myapp',
        TraceAttributes::DB_COLLECTION_NAME => 'users',
        TraceAttributes::DB_OPERATION_NAME => 'SELECT',
    ]);

    return User::where('active', true)->get();
});

// 4. HTTP client request tracing
$response = Measure::httpClient('GET', 'https://api.example.com/users', function ($spanBuilder) {
    $spanBuilder->setAttributes([
        'api.client' => 'laravel-http',
        'api.timeout' => 30,
    ]);
});

// 5. Queue job processing (using standard messaging semantic conventions)
dispatch(function () {
    Measure::queue('process', 'EmailJob', function ($spanBuilder) {
        $spanBuilder->setAttributes([
            TraceAttributes::MESSAGING_SYSTEM => 'laravel-queue',
            TraceAttributes::MESSAGING_DESTINATION_NAME => 'emails',
            TraceAttributes::MESSAGING_OPERATION_TYPE => 'PROCESS',
        ]);
    });
});

// 6. Redis operation tracing
$value = Measure::redis('GET', function ($spanBuilder) {
    $spanBuilder->setAttributes([
        TraceAttributes::DB_SYSTEM => 'redis',
        TraceAttributes::DB_OPERATION_NAME => 'GET',
        'redis.key' => 'user:123',
    ]);
});

// 7. Cache operation tracing
$user = Measure::cache('get', 'user:123', function ($spanBuilder) {
    $spanBuilder->setAttributes([
        'cache.store' => 'redis',
        'cache.key' => 'user:123',
    ]);
});

// 8. Event recording (using standard event semantic conventions)
Measure::event('user.registered', function ($spanBuilder) {
    $spanBuilder->setAttributes([
        TraceAttributes::EVENT_NAME => 'user.registered',
        TraceAttributes::ENDUSER_ID => 123,
        'event.domain' => 'laravel',
    ]);
});

// 9. Console command tracing
Artisan::command('users:cleanup', function () {
    Measure::command('users:cleanup', function ($spanBuilder) {
        $spanBuilder->setAttributes([
            'console.command' => 'users:cleanup',
            'console.arguments' => '--force',
        ]);
    });
});

// ======================= Exception Handling and Event Recording =======================

try {
    $result = Measure::trace('risky.operation', function ($span) {
        // Operation that might throw an exception
        $span->setAttributes([
            'operation.type' => 'data_processing',
        ]);

        return processData();
    });
} catch (\Exception $e) {
    // Exception will be automatically recorded in the span
    Measure::recordException($e);
}

// Manually add events to current span
Measure::addEvent('checkpoint.reached', [
    'checkpoint.name' => 'data_validation',
    'checkpoint.status' => 'passed',
]);

// ======================= Batch Operation Examples =======================

// Batch database operations
Measure::database('BATCH_INSERT', 'users', function ($spanBuilder) {
    $spanBuilder->setAttributes([
        TraceAttributes::DB_OPERATION_BATCH_SIZE => 100,
        TraceAttributes::DB_SYSTEM => 'mysql',
        'operation.batch' => true,
    ]);
});

// ======================= Performance Monitoring Examples =======================

// Monitor API response time
$users = Measure::trace('api.users.list', function ($span) {
    $span->setAttributes([
        TraceAttributes::HTTP_REQUEST_METHOD => 'GET',
        'api.endpoint' => '/users',
        'performance.monitored' => true,
    ]);

    $startMemory = memory_get_usage();
    $users = User::with('profile')->paginate(50);
    $endMemory = memory_get_usage();

    $span->setAttributes([
        'memory.usage_bytes' => $endMemory - $startMemory,
        'result.count' => $users->count(),
    ]);

    return $users;
});

// ======================= Distributed Tracing Examples =======================

// Propagate trace context between microservices
$headers = Measure::propagationHeaders();

// Include tracing headers when sending HTTP requests
$response = Http::withHeaders($headers)->get('https://service.example.com/api');

// Extract trace context when receiving requests
$context = Measure::extractContextFromPropagationHeaders($request->headers->all());

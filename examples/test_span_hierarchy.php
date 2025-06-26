<?php

/**
 * Span Hierarchy Test Example
 *
 * This example verifies that span chains work correctly in non-Octane mode
 */

require_once __DIR__.'/../vendor/autoload.php';

use OpenTelemetry\API\Trace\SpanKind;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

// Simulate Laravel application initialization
$app = new \Illuminate\Foundation\Application(__DIR__.'/../');
$app->singleton(\Overtrue\LaravelOpenTelemetry\Support\Measure::class, function ($app) {
    return new \Overtrue\LaravelOpenTelemetry\Support\Measure($app);
});

echo "=== Testing Span Hierarchy ===\n\n";

// 1. Create root span (simulate HTTP request)
echo "1. Creating root span\n";
$rootSpan = Measure::startRootSpan('GET /api/users', [
    'http.method' => 'GET',
    'http.url' => '/api/users',
    'span.kind' => 'server',
]);
echo 'Root span ID: '.$rootSpan->getContext()->getSpanId()."\n";
echo 'Trace ID: '.$rootSpan->getContext()->getTraceId()."\n\n";

// 2. Create child span (simulate database query)
echo "2. Creating database query span\n";
$dbSpan = Measure::span('db.query', 'users')
    ->setSpanKind(SpanKind::KIND_CLIENT)
    ->setAttribute('db.statement', 'SELECT * FROM users')
    ->setAttribute('db.operation', 'SELECT')
    ->startAndActivate();

echo 'Database span ID: '.$dbSpan->getSpan()->getContext()->getSpanId()."\n";
echo 'Parent span ID: '.$rootSpan->getContext()->getSpanId()."\n";
echo 'Same Trace ID: '.($dbSpan->getSpan()->getContext()->getTraceId() === $rootSpan->getContext()->getTraceId() ? 'Yes' : 'No')."\n\n";

// 3. Create nested child span (simulate cache operation)
echo "3. Creating cache operation span\n";
$cacheSpan = Measure::span('cache.get', 'users')
    ->setSpanKind(SpanKind::KIND_CLIENT)
    ->setAttribute('cache.key', 'users:all')
    ->setAttribute('cache.operation', 'get')
    ->startAndActivate();

echo 'Cache span ID: '.$cacheSpan->getSpan()->getContext()->getSpanId()."\n";
echo 'Parent span ID: '.$dbSpan->getSpan()->getContext()->getSpanId()."\n";
echo 'Same Trace ID: '.($cacheSpan->getSpan()->getContext()->getTraceId() === $rootSpan->getContext()->getTraceId() ? 'Yes' : 'No')."\n\n";

// 4. End spans in correct order
echo "4. Ending spans\n";
$cacheSpan->end();
echo "Cache span ended\n";

$dbSpan->end();
echo "Database span ended\n";

Measure::endRootSpan();
echo "Root span ended\n\n";

echo "=== Span Hierarchy Test Complete ===\n";
echo "If all spans have the same Trace ID, the span chain is working correctly!\n";

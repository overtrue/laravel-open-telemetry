<?php

/**
 * OpenTelemetry Semantic Conventions Usage Guide
 *
 * This file demonstrates how to properly use standard semantic conventions
 * in the Laravel OpenTelemetry package to ensure compatibility and consistency
 * with other OpenTelemetry implementations
 */

use OpenTelemetry\SemConv\TraceAttributes;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

// ======================= Database Operation Semantic Conventions =======================

// ✅ Correct: Using standard database semantic conventions
Measure::database('SELECT', 'users', function ($spanBuilder) {
    $spanBuilder->setAttributes([
        TraceAttributes::DB_SYSTEM => 'mysql',                    // Database system
        TraceAttributes::DB_NAMESPACE => 'myapp_production',      // Database name
        TraceAttributes::DB_COLLECTION_NAME => 'users',          // Table name
        TraceAttributes::DB_OPERATION_NAME => 'SELECT',          // Operation name
        TraceAttributes::DB_QUERY_TEXT => 'SELECT * FROM users WHERE active = ?', // Query text
    ]);
});

// ❌ Incorrect: Using custom attribute names
Measure::database('SELECT', 'users', function ($spanBuilder) {
    $spanBuilder->setAttributes([
        'database.type' => 'mysql',         // Should use TraceAttributes::DB_SYSTEM
        'db.name' => 'myapp_production',    // Should use TraceAttributes::DB_NAMESPACE
        'table.name' => 'users',            // Should use TraceAttributes::DB_COLLECTION_NAME
    ]);
});

// ======================= HTTP Client Semantic Conventions =======================

// ✅ Correct: Using standard HTTP semantic conventions
Measure::httpClient('GET', 'https://api.example.com/users', function ($spanBuilder) {
    $spanBuilder->setAttributes([
        TraceAttributes::HTTP_REQUEST_METHOD => 'GET',
        TraceAttributes::URL_FULL => 'https://api.example.com/users',
        TraceAttributes::URL_SCHEME => 'https',
        TraceAttributes::SERVER_ADDRESS => 'api.example.com',
        TraceAttributes::SERVER_PORT => 443,
        TraceAttributes::USER_AGENT_ORIGINAL => 'Laravel/9.0 Guzzle/7.0',
    ]);
});

// ❌ Incorrect: Using custom attribute names
Measure::httpClient('GET', 'https://api.example.com/users', function ($spanBuilder) {
    $spanBuilder->setAttributes([
        'http.method' => 'GET',             // Should use TraceAttributes::HTTP_REQUEST_METHOD
        'request.url' => 'https://api.example.com/users', // Should use TraceAttributes::URL_FULL
        'host.name' => 'api.example.com',  // Should use TraceAttributes::SERVER_ADDRESS
    ]);
});

// ======================= Messaging Semantic Conventions =======================

// ✅ Correct: Using standard messaging semantic conventions
Measure::queue('process', 'SendEmailJob', function ($spanBuilder) {
    $spanBuilder->setAttributes([
        TraceAttributes::MESSAGING_SYSTEM => 'laravel-queue',
        TraceAttributes::MESSAGING_DESTINATION_NAME => 'emails',
        TraceAttributes::MESSAGING_OPERATION_TYPE => 'PROCESS',
        TraceAttributes::MESSAGING_MESSAGE_ID => 'msg_12345',
    ]);
});

// ❌ Incorrect: Using custom attribute names
Measure::queue('process', 'SendEmailJob', function ($spanBuilder) {
    $spanBuilder->setAttributes([
        'queue.system' => 'laravel-queue',  // Should use TraceAttributes::MESSAGING_SYSTEM
        'queue.name' => 'emails',           // Should use TraceAttributes::MESSAGING_DESTINATION_NAME
        'job.operation' => 'PROCESS',       // Should use TraceAttributes::MESSAGING_OPERATION_TYPE
    ]);
});

// ======================= Event Semantic Conventions =======================

// ✅ Correct: Using standard event semantic conventions
Measure::event('user.registered', function ($spanBuilder) {
    $spanBuilder->setAttributes([
        TraceAttributes::EVENT_NAME => 'user.registered',
        TraceAttributes::ENDUSER_ID => '123',
        'event.domain' => 'laravel',  // Custom attribute, as no standard is defined
    ]);
});

// ======================= Exception Semantic Conventions =======================

try {
    // Some operation that might fail
    throw new \Exception('Something went wrong');
} catch (\Exception $e) {
    // ✅ Correct: Exceptions automatically use standard semantic conventions
    Measure::recordException($e);

    // When recording manually, also use standard attributes
    Measure::addEvent('exception.occurred', [
        TraceAttributes::EXCEPTION_TYPE => get_class($e),
        TraceAttributes::EXCEPTION_MESSAGE => $e->getMessage(),
        TraceAttributes::CODE_FILEPATH => $e->getFile(),
        TraceAttributes::CODE_LINENO => $e->getLine(),
    ]);
}

// ======================= User Authentication Semantic Conventions =======================

// ✅ Correct: Using standard user semantic conventions
Measure::auth('login', function ($spanBuilder) {
    $spanBuilder->setAttributes([
        TraceAttributes::ENDUSER_ID => auth()->id(),
        TraceAttributes::ENDUSER_ROLE => auth()->user()->role ?? 'user',
        // 'auth.method' => 'password',  // Custom attribute, as no standard is defined
    ]);
});

// ======================= Network Semantic Conventions =======================

// ✅ Correct: Using standard network semantic conventions
$spanBuilder->setAttributes([
    TraceAttributes::NETWORK_PROTOCOL_NAME => 'http',
    TraceAttributes::NETWORK_PROTOCOL_VERSION => '1.1',
    TraceAttributes::NETWORK_PEER_ADDRESS => '192.168.1.1',
    TraceAttributes::NETWORK_PEER_PORT => 8080,
]);

// ======================= Performance Monitoring Semantic Conventions =======================

// ✅ Correct: Setting attributes for performance monitoring
Measure::trace('data.processing', function ($span) {
    $startTime = microtime(true);
    $startMemory = memory_get_usage();

    // Execute data processing
    $result = processLargeDataset();

    $endTime = microtime(true);
    $endMemory = memory_get_usage();

    $span->setAttributes([
        'process.runtime.name' => 'php',
        'process.runtime.version' => PHP_VERSION,
        'performance.duration_ms' => ($endTime - $startTime) * 1000,
        'performance.memory_usage_bytes' => $endMemory - $startMemory,
        'data.records_processed' => count($result),
    ]);

    return $result;
});

// ======================= Cache Operations (No Standard Semantic Conventions Yet) =======================

// 📝 Note: Cache operations currently have no standard OpenTelemetry semantic conventions
// We use consistent custom attribute names, awaiting standardization
Measure::cache('get', 'user:123', function ($spanBuilder) {
    $spanBuilder->setAttributes([
        'cache.operation' => 'GET',
        'cache.key' => 'user:123',
        'cache.store' => 'redis',
        'cache.hit' => true,
        'cache.ttl' => 3600,
    ]);
});

// ======================= Best Practices Summary =======================

/**
 * 🎯 Semantic Conventions Usage Best Practices:
 *
 * 1. Prioritize Standard Semantic Conventions
 *    - Always use predefined constants from OpenTelemetry\SemConv\TraceAttributes
 *    - Ensure attribute names and values comply with OpenTelemetry specifications
 *
 * 2. Custom Attribute Naming Standards
 *    - When no standard semantic conventions exist, use descriptive attribute names
 *    - Follow the "namespace.attribute" naming pattern
 *    - Avoid conflicts with existing standard attributes
 *
 * 3. Attribute Value Standardization
 *    - Use standard enumerated values (e.g., HTTP method names in uppercase)
 *    - Maintain consistency and comparability of attribute values
 *    - Avoid including sensitive information
 *
 * 4. Backward Compatibility
 *    - Update promptly when OpenTelemetry releases new semantic conventions
 *    - Maintain stability of existing custom attributes
 *
 * 5. Document Custom Attributes
 *    - Write documentation for project-specific attributes
 *    - Ensure team members understand attribute meanings and purposes
 */

// ======================= Common Errors and Corrections =======================

// ❌ Incorrect: Using deprecated attribute names
$spanBuilder->setAttributes([
    'http.method' => 'GET',                    // Deprecated
    'http.url' => 'https://example.com',       // Deprecated
]);

// ✅ Correct: Using current standard attributes
$spanBuilder->setAttributes([
    TraceAttributes::HTTP_REQUEST_METHOD => 'GET',      // Current standard
    TraceAttributes::URL_FULL => 'https://example.com', // Current standard
]);

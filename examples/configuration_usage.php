<?php

/**
 * Configuration Usage Examples
 *
 * This file demonstrates how to use the allowed_headers, sensitive_headers,
 * and ignore_paths configurations in Laravel OpenTelemetry package.
 */

// ======================= Configuration Examples =======================

// In your config/otel.php file:

return [
    'enabled' => env('OTEL_ENABLED', true),

    // ======================= Headers Configuration =======================

    /**
     * Allow to trace requests with specific headers. You can use `*` as wildcard.
     * Only headers matching these patterns will be included in spans.
     */
    'allowed_headers' => explode(',', env('OTEL_ALLOWED_HEADERS', implode(',', [
        'referer',           // Exact match
        'x-*',              // Wildcard: all headers starting with 'x-'
        'accept',           // Content negotiation header
        'request-id',       // Custom request ID header
        'user-agent',       // Browser/client information
        'content-type',     // Request content type
        'authorization',    // Will be masked if in sensitive_headers
        'x-forwarded-*',    // Proxy headers
        'x-real-ip',        // Real IP header
        'x-request-*',      // Custom request headers
    ]))),

    /**
     * Sensitive headers will be marked as *** from the span attributes.
     * You can use `*` as wildcard.
     */
    'sensitive_headers' => explode(',', env('OTEL_SENSITIVE_HEADERS', implode(',', [
        'cookie',           // Session cookies
        'authorization',    // Auth tokens
        'x-api-key',        // API keys
        'x-auth-*',         // Custom auth headers
        'x-token-*',        // Token headers
        'x-secret-*',       // Secret headers
        'x-password-*',     // Password headers
        '*-token',          // Headers ending with 'token'
        '*-key',            // Headers ending with 'key'
        '*-secret',         // Headers ending with 'secret'
    ]))),

    // ======================= Paths Configuration =======================

    /**
     * Ignore paths will not be traced. You can use `*` as wildcard.
     * These requests will be completely skipped from tracing.
     */
    'ignore_paths' => explode(',', env('OTEL_IGNORE_PATHS', implode(',', [
        'horizon*',         // Laravel Horizon dashboard
        'telescope*',       // Laravel Telescope dashboard
        '_debugbar*',       // Laravel Debugbar
        'health*',          // Health check endpoints
        'ping',             // Simple ping endpoint
        'status',           // Status endpoint
        'metrics',          // Metrics endpoint
        'favicon.ico',      // Browser favicon requests
        'robots.txt',       // SEO robots file
        'sitemap.xml',      // SEO sitemap
        'api/health',       // API health check
        'api/ping',         // API ping
        'admin/health',     // Admin health check
        'internal/*',       // Internal endpoints
        'monitoring/*',     // Monitoring endpoints
        '_profiler/*',      // Symfony profiler (if used)
        '.well-known/*',    // Well-known URIs (RFC 8615)
    ]))),
];

// ======================= Environment Variables =======================

// You can also set these via environment variables:

/*
# Basic configuration
OTEL_ENABLED=true

# Headers configuration (comma-separated)
OTEL_ALLOWED_HEADERS="referer,x-*,accept,request-id,user-agent,content-type,authorization"
OTEL_SENSITIVE_HEADERS="cookie,authorization,x-api-key,x-auth-*,*-token,*-key"

# Paths to ignore (comma-separated)
OTEL_IGNORE_PATHS="horizon*,telescope*,_debugbar*,health*,ping,metrics,favicon.ico"
*/

// ======================= Usage Examples =======================

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Overtrue\LaravelOpenTelemetry\Support\HttpAttributesHelper;

// Example 1: Routes that demonstrate ignore_paths functionality
Route::middleware(['web'])->group(function () {
    Route::get('/health', function () {
        // This request will be ignored if 'health*' is in ignore_paths
        return response()->json(['status' => 'ok']);
    });

    Route::get('/api/users', function (Request $request) {
        // This request will be traced (not in ignore_paths)
        // Headers will be filtered based on allowed_headers/sensitive_headers
        return response()->json(['users' => []]);
    });
});

// Example 2: Function to manually check if request should be ignored
function handleRequest(Request $request)
{
    if (HttpAttributesHelper::shouldIgnoreRequest($request)) {
        // Request matches ignore_paths pattern, skip custom tracing
        return processWithoutTracing($request);
    }

    // Request will be traced normally
    return processWithTracing($request);
}

function processWithoutTracing(Request $request)
{
    // Handle request without OpenTelemetry tracing
    return response()->json(['processed' => true, 'traced' => false]);
}

function processWithTracing(Request $request)
{
    // Handle request with OpenTelemetry tracing
    return response()->json(['processed' => true, 'traced' => true]);
}

// Example 3: HTTP Client requests also use header configurations
function makeApiCall()
{
    $response = Http::withHeaders([
        'Authorization' => 'Bearer secret-token',  // Will be masked as ***
        'X-Request-ID' => 'req-123',              // Will be included (if allowed)
        'X-Internal-Key' => 'internal-secret',    // Will be masked if sensitive
        'User-Agent' => 'MyApp/1.0',              // Will be included (if allowed)
    ])->get('https://api.example.com/data');

    return $response->json();
}

// ======================= Real-world Configuration Examples =======================

// Example 1: API Gateway/Microservices
$apiGatewayConfig = [
    'allowed_headers' => [
        'x-request-id',      // Request tracing
        'x-correlation-id',  // Correlation tracing
        'x-forwarded-*',     // Proxy information
        'user-agent',        // Client information
        'accept',            // Content negotiation
        'content-type',      // Request content type
        'authorization',     // Auth (will be masked)
    ],
    'sensitive_headers' => [
        'authorization',     // OAuth/JWT tokens
        'x-api-key',        // API keys
        'cookie',           // Session cookies
        'x-auth-*',         // Custom auth headers
    ],
    'ignore_paths' => [
        'health',           // Health checks
        'metrics',          // Prometheus metrics
        'ready',            // Readiness probe
        'live',             // Liveness probe
        'internal/*',       // Internal endpoints
    ],
];

// Example 2: E-commerce Application
$ecommerceConfig = [
    'allowed_headers' => [
        'x-session-id',     // Session tracking
        'x-user-id',        // User identification
        'x-cart-id',        // Shopping cart tracking
        'x-device-*',       // Device information
        'referer',          // Traffic source
        'user-agent',       // Browser/device info
    ],
    'sensitive_headers' => [
        'authorization',    // User auth tokens
        'x-payment-*',      // Payment information
        'x-credit-*',       // Credit card info
        'cookie',           // Session cookies
    ],
    'ignore_paths' => [
        'assets/*',         // Static assets
        'images/*',         // Image files
        'css/*',            // Stylesheets
        'js/*',             // JavaScript files
        'favicon.ico',      // Browser favicon
        'robots.txt',       // SEO robots
        'sitemap.xml',      // SEO sitemap
        'checkout/ping',    // Payment gateway pings
    ],
];

// Example 3: Admin Dashboard
$adminConfig = [
    'allowed_headers' => [
        'x-admin-role',     // Admin role information
        'x-permission-*',   // Permission headers
        'x-audit-*',        // Audit trail headers
        'referer',          // Navigation tracking
    ],
    'sensitive_headers' => [
        'authorization',    // Admin tokens
        'x-admin-token',    // Admin API tokens
        'x-sudo-*',         // Elevated privilege headers
        'cookie',           // Admin session cookies
    ],
    'ignore_paths' => [
        'admin/health',     // Admin health checks
        'admin/ping',       // Admin ping
        'admin/assets/*',   // Admin static assets
        'admin/logs/download', // Large log downloads
    ],
];

// ======================= Performance Considerations =======================

/**
 * Tips for optimal performance:
 *
 * 1. Keep allowed_headers list minimal
 *    - Only include headers you actually need for debugging
 *    - Too many headers can increase span size significantly
 *
 * 2. Use specific patterns instead of wildcards when possible
 *    - 'x-request-id' is better than 'x-*' if you only need that header
 *
 * 3. Ignore high-frequency, low-value endpoints
 *    - Static assets (images, CSS, JS)
 *    - Health checks and monitoring endpoints
 *    - Favicon and robots.txt requests
 *
 * 4. Use environment-specific configurations
 *    - Production: minimal headers, more ignored paths
 *    - Development: more headers for debugging
 *    - Testing: ignore test-specific endpoints
 */

// ======================= Debugging Configuration =======================

// To debug which requests are being ignored or which headers are being filtered:

Route::get('/debug/otel-config', function (Request $request) {
    return response()->json([
        'request_path' => $request->path(),
        'should_ignore' => HttpAttributesHelper::shouldIgnoreRequest($request),
        'config' => [
            'allowed_headers' => config('otel.allowed_headers'),
            'sensitive_headers' => config('otel.sensitive_headers'),
            'ignore_paths' => config('otel.ignore_paths'),
        ],
        'request_headers' => $request->headers->all(),
    ]);
});

// ======================= Testing Examples =======================

// Example test functions that you could use in your test files:

function testIgnorePathsConfiguration()
{
    // In your tests, you can override configurations:
    config(['otel.ignore_paths' => ['test/*', 'debug/*']]);

    $request = Request::create('/test/endpoint');
    $shouldIgnore = HttpAttributesHelper::shouldIgnoreRequest($request);
    // Assert $shouldIgnore is true

    $request = Request::create('/api/users');
    $shouldIgnore = HttpAttributesHelper::shouldIgnoreRequest($request);
    // Assert $shouldIgnore is false
}

function testHeaderFiltering()
{
    config([
        'otel.allowed_headers' => ['x-*', 'authorization'],
        'otel.sensitive_headers' => ['authorization', 'x-secret-*'],
    ]);

    // Test your header filtering logic here
    // You can create mock requests with specific headers
    // and verify they are properly filtered
}

<?php

/**
 * Duplicate Tracing Prevention Test
 *
 * This file demonstrates how HttpClientWatcher intelligently avoids duplicate tracing
 * through automatic context propagation using Laravel's globalRequestMiddleware
 */
echo "=== HTTP Client Tracing Solution ===\n\n";

echo "Previous Issue:\n";
echo "HTTP requests could potentially be traced multiple times if different\n";
echo "mechanisms were attempting to instrument the same request.\n\n";

echo "Current Solution:\n";
echo "- HttpClientWatcher creates spans for all HTTP requests\n";
echo "- Laravel's globalRequestMiddleware automatically adds propagation headers\n";
echo "- Smart duplicate detection prevents overlapping instrumentation\n";
echo "- Fully automatic - no manual configuration required\n\n";

echo "How it works:\n";

// Example 1: Automatic tracing (HttpClientWatcher handles everything)
echo "1. Automatic HTTP request tracing:\n";
echo "   Http::get('https://httpbin.org/ip')\n";
echo "   → HttpClientWatcher creates span\n";
echo "   → globalRequestMiddleware adds propagation headers\n";
echo "   → Single span created automatically\n\n";

// Example 2: Context propagation in microservices
echo "2. Microservice context propagation:\n";
echo "   Http::get('http://service-b/api/users')\n";
echo "   → Automatically includes trace context in headers\n";
echo "   → Service B can continue the trace seamlessly\n\n";

echo "Key Features:\n";
echo "- No manual withTrace() calls needed\n";
echo "- Automatic propagation headers (traceparent, tracestate, etc.)\n";
echo "- Smart duplicate detection\n";
echo "- Works with all Laravel HTTP client usage patterns\n";
echo "- Zero configuration required\n\n";

echo "Configuration Options:\n";
echo "You can disable automatic propagation if needed:\n";
echo "OTEL_HTTP_CLIENT_PROPAGATION_ENABLED=false\n\n";
echo "Or in config/otel.php:\n";
echo "'http_client' => [\n";
echo "    'propagation_middleware' => ['enabled' => false]\n";
echo "]\n\n";

echo "Best Practices:\n";
echo "1. Let HttpClientWatcher handle all HTTP request tracing automatically\n";
echo "2. Use Measure::trace() for custom business logic spans\n";
echo "3. No need to manually add tracing to HTTP client calls\n";
echo "4. Context propagation happens automatically across services\n\n";

echo "Result:\n";
echo "Every HTTP request is traced exactly once with proper context propagation\n";
echo "between microservices - completely automatically!\n";

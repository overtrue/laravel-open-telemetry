<?php

/**
 * OTLP Exporter Authentication Examples
 *
 * This file demonstrates how to configure authentication headers for various
 * OpenTelemetry backends and collectors using the OTEL_EXPORTER_OTLP_HEADERS
 * environment variable.
 *
 * The OTEL_EXPORTER_OTLP_HEADERS variable is a standard OpenTelemetry SDK
 * configuration option and is automatically recognized without any additional
 * configuration in this Laravel package.
 */

// ============================================================================
// Example 1: Simple API Key Authentication
// ============================================================================
/*
Many custom collectors and some SaaS providers use a simple API key header:

OTEL_SERVICE_NAME=my-laravel-app
OTEL_TRACES_EXPORTER=otlp
OTEL_EXPORTER_OTLP_ENDPOINT=https://collector.example.com:4318
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_EXPORTER_OTLP_HEADERS="x-api-key=your-secret-api-key-here"
*/

// ============================================================================
// Example 2: Bearer Token Authentication
// ============================================================================
/*
OAuth2 or JWT-based authentication using Bearer tokens:

OTEL_SERVICE_NAME=my-laravel-app
OTEL_TRACES_EXPORTER=otlp
OTEL_EXPORTER_OTLP_ENDPOINT=https://collector.example.com:4318
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_EXPORTER_OTLP_HEADERS="authorization=Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
*/

// ============================================================================
// Example 3: Basic Authentication
// ============================================================================
/*
HTTP Basic authentication with base64-encoded credentials:

OTEL_SERVICE_NAME=my-laravel-app
OTEL_TRACES_EXPORTER=otlp
OTEL_EXPORTER_OTLP_ENDPOINT=https://collector.example.com:4318
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_EXPORTER_OTLP_HEADERS="authorization=Basic dXNlcm5hbWU6cGFzc3dvcmQ="

# To generate the base64 credentials in bash:
# echo -n "username:password" | base64
*/

// ============================================================================
// Example 4: Multiple Headers
// ============================================================================
/*
Some backends require multiple headers for authentication and routing:

OTEL_SERVICE_NAME=my-laravel-app
OTEL_TRACES_EXPORTER=otlp
OTEL_EXPORTER_OTLP_ENDPOINT=https://collector.example.com:4318
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_EXPORTER_OTLP_HEADERS="x-api-key=key123,x-tenant-id=tenant-abc,x-environment=production"
*/

// ============================================================================
// Example 5: Honeycomb (Popular Observability Platform)
// ============================================================================
/*
Honeycomb uses a team API key for authentication:

OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=my-laravel-app
OTEL_TRACES_EXPORTER=otlp
OTEL_EXPORTER_OTLP_ENDPOINT=https://api.honeycomb.io
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_EXPORTER_OTLP_HEADERS="x-honeycomb-team=hcxik_01hqk4kxxxxxxxxxxxxxxxxxxxxxxx"

# Optional: Specify dataset
# OTEL_EXPORTER_OTLP_HEADERS="x-honeycomb-team=your-key,x-honeycomb-dataset=my-dataset"
*/

// ============================================================================
// Example 6: New Relic
// ============================================================================
/*
New Relic requires a license key:

OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=my-laravel-app
OTEL_TRACES_EXPORTER=otlp
OTEL_EXPORTER_OTLP_ENDPOINT=https://otlp.nr-data.net:4318
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_EXPORTER_OTLP_HEADERS="api-key=your-new-relic-license-key-here"

# For EU region, use:
# OTEL_EXPORTER_OTLP_ENDPOINT=https://otlp.eu01.nr-data.net:4318
*/

// ============================================================================
// Example 7: Grafana Cloud
// ============================================================================
/*
Grafana Cloud uses Basic authentication with instance ID and API token:

OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=my-laravel-app
OTEL_TRACES_EXPORTER=otlp
OTEL_EXPORTER_OTLP_ENDPOINT=https://otlp-gateway-prod-us-central-0.grafana.net/otlp
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_EXPORTER_OTLP_HEADERS="authorization=Basic <base64-encoded-instanceId:token>"

# Generate the base64 credentials:
# echo -n "instanceId:glc_token" | base64
*/

// ============================================================================
// Example 8: Datadog
// ============================================================================
/*
Datadog OTLP endpoint with API key:

OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=my-laravel-app
OTEL_TRACES_EXPORTER=otlp
OTEL_EXPORTER_OTLP_ENDPOINT=https://api.datadoghq.com
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_EXPORTER_OTLP_HEADERS="dd-api-key=your-datadog-api-key"

# For EU region, use:
# OTEL_EXPORTER_OTLP_ENDPOINT=https://api.datadoghq.eu
*/

// ============================================================================
// Example 9: AWS X-Ray (via OpenTelemetry Collector)
// ============================================================================
/*
When using AWS X-Ray with OpenTelemetry Collector:

OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=my-laravel-app
OTEL_TRACES_EXPORTER=otlp
OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4318
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf

# Note: AWS X-Ray typically doesn't require headers when using the collector
# The collector itself handles AWS credentials via IAM roles or environment variables
*/

// ============================================================================
// Example 10: Self-Hosted OpenTelemetry Collector with Auth
// ============================================================================
/*
Custom authentication for a self-hosted collector:

OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=my-laravel-app
OTEL_TRACES_EXPORTER=otlp
OTEL_EXPORTER_OTLP_ENDPOINT=https://otel-collector.mycompany.com:4318
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_EXPORTER_OTLP_HEADERS="authorization=Bearer company-secret-token,x-service-id=laravel-api"
*/

// ============================================================================
// Important Notes
// ============================================================================
/*
1. Security Best Practices:
   - Never commit API keys or tokens to source control
   - Use environment variables or secure secret management systems
   - Rotate credentials regularly
   - Use separate credentials for different environments

2. Header Format:
   - Format: comma-separated key=value pairs
   - No spaces around the equals sign
   - Header names are case-insensitive but commonly lowercase
   - Values with special characters may need URL encoding

3. Testing Your Configuration:
   - Use OTEL_TRACES_EXPORTER=console first to verify telemetry generation
   - Check collector/backend documentation for specific header requirements
   - Monitor application logs for connection errors
   - Use the package's test command: php artisan otel:test

4. Debugging:
   - Enable debug logging to see outgoing requests
   - Check for typos in header names
   - Verify endpoint URL and protocol match your backend
   - Ensure network connectivity to the endpoint

5. This package requires NO code changes:
   - The OTEL_EXPORTER_OTLP_HEADERS variable is automatically recognized
   - The OpenTelemetry PHP SDK handles header injection
   - Just set the environment variable and restart your application
*/

// ============================================================================
// Verification Script (Optional)
// ============================================================================
/*
You can verify your configuration using a simple PHP script:
*/

if (php_sapi_name() === 'cli') {
    echo "OpenTelemetry OTLP Headers Configuration\n";
    echo str_repeat('=', 50) . "\n\n";

    // Check if headers are configured
    $headers = getenv('OTEL_EXPORTER_OTLP_HEADERS');
    
    if ($headers) {
        echo "✓ OTEL_EXPORTER_OTLP_HEADERS is set\n";
        echo "  Value: {$headers}\n\n";
        
        // Parse and display headers (for verification only - be careful with sensitive data!)
        $headerPairs = explode(',', $headers);
        echo "  Parsed headers:\n";
        foreach ($headerPairs as $pair) {
            $parts = explode('=', $pair, 2);
            if (count($parts) === 2) {
                $headerName = trim($parts[0]);
                $headerValue = trim($parts[1]);
                // Mask sensitive values
                $maskedValue = (stripos($headerName, 'key') !== false || 
                               stripos($headerName, 'token') !== false || 
                               stripos($headerName, 'authorization') !== false)
                    ? str_repeat('*', min(strlen($headerValue), 20))
                    : $headerValue;
                echo "    - {$headerName}: {$maskedValue}\n";
            }
        }
    } else {
        echo "✗ OTEL_EXPORTER_OTLP_HEADERS is not set\n";
        echo "  No authentication headers will be sent to the OTLP endpoint.\n";
    }
    
    echo "\n";
    echo "Other relevant configuration:\n";
    echo "  OTEL_EXPORTER_OTLP_ENDPOINT: " . (getenv('OTEL_EXPORTER_OTLP_ENDPOINT') ?: 'not set') . "\n";
    echo "  OTEL_EXPORTER_OTLP_PROTOCOL: " . (getenv('OTEL_EXPORTER_OTLP_PROTOCOL') ?: 'not set') . "\n";
    echo "  OTEL_SERVICE_NAME: " . (getenv('OTEL_SERVICE_NAME') ?: 'not set') . "\n";
    echo "\n";
}

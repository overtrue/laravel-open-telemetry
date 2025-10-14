# Authentication Headers Support for OTLP Exporters

This document addresses [Issue #6](https://github.com/overtrue/laravel-open-telemetry/issues/6) regarding adding authentication headers to OTLP endpoints.

## Solution

The OpenTelemetry PHP SDK already supports the standard `OTEL_EXPORTER_OTLP_HEADERS` environment variable for adding custom headers to OTLP exporters. **No code changes are required** - you just need to set this environment variable.

## How to Use

Add the following to your `.env` file:

```env
OTEL_EXPORTER_OTLP_HEADERS="x-api-key=your-api-key,authorization=Bearer your-token"
```

## Examples

### Simple API Key
```env
OTEL_EXPORTER_OTLP_HEADERS="x-api-key=your-secret-api-key"
```

### Bearer Token
```env
OTEL_EXPORTER_OTLP_HEADERS="authorization=Bearer your-token-here"
```

### Multiple Headers
```env
OTEL_EXPORTER_OTLP_HEADERS="x-api-key=key123,x-tenant-id=tenant456"
```

### Common SaaS Providers

#### Honeycomb
```env
OTEL_EXPORTER_OTLP_ENDPOINT=https://api.honeycomb.io
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_EXPORTER_OTLP_HEADERS="x-honeycomb-team=your-api-key"
```

#### New Relic
```env
OTEL_EXPORTER_OTLP_ENDPOINT=https://otlp.nr-data.net:4318
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_EXPORTER_OTLP_HEADERS="api-key=your-new-relic-license-key"
```

#### Grafana Cloud
```env
OTEL_EXPORTER_OTLP_ENDPOINT=https://otlp-gateway-prod-us-central-0.grafana.net/otlp
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_EXPORTER_OTLP_HEADERS="authorization=Basic base64-encoded-credentials"
```

## Header Format

- Format: comma-separated `key=value` pairs
- No spaces around the equals sign
- Header names are case-insensitive
- Values with special characters may need URL encoding

## Verification

You can verify your configuration by running:

```bash
php examples/authentication_example.php
```

Or set environment variables inline:

```bash
OTEL_EXPORTER_OTLP_HEADERS="x-api-key=test" php examples/authentication_example.php
```

## Documentation

Complete documentation has been added to:
- `README.md` - Environment variables reference and authentication section
- `config/otel.php` - Comments explaining SDK configuration
- `examples/middleware_example.php` - Real-world usage examples
- `examples/authentication_example.php` - Comprehensive authentication guide

## Important Notes

1. **Security**: Never commit API keys or tokens to source control
2. **Environment-Specific**: Use different credentials for dev/staging/production
3. **No Code Changes**: This is handled by the OpenTelemetry PHP SDK automatically
4. **Standard Variable**: `OTEL_EXPORTER_OTLP_HEADERS` is part of the OpenTelemetry specification

## References

- [OpenTelemetry Environment Variable Specification](https://opentelemetry.io/docs/specs/otel/configuration/sdk-environment-variables/)
- [OTLP Exporter Configuration](https://opentelemetry.io/docs/specs/otel/protocol/exporter/)

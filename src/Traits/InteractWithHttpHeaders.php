<?php

declare(strict_types=1);

namespace Overtrue\LaravelOpenTelemetry\Traits;

use OpenTelemetry\API\Trace\SpanInterface;

trait InteractWithHttpHeaders
{
    /**
     * Normalize headers for consistent processing.
     */
    protected function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $key => $value) {
            $key = strtolower($key);
            $normalized[$key] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        return $normalized;
    }

    /**
     * Record allowed headers as span attributes.
     */
    protected function recordHeaders(SpanInterface $span, array $headers, string $prefix = 'http.request.header.'): void
    {
        $normalized = $this->normalizeHeaders($headers);
        $allowedHeaders = config('otel.allowed_headers', []);
        $sensitiveHeaders = config('otel.sensitive_headers', []);

        foreach ($normalized as $key => $value) {
            if ($this->headerIsAllowed($key, $allowedHeaders)) {
                $attributeKey = $prefix.$key;

                if ($this->headerIsSensitive($key, $sensitiveHeaders)) {
                    $span->setAttribute($attributeKey, '***');
                } else {
                    $span->setAttribute($attributeKey, $value);
                }
            }
        }
    }

    /**
     * Check if header is allowed.
     */
    protected function headerIsAllowed(string $header, array $allowedHeaders): bool
    {
        return array_any($allowedHeaders, fn ($pattern) => fnmatch($pattern, $header));
    }

    /**
     * Check if header is sensitive.
     */
    protected function headerIsSensitive(string $header, array $sensitiveHeaders): bool
    {
        return array_any($sensitiveHeaders, fn ($pattern) => fnmatch($pattern, $header));
    }
}

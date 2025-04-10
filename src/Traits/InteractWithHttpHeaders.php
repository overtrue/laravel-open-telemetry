<?php

namespace Overtrue\LaravelOpenTelemetry\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use OpenTelemetry\API\Trace\SpanInterface;
use Symfony\Component\HttpFoundation\Response;

trait InteractWithHttpHeaders
{
    /**
     * @var array<string>
     */
    protected array $defaultSensitiveHeaders = [
        'authorization',
        'cookie',
        'set-cookie',
    ];

    /**
     * @var array<string>
     */
    protected static array $allowedHeaders = [];

    /**
     * @var array<string>
     */
    protected static array $sensitiveHeaders = [];

    /**
     * @return array<string>
     */
    public static function getAllowedHeaders(): array
    {
        return static::$allowedHeaders;
    }

    public static function headerIsAllowed(string $header): bool
    {
        return Str::is(static::getAllowedHeaders(), $header);
    }

    /**
     * @return array<string>
     */
    public static function getSensitiveHeaders(): array
    {
        return static::$sensitiveHeaders;
    }

    public static function headerIsSensitive(string $header): bool
    {
        return Str::is(static::getSensitiveHeaders(), $header);
    }

    protected function normalizeHeaders(array $headers): array
    {
        return Arr::map(
            $headers,
            fn (string $header) => strtolower(trim($header)),
        );
    }

    protected function recordHeaders(SpanInterface $span, Request|Response $http): SpanInterface
    {
        $prefix = match (true) {
            $http instanceof Request => 'http.request.header.',
            $http instanceof Response => 'http.response.header.',
        };

        foreach ($http->headers->all() as $key => $value) {
            $key = strtolower($key);

            if (! static::headerIsAllowed($key)) {
                continue;
            }

            $value = static::headerIsSensitive($key) ? ['*****'] : $value;

            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            $span->setAttribute($prefix.$key, $value);
        }

        return $span;
    }
}

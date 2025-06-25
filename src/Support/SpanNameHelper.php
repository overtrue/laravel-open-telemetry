<?php

namespace Overtrue\LaravelOpenTelemetry\Support;

use Illuminate\Http\Request;

class SpanNameHelper
{
    /**
     * Generate span name for HTTP requests
     * Format: HTTP {METHOD} {route pattern or path}
     */
    public static function http(Request $request): string
    {
        $route = HttpAttributesHelper::getRouteUri($request);
        if ($route && $route !== $request->path()) {
            // Use route pattern, more intuitive
            return sprintf('HTTP %s %s', $request->method(), $route);
        }

        // Fallback to actual path
        return sprintf('HTTP %s /%s', $request->method(), $request->path());
    }

    /**
     * Generate span name for HTTP client requests
     * Format: HTTP {METHOD} {hostname}{path}
     */
    public static function httpClient(string $method, string $url): string
    {
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'] ?? 'unknown';
        $path = $parsedUrl['path'] ?? '/';

        return sprintf('HTTP %s %s%s', strtoupper($method), $host, $path);
    }

    /**
     * Generate span name for database queries
     * Format: DB {operation} {table}
     */
    public static function database(string $operation, ?string $table = null): string
    {
        if ($table) {
            return sprintf('DB %s %s', strtoupper($operation), $table);
        }

        return sprintf('DB %s', strtoupper($operation));
    }

    /**
     * Generate span name for Redis commands
     * Format: REDIS {command}
     */
    public static function redis(string $command): string
    {
        return sprintf('REDIS %s', strtoupper($command));
    }

    /**
     * Generate span name for queue jobs
     * Format: QUEUE {operation} {job class name}
     */
    public static function queue(string $operation, ?string $jobClass = null): string
    {
        if ($jobClass) {
            // Extract class name (remove namespace)
            $className = class_basename($jobClass);
            return sprintf('QUEUE %s %s', strtoupper($operation), $className);
        }

        return sprintf('QUEUE %s', strtoupper($operation));
    }

    /**
     * Generate span name for authentication operations
     * Format: AUTH {operation}
     */
    public static function auth(string $operation): string
    {
        return sprintf('AUTH %s', strtoupper($operation));
    }

    /**
     * Generate span name for cache operations
     * Format: CACHE {operation} {key}
     */
    public static function cache(string $operation, ?string $key = null): string
    {
        if ($key) {
            // Limit key length to avoid overly long span names
            $shortKey = strlen($key) > 50 ? substr($key, 0, 47) . '...' : $key;
            return sprintf('CACHE %s %s', strtoupper($operation), $shortKey);
        }

        return sprintf('CACHE %s', strtoupper($operation));
    }

    /**
     * Generate span name for events
     * Format: EVENT {event name}
     */
    public static function event(string $eventName): string
    {
        // Simplify event name, remove namespace prefix
        $shortEventName = str_replace(['Illuminate\\', 'App\\Events\\'], '', $eventName);
        return sprintf('EVENT %s', $shortEventName);
    }

    /**
     * Generate span name for exception handling
     * Format: EXCEPTION {exception class name}
     */
    public static function exception(string $exceptionClass): string
    {
        $className = class_basename($exceptionClass);
        return sprintf('EXCEPTION %s', $className);
    }

    /**
     * Generate span name for console commands
     * Format: COMMAND {command name}
     */
    public static function command(string $commandName): string
    {
        return sprintf('COMMAND %s', $commandName);
    }
}

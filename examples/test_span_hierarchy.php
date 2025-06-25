<?php

/**
 * 测试 Span 层次结构示例
 *
 * 此示例用于验证在非 Octane 模式下 span 链是否正常工作
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Overtrue\LaravelOpenTelemetry\Facades\Measure;
use OpenTelemetry\API\Trace\SpanKind;

// 模拟 Laravel 应用初始化
$app = new \Illuminate\Foundation\Application(__DIR__ . '/../');
$app->singleton(\Overtrue\LaravelOpenTelemetry\Support\Measure::class, function ($app) {
    return new \Overtrue\LaravelOpenTelemetry\Support\Measure($app);
});

echo "=== 测试 Span 层次结构 ===\n\n";

// 1. 创建根 span（模拟 HTTP 请求）
echo "1. 创建根 span\n";
$rootSpan = Measure::startRootSpan('GET /api/users', [
    'http.method' => 'GET',
    'http.url' => '/api/users',
    'span.kind' => 'server'
]);
echo "根 span ID: " . $rootSpan->getContext()->getSpanId() . "\n";
echo "Trace ID: " . $rootSpan->getContext()->getTraceId() . "\n\n";

// 2. 创建子 span（模拟数据库查询）
echo "2. 创建数据库查询 span\n";
$dbSpan = Measure::span('db.query', 'users')
    ->setSpanKind(SpanKind::KIND_CLIENT)
    ->setAttribute('db.statement', 'SELECT * FROM users')
    ->setAttribute('db.operation', 'SELECT')
    ->start();

echo "数据库 span ID: " . $dbSpan->getSpan()->getContext()->getSpanId() . "\n";
echo "父 span ID: " . $rootSpan->getContext()->getSpanId() . "\n";
echo "同一个 Trace ID: " . ($dbSpan->getSpan()->getContext()->getTraceId() === $rootSpan->getContext()->getTraceId() ? '是' : '否') . "\n\n";

// 3. 创建嵌套的子 span（模拟缓存操作）
echo "3. 创建缓存操作 span\n";
$cacheSpan = Measure::span('cache.get', 'users')
    ->setSpanKind(SpanKind::KIND_CLIENT)
    ->setAttribute('cache.key', 'users:all')
    ->setAttribute('cache.operation', 'get')
    ->start();

echo "缓存 span ID: " . $cacheSpan->getSpan()->getContext()->getSpanId() . "\n";
echo "父 span ID: " . $dbSpan->getSpan()->getContext()->getSpanId() . "\n";
echo "同一个 Trace ID: " . ($cacheSpan->getSpan()->getContext()->getTraceId() === $rootSpan->getContext()->getTraceId() ? '是' : '否') . "\n\n";

// 4. 按照正确的顺序结束 span
echo "4. 结束 span\n";
$cacheSpan->end();
echo "缓存 span 已结束\n";

$dbSpan->end();
echo "数据库 span 已结束\n";

Measure::endRootSpan();
echo "根 span 已结束\n\n";

echo "=== Span 层次结构测试完成 ===\n";
echo "如果所有 span 都有相同的 Trace ID，说明 span 链正常工作！\n";

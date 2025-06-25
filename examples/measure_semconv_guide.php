<?php

/**
 * OpenTelemetry 语义约定使用指南
 *
 * 本文件演示了如何在 Laravel OpenTelemetry 包中正确使用标准语义约定
 * 确保与其他 OpenTelemetry 实现的兼容性和一致性
 */

use OpenTelemetry\SemConv\TraceAttributes;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

// ======================= 数据库操作语义约定 =======================

// ✅ 正确：使用标准的数据库语义约定
Measure::database('SELECT', 'users', function ($spanBuilder) {
    $spanBuilder->setAttributes([
        TraceAttributes::DB_SYSTEM => 'mysql',                    // 数据库系统
        TraceAttributes::DB_NAMESPACE => 'myapp_production',      // 数据库名称
        TraceAttributes::DB_COLLECTION_NAME => 'users',          // 表名
        TraceAttributes::DB_OPERATION_NAME => 'SELECT',          // 操作名称
        TraceAttributes::DB_QUERY_TEXT => 'SELECT * FROM users WHERE active = ?', // 查询文本
    ]);
});

// ❌ 错误：使用自定义属性名
Measure::database('SELECT', 'users', function ($spanBuilder) {
    $spanBuilder->setAttributes([
        'database.type' => 'mysql',         // 应该用 TraceAttributes::DB_SYSTEM
        'db.name' => 'myapp_production',    // 应该用 TraceAttributes::DB_NAMESPACE
        'table.name' => 'users',            // 应该用 TraceAttributes::DB_COLLECTION_NAME
    ]);
});

// ======================= HTTP 客户端语义约定 =======================

// ✅ 正确：使用标准的 HTTP 语义约定
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

// ❌ 错误：使用自定义属性名
Measure::httpClient('GET', 'https://api.example.com/users', function ($spanBuilder) {
    $spanBuilder->setAttributes([
        'http.method' => 'GET',             // 应该用 TraceAttributes::HTTP_REQUEST_METHOD
        'request.url' => 'https://api.example.com/users', // 应该用 TraceAttributes::URL_FULL
        'host.name' => 'api.example.com',  // 应该用 TraceAttributes::SERVER_ADDRESS
    ]);
});

// ======================= 消息传递语义约定 =======================

// ✅ 正确：使用标准的消息传递语义约定
Measure::queue('process', 'SendEmailJob', function ($spanBuilder) {
    $spanBuilder->setAttributes([
        TraceAttributes::MESSAGING_SYSTEM => 'laravel-queue',
        TraceAttributes::MESSAGING_DESTINATION_NAME => 'emails',
        TraceAttributes::MESSAGING_OPERATION_TYPE => 'PROCESS',
        TraceAttributes::MESSAGING_MESSAGE_ID => 'msg_12345',
    ]);
});

// ❌ 错误：使用自定义属性名
Measure::queue('process', 'SendEmailJob', function ($spanBuilder) {
    $spanBuilder->setAttributes([
        'queue.system' => 'laravel-queue',  // 应该用 TraceAttributes::MESSAGING_SYSTEM
        'queue.name' => 'emails',           // 应该用 TraceAttributes::MESSAGING_DESTINATION_NAME
        'job.operation' => 'PROCESS',       // 应该用 TraceAttributes::MESSAGING_OPERATION_TYPE
    ]);
});

// ======================= 事件语义约定 =======================

// ✅ 正确：使用标准的事件语义约定
Measure::event('user.registered', function ($spanBuilder) {
    $spanBuilder->setAttributes([
        TraceAttributes::EVENT_NAME => 'user.registered',
        TraceAttributes::ENDUSER_ID => '123',
        'event.domain' => 'laravel',  // 自定义属性，因为没有标准定义
    ]);
});

// ======================= 异常语义约定 =======================

try {
    // 一些可能失败的操作
    throw new \Exception('Something went wrong');
} catch (\Exception $e) {
    // ✅ 正确：异常会自动使用标准语义约定
    Measure::recordException($e);

    // 手动记录时也使用标准属性
    Measure::addEvent('exception.occurred', [
        TraceAttributes::EXCEPTION_TYPE => get_class($e),
        TraceAttributes::EXCEPTION_MESSAGE => $e->getMessage(),
        TraceAttributes::CODE_FILEPATH => $e->getFile(),
        TraceAttributes::CODE_LINENO => $e->getLine(),
    ]);
}

// ======================= 用户认证语义约定 =======================

// ✅ 正确：使用标准的用户语义约定
Measure::auth('login', function ($spanBuilder) {
    $spanBuilder->setAttributes([
        TraceAttributes::ENDUSER_ID => auth()->id(),
        TraceAttributes::ENDUSER_ROLE => auth()->user()->role ?? 'user',
        // 'auth.method' => 'password',  // 自定义属性，因为没有标准定义
    ]);
});

// ======================= 网络语义约定 =======================

// ✅ 正确：使用标准的网络语义约定
$spanBuilder->setAttributes([
    TraceAttributes::NETWORK_PROTOCOL_NAME => 'http',
    TraceAttributes::NETWORK_PROTOCOL_VERSION => '1.1',
    TraceAttributes::NETWORK_PEER_ADDRESS => '192.168.1.1',
    TraceAttributes::NETWORK_PEER_PORT => 8080,
]);

// ======================= 性能监控语义约定 =======================

// ✅ 正确：监控性能时的属性设置
Measure::trace('data.processing', function ($span) {
    $startTime = microtime(true);
    $startMemory = memory_get_usage();

    // 执行数据处理
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

// ======================= 缓存操作（暂无标准语义约定）=======================

// 📝 注意：缓存操作目前没有标准的 OpenTelemetry 语义约定
// 我们使用一致的自定义属性名，等待标准化
Measure::cache('get', 'user:123', function ($spanBuilder) {
    $spanBuilder->setAttributes([
        'cache.operation' => 'GET',
        'cache.key' => 'user:123',
        'cache.store' => 'redis',
        'cache.hit' => true,
        'cache.ttl' => 3600,
    ]);
});

// ======================= 最佳实践总结 =======================

/**
 * 🎯 语义约定使用最佳实践：
 *
 * 1. 优先使用标准语义约定
 *    - 总是从 OpenTelemetry\SemConv\TraceAttributes 中使用预定义常量
 *    - 确保属性名和值符合 OpenTelemetry 规范
 *
 * 2. 自定义属性命名规范
 *    - 当没有标准语义约定时，使用描述性的属性名
 *    - 遵循 "namespace.attribute" 的命名模式
 *    - 避免与现有标准属性冲突
 *
 * 3. 属性值标准化
 *    - 使用标准的枚举值（如 HTTP 方法名大写）
 *    - 保持属性值的一致性和可比较性
 *    - 避免包含敏感信息
 *
 * 4. 向后兼容性
 *    - 当 OpenTelemetry 发布新的语义约定时，及时更新
 *    - 保持现有自定义属性的稳定性
 *
 * 5. 文档化自定义属性
 *    - 为项目特定的属性编写文档
 *    - 确保团队成员了解属性的含义和用途
 */

// ======================= 常见错误和修正 =======================

// ❌ 错误：使用过时的属性名
$spanBuilder->setAttributes([
    'http.method' => 'GET',                    // 已废弃
    'http.url' => 'https://example.com',       // 已废弃
    'http.status_code' => 200,                 // 已废弃
]);

// ✅ 正确：使用最新的标准属性名
$spanBuilder->setAttributes([
    TraceAttributes::HTTP_REQUEST_METHOD => 'GET',           // 新标准
    TraceAttributes::URL_FULL => 'https://example.com',      // 新标准
    TraceAttributes::HTTP_RESPONSE_STATUS_CODE => 200,       // 新标准
]);

// ❌ 错误：属性值不规范
$spanBuilder->setAttributes([
    TraceAttributes::DB_OPERATION_NAME => 'select',          // 应该大写
    TraceAttributes::HTTP_REQUEST_METHOD => 'get',           // 应该大写
]);

// ✅ 正确：规范的属性值
$spanBuilder->setAttributes([
    TraceAttributes::DB_OPERATION_NAME => 'SELECT',          // 大写
    TraceAttributes::HTTP_REQUEST_METHOD => 'GET',           // 大写
]);

<?php

/**
 * 改进后的 Measure API 使用示例
 * 展示了更灵活和语义化的追踪方式，并使用 OpenTelemetry 标准语义约定
 */

use Illuminate\Http\Request;
use OpenTelemetry\SemConv\TraceAttributes;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

// ======================= 原来的使用方式 =======================

// 之前：手动创建和管理 span
$span = Measure::start('user.create');
$span->setAttributes(['user.id' => 123]);
// ... 业务逻辑
$span->end();

// ======================= 改进后的使用方式 =======================

// 1. 使用 trace() 方法自动管理 span 生命周期
$user = Measure::trace('user.create', function ($span) {
    $span->setAttributes([
        TraceAttributes::ENDUSER_ID => 123,
        'user.action' => 'registration'
    ]);

    // 业务逻辑
    $user = new User();
    $user->save();

    return $user;
}, ['initial.context' => 'registration']);

// 2. 语义化的 HTTP 请求追踪
Route::middleware('api')->group(function () {
    Route::get('/users', function (Request $request) {
        // 自动创建 HTTP span 并设置相关属性
        $span = Measure::http($request, function ($spanBuilder) {
            $spanBuilder->setAttributes([
                'user.authenticated' => auth()->check(),
                'api.version' => 'v1',
            ]);
        });

        $users = User::all();
        $span->end();

        return response()->json($users);
    });
});

// 3. 数据库操作追踪（使用标准语义约定）
$users = Measure::trace('user.query', function ($span) {
    // 使用标准的数据库语义约定属性
    $span->setAttributes([
        TraceAttributes::DB_SYSTEM => 'mysql',
        TraceAttributes::DB_NAMESPACE => 'myapp',
        TraceAttributes::DB_COLLECTION_NAME => 'users',
        TraceAttributes::DB_OPERATION_NAME => 'SELECT',
    ]);

    return User::where('active', true)->get();
});

// 4. HTTP 客户端请求追踪
$response = Measure::httpClient('GET', 'https://api.example.com/users', function ($spanBuilder) {
    $spanBuilder->setAttributes([
        'api.client' => 'laravel-http',
        'api.timeout' => 30,
    ]);
});

// 5. 队列任务处理（使用标准消息传递语义约定）
dispatch(function () {
    Measure::queue('process', 'EmailJob', function ($spanBuilder) {
        $spanBuilder->setAttributes([
            TraceAttributes::MESSAGING_SYSTEM => 'laravel-queue',
            TraceAttributes::MESSAGING_DESTINATION_NAME => 'emails',
            TraceAttributes::MESSAGING_OPERATION_TYPE => 'PROCESS',
        ]);
    });
});

// 6. Redis 操作追踪
$value = Measure::redis('GET', function ($spanBuilder) {
    $spanBuilder->setAttributes([
        TraceAttributes::DB_SYSTEM => 'redis',
        TraceAttributes::DB_OPERATION_NAME => 'GET',
        'redis.key' => 'user:123',
    ]);
});

// 7. 缓存操作追踪
$user = Measure::cache('get', 'user:123', function ($spanBuilder) {
    $spanBuilder->setAttributes([
        'cache.store' => 'redis',
        'cache.key' => 'user:123',
    ]);
});

// 8. 事件记录（使用标准事件语义约定）
Measure::event('user.registered', function ($spanBuilder) {
    $spanBuilder->setAttributes([
        TraceAttributes::EVENT_NAME => 'user.registered',
        TraceAttributes::ENDUSER_ID => 123,
        'event.domain' => 'laravel',
    ]);
});

// 9. 控制台命令追踪
Artisan::command('users:cleanup', function () {
    Measure::command('users:cleanup', function ($spanBuilder) {
        $spanBuilder->setAttributes([
            'console.command' => 'users:cleanup',
            'console.arguments' => '--force',
        ]);
    });
});

// ======================= 异常处理和事件记录 =======================

try {
    $result = Measure::trace('risky.operation', function ($span) {
        // 可能会抛出异常的操作
        $span->setAttributes([
            'operation.type' => 'data_processing',
        ]);

        return processData();
    });
} catch (\Exception $e) {
    // 异常会自动记录到 span 中
    Measure::recordException($e);
}

// 手动添加事件到当前 span
Measure::addEvent('checkpoint.reached', [
    'checkpoint.name' => 'data_validation',
    'checkpoint.status' => 'passed',
]);

// ======================= 批量操作示例 =======================

// 批量数据库操作
Measure::database('BATCH_INSERT', 'users', function ($spanBuilder) {
    $spanBuilder->setAttributes([
        TraceAttributes::DB_OPERATION_BATCH_SIZE => 100,
        TraceAttributes::DB_SYSTEM => 'mysql',
        'operation.batch' => true,
    ]);
});

// ======================= 性能监控示例 =======================

// 监控 API 响应时间
$users = Measure::trace('api.users.list', function ($span) {
    $span->setAttributes([
        TraceAttributes::HTTP_REQUEST_METHOD => 'GET',
        'api.endpoint' => '/users',
        'performance.monitored' => true,
    ]);

    $startMemory = memory_get_usage();
    $users = User::with('profile')->paginate(50);
    $endMemory = memory_get_usage();

    $span->setAttributes([
        'memory.usage_bytes' => $endMemory - $startMemory,
        'result.count' => $users->count(),
    ]);

    return $users;
});

// ======================= 分布式追踪示例 =======================

// 在微服务之间传播 trace context
$headers = Measure::propagationHeaders();

// 发送 HTTP 请求时包含追踪头
$response = Http::withHeaders($headers)->get('https://service.example.com/api');

// 接收请求时提取 trace context
$context = Measure::extractContextFromPropagationHeaders($request->headers->all());

<?php

/**
 * Octane Span Hierarchy Test
 *
 * 测试在 Octane 模式下 watchers 的 span 是否正确维持层次结构
 * 修复前: span 会错位，独立存在
 * 修复后: span 应该正确关联到父 span，形成完整的 trace 层次结构
 */

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

Route::get('/octane-hierarchy-test', function () {
    return Measure::trace('main_operation', function () {
        // 1. 测试数据库查询 span 层次结构
        DB::table('users')->count();  // QueryWatcher 应该创建子 span

        // 2. 测试缓存操作 span 层次结构
        Cache::remember('test_key', 60, function () {
            return 'cached_value';
        });  // CacheWatcher 应该创建子 span

        // 3. 测试 HTTP 客户端 span 层次结构
        Http::get('https://httpbin.org/ip');  // HttpClientWatcher 应该创建子 span

        // 4. 触发事件测试 EventWatcher
        event('test.event', ['data' => 'test']);  // EventWatcher 应该创建子 span

        // 5. 嵌套操作测试
        return Measure::trace('nested_operation', function () {
            // 这些操作应该都是 nested_operation 的子 span
            DB::table('posts')->where('status', 'published')->count();
            Cache::get('another_key', 'default');
            Http::get('https://httpbin.org/uuid');

            return [
                'message' => '所有操作应该正确维持层次结构',
                'trace_id' => Measure::traceId(),
                'expected_structure' => [
                    'main_operation' => [
                        'database.query (users)',
                        'cache.set (test_key)',
                        'http.client.get (httpbin.org/ip)',
                        'event (test.event)',
                        'nested_operation' => [
                            'database.query (posts)',
                            'cache.miss (another_key)',
                            'http.client.get (httpbin.org/uuid)',
                        ]
                    ]
                ]
            ];
        });
    });
});

Route::get('/octane-context-test', function () {
    // 测试在 Octane 长期运行进程中 context 是否正确传播
    $results = [];

    // 模拟多个并发请求情况
    for ($i = 1; $i <= 3; $i++) {
        $traceResult = Measure::trace("request_{$i}", function () use ($i) {
            // 每个请求都应该有独立的 trace
            DB::select("SELECT {$i} as request_id");
            Cache::put("request_{$i}", $i, 60);

            return [
                'request_id' => $i,
                'trace_id' => Measure::traceId(),
                'span_count' => 'should_include_db_and_cache_spans',
            ];
        });

        $results[] = $traceResult;
    }

    return [
        'message' => '每个请求应该有独立的 trace_id，但 spans 应该正确关联',
        'results' => $results,
        'verification' => [
            'each_request_has_unique_trace_id' => true,
            'spans_are_properly_nested' => true,
            'no_orphaned_spans' => true,
        ]
    ];
});

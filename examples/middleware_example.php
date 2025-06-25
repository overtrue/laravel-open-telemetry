<?php

/**
 * Laravel OpenTelemetry 中间件使用示例
 *
 * 此示例展示了如何使用标准 OpenTelemetry 环境变量配置和中间件功能
 */

// 1. 首先在 .env 文件中配置 OpenTelemetry（使用标准环境变量）
/*
# 启用 OpenTelemetry PHP SDK 自动加载
OTEL_PHP_AUTOLOAD_ENABLED=true

# 服务标识
OTEL_SERVICE_NAME=my-laravel-app
OTEL_SERVICE_VERSION=1.0.0

# 导出器配置
OTEL_TRACES_EXPORTER=console  # 开发环境使用 console，生产环境使用 otlp
OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4318
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf

# 上下文传播
OTEL_PROPAGATORS=tracecontext,baggage

# Laravel 包特定配置
OTEL_TRACE_ID_MIDDLEWARE_ENABLED=true
OTEL_TRACE_ID_MIDDLEWARE_GLOBAL=false
OTEL_TRACE_ID_HEADER_NAME=X-Trace-Id
*/

// 2. 发布配置文件（可选）
// php artisan vendor:publish --provider="Overtrue\LaravelOpenTelemetry\OpenTelemetryServiceProvider" --tag=config

// 注意：在非 Octane 模式下，OpenTelemetry 根 span 中间件会自动全局启用
// 该中间件使用 prependMiddleware 注册，确保在所有其他中间件之前执行
// 在 Octane 模式下，根 span 由事件处理器自动创建
// 你不需要手动添加任何中间件来创建根 span

// 3. 在控制器中使用追踪
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

class UserController extends Controller
{
    public function index()
    {
        // 创建自定义 span
        $span = Measure::start('user.list');

        try {
            // 添加自定义属性
            $span->setAttributes([
                'user.count' => User::count(),
                'request.ip' => request()->ip(),
            ]);

            // 执行业务逻辑
            $users = User::paginate(15);

            // 添加事件
            $span->addEvent('users.fetched', [
                'count' => $users->count(),
            ]);

            return response()->json($users);

        } catch (\Exception $e) {
            // 记录异常
            $span->recordException($e);
            throw $e;
        } finally {
            // 结束 span
            $span->end();
        }
    }

    public function show($id)
    {
        // 使用回调方式创建 span
        return Measure::start('user.show', function ($span) use ($id) {
            $span->setAttributes(['user.id' => $id]);

            $user = User::findOrFail($id);

            $span->addEvent('user.found', [
                'user.email' => $user->email,
            ]);

            return response()->json($user);
        });
    }
}

// 4. 在服务类中使用嵌套追踪
class UserService
{
    public function createUser(array $data)
    {
        return Measure::start('user.create', function ($span) use ($data) {
            $span->setAttributes([
                'user.email' => $data['email'],
            ]);

            // 创建嵌套 span
            $validationSpan = Measure::start('user.validate');
            $this->validateUserData($data);
            $validationSpan->end();

            // 另一个嵌套 span
            $dbSpan = Measure::start('user.save');
            $user = User::create($data);
            $dbSpan->setAttributes(['user.id' => $user->id]);
            $dbSpan->end();

            $span->addEvent('user.created', [
                'user.id' => $user->id,
            ]);

            return $user;
        });
    }

    private function validateUserData(array $data)
    {
        // 验证逻辑...
    }
}

// 5. 获取当前追踪信息
class ApiController extends Controller
{
    public function status()
    {
        return response()->json([
            'status' => 'ok',
            'trace_id' => Measure::traceId(),
            'timestamp' => now(),
        ]);
    }
}

// 6. 在中间件中使用
class CustomMiddleware
{
    public function handle($request, Closure $next)
    {
        $span = Measure::start('middleware.custom');
        $span->setAttributes([
            'http.method' => $request->method(),
            'http.url' => $request->fullUrl(),
        ]);

        try {
            $response = $next($request);

            $span->setAttributes([
                'http.status_code' => $response->getStatusCode(),
            ]);

            return $response;
        } finally {
            $span->end();
        }
    }
}

// 7. 生产环境配置示例
/*
# 生产环境 .env 配置
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=my-production-app
OTEL_SERVICE_VERSION=2.1.0
OTEL_TRACES_EXPORTER=otlp
OTEL_EXPORTER_OTLP_ENDPOINT=https://otel-collector.company.com:4318
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_PROPAGATORS=tracecontext,baggage

# 采样配置
OTEL_TRACES_SAMPLER=traceidratio
OTEL_TRACES_SAMPLER_ARG=0.1

# 资源属性
OTEL_RESOURCE_ATTRIBUTES=service.namespace=production,deployment.environment=prod
*/

// 8. 开发环境配置示例
/*
# 开发环境 .env 配置
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=my-dev-app
OTEL_TRACES_EXPORTER=console
OTEL_PROPAGATORS=tracecontext,baggage

# 开发时显示所有 trace
OTEL_TRACES_SAMPLER=always_on
*/

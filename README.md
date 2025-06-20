# Laravel OpenTelemetry

[![CI](https://github.com/overtrue/laravel-open-telemetry/actions/workflows/ci.yml/badge.svg)](https://github.com/overtrue/laravel-open-telemetry/actions/workflows/ci.yml)
[![Latest Stable Version](https://poser.pugx.org/overtrue/laravel-open-telemetry/v/stable.svg)](https://packagist.org/packages/overtrue/laravel-open-telemetry)
[![Latest Unstable Version](https://poser.pugx.org/overtrue/laravel-open-telemetry/v/unstable.svg)](https://packagist.org/packages/overtrue/laravel-open-telemetry)
[![Total Downloads](https://poser.pugx.org/overtrue/laravel-open-telemetry/downloads)](https://packagist.org/packages/overtrue/laravel-open-telemetry)
[![License](https://poser.pugx.org/overtrue/laravel-open-telemetry/license)](https://packagist.org/packages/overtrue/laravel-open-telemetry)

🚀 **现代化的 Laravel OpenTelemetry 集成包**

此包在官方 [`opentelemetry-auto-laravel`](https://packagist.org/packages/open-telemetry/opentelemetry-auto-laravel) 包的基础上，提供额外的 Laravel 特定增强功能。

## ✨ 特性

### 🔧 基于官方包
- ✅ 自动安装并依赖官方 `open-telemetry/opentelemetry-auto-laravel` 包
- ✅ 继承官方包的所有基础自动化仪表功能
- ✅ 使用官方标准的注册方式和 hook 机制

### 🎯 增强功能
- ✅ **异常监听**: 详细的异常信息记录
- ✅ **认证追踪**: 用户认证状态和身份信息
- ✅ **事件分发**: 事件名称、监听器数量统计
- ✅ **队列操作**: 任务处理、入队和状态追踪
- ✅ **Redis 命令**: 命令执行、参数和结果记录
- ✅ **Guzzle HTTP**: 自动追踪 HTTP 客户端请求

### ⚙️ 灵活配置
- ✅ 可独立控制每项增强功能的启用/禁用
- ✅ 敏感信息过滤和头部白名单
- ✅ 路径忽略和性能优化选项
- ✅ 自动响应头 trace ID 注入

## 📦 安装

```bash
composer require overtrue/laravel-open-telemetry
```

### 依赖要求

- **PHP**: 8.4+
- **Laravel**: 10.0+ | 11.0+ | 12.0+
- **OpenTelemetry 扩展**: 必需 (`ext-opentelemetry`)
- **官方包**: 自动安装 `open-telemetry/opentelemetry-auto-laravel`

## 🔧 配置

### 发布配置文件

```bash
php artisan vendor:publish --provider="Overtrue\LaravelOpenTelemetry\OpenTelemetryServiceProvider" --tag="config"
```

### 环境变量配置

#### 🟢 OpenTelemetry SDK 配置（服务器环境变量）

**重要**：这些变量必须设置为服务器环境变量，不能放在 Laravel 的 `.env` 文件中：

```bash
# 核心配置
export OTEL_PHP_AUTOLOAD_ENABLED=true
export OTEL_SERVICE_NAME=my-laravel-app
export OTEL_TRACES_EXPORTER=console  # 或 otlp

# 生产环境配置
export OTEL_TRACES_EXPORTER=otlp
export OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4318
```

#### 🟡 Laravel 包配置（可放在 .env 文件）

```bash
# HTTP 头处理
OTEL_ALLOWED_HEADERS=referer,x-*,accept,request-id
OTEL_SENSITIVE_HEADERS=authorization,cookie,x-api-key

# 响应头
OTEL_RESPONSE_TRACE_HEADER_NAME=X-Trace-Id
```

### 配置示例

#### 开发环境
```bash
# 服务器环境变量
export OTEL_PHP_AUTOLOAD_ENABLED=true
export OTEL_SERVICE_NAME=my-dev-app
export OTEL_TRACES_EXPORTER=console

# .env 文件
OTEL_RESPONSE_TRACE_HEADER_NAME=X-Trace-Id
```

#### 生产环境
```bash
# 服务器环境变量
export OTEL_PHP_AUTOLOAD_ENABLED=true
export OTEL_SERVICE_NAME=my-production-app
export OTEL_TRACES_EXPORTER=otlp
export OTEL_EXPORTER_OTLP_ENDPOINT=https://your-collector.com:4318

# .env 文件
OTEL_RESPONSE_TRACE_HEADER_NAME=X-Trace-Id
OTEL_SERVICE_VERSION=2.1.0
```

## 🚀 使用方法

### 响应头 Trace ID

安装后，每个 HTTP 响应都会自动包含 trace ID 头部（默认为 `X-Trace-Id`）：

```bash
# 请求示例
curl -v https://your-app.com/api/users

# 响应头将包含
X-Trace-Id: 1234567890abcdef1234567890abcdef
```

**配置选项：**
- 设置自定义头部名称：`OTEL_RESPONSE_TRACE_HEADER_NAME=Custom-Trace-Header`
- 禁用此功能：`OTEL_RESPONSE_TRACE_HEADER_NAME=null`

### 自动追踪

安装并配置后，包会自动为您的 Laravel 应用提供详细的追踪信息：

```php
// 官方包提供的基础功能
// ✅ HTTP 请求自动追踪
// ✅ 数据库查询追踪
// ✅ 缓存操作追踪
// ✅ 外部 HTTP 请求追踪

// 此包提供的增强功能
// ✅ 异常详细记录
// ✅ 用户认证状态追踪
// ✅ 事件分发统计
// ✅ 队列任务处理追踪
// ✅ Redis 命令执行记录
// ✅ Guzzle HTTP 客户端追踪
// ✅ 自动响应头 trace ID 注入
```

### 手动追踪

使用 Facade 进行手动追踪：

```php
use Overtrue\LaravelOpenTelemetry\Facades\Measure;

// 简单 span
$startedSpan = Measure::span('custom-operation')->start();
// 您的代码
$startedSpan->end();

// 使用闭包（推荐方式）
$result = Measure::span('custom-operation')->measure(function() {
    // 您的代码
    return 'result';
});

// 手动控制
$spanBuilder = Measure::span('custom-operation');
$spanBuilder->setAttribute('user.id', $userId);
$spanBuilder->setAttribute('operation.type', 'critical');
$startedSpan = $spanBuilder->start();
// 您的代码
$startedSpan->end();

// 获取当前 span
$currentSpan = Measure::getCurrentSpan();

// 获取追踪 ID
$traceId = Measure::getTraceId();
```

### Guzzle HTTP 客户端追踪

自动为 Guzzle HTTP 请求添加追踪：

```php
use Illuminate\Support\Facades\Http;

// 使用 withTrace() 宏启用追踪
$response = Http::withTrace()->get('https://api.example.com/users');

// 或者直接使用，如果全局启用了追踪
$response = Http::get('https://api.example.com/users');
```

### 测试命令

运行内置的测试命令来验证追踪是否正常工作：

```bash
php artisan otel:test
```

此命令将创建一些测试 span 并显示当前的配置状态。

## 🏗️ 架构说明

### 分层架构

```
┌─────────────────────────────────────┐
│     您的 Laravel 应用               │
├─────────────────────────────────────┤
│  overtrue/laravel-open-telemetry    │  ← 增强层
│  Hooks:                             │
│  - HTTP Kernel Hook (响应头)        │
│  Watchers:                          │
│  - ExceptionWatcher                 │
│  - AuthenticateWatcher              │
│  - EventWatcher                     │
│  - QueueWatcher                     │
│  - RedisWatcher                     │
├─────────────────────────────────────┤
│  open-telemetry/opentelemetry-      │  ← 官方自动化层
│  auto-laravel                       │
│  - HTTP 请求、数据库、缓存追踪       │
├─────────────────────────────────────┤
│  OpenTelemetry PHP SDK              │  ← 核心 SDK
└─────────────────────────────────────┘
```

### 注册机制

- **双重机制**: 同时支持 Hook 和 Watcher 两种注册方式
- **Hook 层**: 基于 OpenTelemetry 官方 Hook 机制，用于核心基础设施功能（如响应头注入）
- **Watcher 层**: 基于 Laravel 事件系统，用于应用层业务逻辑追踪
- **高性能**: Hook 直接拦截框架调用，Watcher 基于原生事件机制，性能开销极小
- **标准化**: 遵循 OpenTelemetry 官方标准和最佳实践
- **模块化**: 每个组件独立注册，可单独启用/禁用

## 🔍 追踪信息示例

### HTTP 请求追踪
```
Span: http.request
├── http.method: "GET"
├── http.url: "https://example.com/users/123"
├── http.status_code: 200
├── http.request.header.content-type: "application/json"
└── http.response.header.content-length: "1024"
```

### 队列任务追踪
```
Span: queue.process
├── queue.connection: "redis"
├── queue.name: "emails"
├── queue.job.class: "App\Jobs\SendEmailJob"
├── queue.job.id: "job_12345"
├── queue.job.attempts: 1
└── queue.job.status: "completed"
```

### Redis 命令追踪
```
Span: redis.get
├── db.system: "redis"
├── db.operation: "get"
├── redis.command: "GET user:123:profile"
├── redis.result.type: "string"
└── redis.result.length: 256
```

### 异常追踪
```
Span: exception.handle
├── exception.type: "App\Exceptions\UserNotFoundException"
├── exception.message: "User with ID 123 not found"
├── exception.stack_trace: "..."
└── exception.level: "error"
```

## 🧪 测试

```bash
composer test
```

## 🎨 代码风格

```bash
composer fix-style
```

## 🤝 贡献

欢迎提交 Pull Request！请确保：

1. 遵循现有代码风格
2. 添加适当的测试
3. 更新相关文档
4. 确保所有测试通过

## 📝 变更日志

请查看 [CHANGELOG](CHANGELOG.md) 了解详细的版本变更信息。

## 📄 许可证

MIT 许可证。详情请查看 [License File](LICENSE) 文件。

## 🙏 致谢

- [OpenTelemetry PHP](https://github.com/open-telemetry/opentelemetry-php) - 核心 OpenTelemetry PHP 实现
- [OpenTelemetry Auto Laravel](https://github.com/opentelemetry-php/contrib-auto-laravel) - 官方 Laravel 自动化仪表包
- [Laravel](https://laravel.com/) - 优雅的 PHP Web 框架

---

<p align="center">
  <strong>让您的 Laravel 应用具备世界级的可观测性 🚀</strong>
</p>

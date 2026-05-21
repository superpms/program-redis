# 公开 API

## pms\facade\RDb

`RDb` 是 facade，目标类是 `pms\program\redis\Driver`。业务代码通常通过 `RDb::method()` 调用 Redis。

常用方法来自 `Driver` 和 `builder\Redis`:

| 方法 | 来源 | 说明 |
| --- | --- | --- |
| `setConfig(array $config)` | `Driver` | 注入项目 Redis 配置 |
| `getConfig(string $name = '', mixed $default = null)` | `Driver` | 读取全部或单个配置 |
| `connect(?string $name = null, bool $force = false)` | `Driver` | 获取指定连接 |
| `isPool(bool $status)` | `Driver` | 切换连接器选择是否使用池化 |
| `getInstance()` | `Driver` | 返回当前已创建连接实例 |
| `listen(callable $callback)` | `Driver` | 保存监听回调，当前包内未主动触发 |
| `event(string $event, callable $callback)` | `Driver` | 注册事件回调 |
| `trigger(string $event, mixed $params = null)` | `Driver` | 触发事件回调 |
| `getRedis()` | `builder\Redis` | 返回原生 `\Redis` handler |
| `getPrefix(string $key = '')` | `builder\Redis` | 拼接 Redis prefix |
| `clearPrefix(string $key)` | `builder\Redis` | 移除当前 prefix |
| `set(string $key, mixed $value, int $expire = 0)` | `builder\Redis` | 写 key |
| `get(string $key, mixed $default = null)` | `builder\Redis` | 读 key，不存在返回默认值 |
| `delete(string $key, string ...$otherKeys)` | `builder\Redis` | 删除 key |
| `scanX(string $pattern, ?int $length = null)` | `builder\Redis` | 用 SCAN 扫描 key |
| `setnx(string $key, mixed $value, int $expire = 0)` | `builder\Redis` | key 不存在时设置 |
| `deleteFolder(string $path)` | `builder\Redis` | 按 `path:*` 扫描删除 |
| `lock(string $name, int $occupy = 3, int $pause = 50)` | `builder\Redis` | 抢占式分布式锁 |
| `unlock(string $name)` | `builder\Redis` | 释放分布式锁 |
| `setnxCache(string $name, Closure $callback, int $expireTime = 0, int $retryCount = 10)` | `builder\Redis` | 抢占式缓存生成 |
| `LRangeAll(string $key)` | `builder\Redis` | `lRange($key, 0, -1)` |
| `LRangeLen(string $key, int $end)` | `builder\Redis` | `lRange($key, 0, $end)` |
| `subscribe(array $channels, callable $callback, bool $autoSetNotify = false)` | `builder\Redis` | 阻塞订阅，标记为 deprecated |
| `unsubscribe(array $channels)` | `builder\Redis` | 取消订阅并恢复 prefix |

未列出的 Redis 命令会通过 `__call()` 透传到 ext-redis。

## pms\RedisQueue

`RedisQueue` 是抽象队列基类，队列名默认为调用类完整类名中的反斜杠替换成下划线。

| 方法 | 说明 |
| --- | --- |
| `lPush(...$items)` | 向队列左侧推入 |
| `rPush(...$items)` | 向队列右侧推入 |
| `lPop()` | 从左侧弹出 |
| `rPop()` | 从右侧弹出 |

如需自定义队列名，可在子类中覆盖 `protected static function getName(): string`。

## Helper

`prdb_pool_autoclose()` 会读取 `RDb::getInstance()` 中的全部连接实例并调用 `close()`。Swoole HTTP sandbox 销毁阶段会自动调用它。

## Connector 类

- `pms\program\redis\connector\Redis`: 懒连接，首次方法调用时创建 `\Redis`
- `pms\program\redis\connector\RedisPool`: 基于 Swoole `ConnectionPool`，`close()` 时把连接归还池

这两个连接器都通过 `__call()` 创建 `builder\Redis` 并代理方法调用。

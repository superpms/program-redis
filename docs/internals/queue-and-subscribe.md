# 队列与发布订阅

## RedisQueue

`pms\RedisQueue` 是 Redis list 的薄封装。默认队列名来自子类类名:

```php
class AppMailQueue extends \pms\RedisQueue {}
// queue name: AppMailQueue
```

命名空间中的反斜杠会被替换为下划线:

```php
class \app\queue\MailWork extends \pms\RedisQueue {}
// queue name: app_queue_MailWork
```

可用方法:

- `lPush(...$items)`
- `rPush(...$items)`
- `lPop()`
- `rPop()`

它不包含 ack、retry、delay、dead-letter 等可靠队列语义。需要这些语义时，应在业务层或套件层实现。

## List helper

`builder\Redis` 提供:

- `LRangeAll($key)`: 返回全部 list 项
- `LRangeLen($key, $end)`: 返回 `0..$end`
- 原生 `lrange()`、`lLen()`、`lPush()`、`rPush()`、`lPop()`、`rPop()` 可通过 `__call()` 透传

大小写需要注意: `LRangeAll` 和 `LRangeLen` 是本包自定义方法名；`lrange` 是 ext-redis 原生透传。

## 订阅

`subscribe(array $channels, callable $callback, bool $autoSetNotify = false)` 当前标记为 `@deprecated`，但代码仍可用。

它会:

1. 读取 Redis `notify-keyspace-events`
2. 如果未包含 `E`，且 `$autoSetNotify=true`，尝试设置为 `Eg`
3. 如果未包含 `E`，且 `$autoSetNotify=false`，抛出异常
4. 调用连接隔离，避免当前阻塞连接继续占用 facade 单例上下文
5. 设置 `OPT_READ_TIMEOUT=-1`
6. 清空 `OPT_PREFIX`
7. 调用 ext-redis `subscribe()`

`unsubscribe()` 会恢复 prefix 后调用原生 `unsubscribe()`。

## 使用限制

- `subscribe()` 是阻塞调用，适合独立 CLI/worker，不适合普通 HTTP 请求链。
- 自动设置 `notify-keyspace-events` 需要 Redis 服务允许 `CONFIG SET`。
- 订阅期间 prefix 被清空，callback 收到的 channel/key 需要按真实 Redis key 处理。

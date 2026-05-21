# 典型用法

## 基本读写

```php
use pms\facade\RDb;

RDb::set('system:flag', '1', 60);
$value = RDb::get('system:flag', '0');
RDb::delete('system:flag');
```

`set($key, $value, $expire)` 在 `$expire > 0` 时使用 `setex`，否则使用 `set`。`get($key, $default)` 在 Redis 返回 `false` 或 `null` 时返回默认值。

## 前缀处理

```php
$full = RDb::getPrefix('process:list:demo');
$raw = RDb::clearPrefix($full);
```

`getPrefix()` 和 `clearPrefix()` 来自 `builder\Redis`，基于 ext-redis 的 `OPT_PREFIX`。

## 扫描与删除一组 key

```php
$keys = RDb::scanX('cache:user:*', 100);
RDb::deleteFolder('cache:user');
```

`scanX()` 用 Redis `SCAN` 累积匹配 key，适合替代直接 `keys()`。`deleteFolder()` 会把传入路径修正为 `path:*` 后逐批删除。

## 分布式锁

```php
RDb::lock('settlement:daily', 120, 10);
try {
    // only one process should run this section
} finally {
    RDb::unlock('settlement:daily');
}
```

锁 key 会自动加 `auto-lock:` 前缀。`lock()` 会循环抢占，发现过期锁会先删除。

## setnxCache

```php
$data = RDb::setnxCache('wechat:access-token', function (callable $setExpire) {
    $setExpire(7000);
    return ['token' => 'xxx'];
}, 300, 10);
```

`setnxCache()` 的语义是: 缓存存在则读缓存；缓存不存在时通过 `auto-lock:<name>` 只允许一个进程生成缓存，其他进程短暂等待。回调返回非 `false`、非 `null`、非空字符串时会 JSON 编码后写入缓存。

## List 队列

```php
RDb::rPush('queue:mail', json_encode(['to' => 'a@example.com'], 320));
$item = RDb::lPop('queue:mail');
$size = RDb::lLen('queue:mail');
```

如果只需要按类名生成队列名，可以继承 `pms\RedisQueue`。

## 原生 Redis 方法

`builder\Redis::__call()` 会把未显式封装的方法透传给 ext-redis handler，因此可以调用 `ttl()`、`expire()`、`eval()`、`hSet()`、`hGet()`、`lrange()` 等 ext-redis 支持的方法。返回结构遵循 ext-redis 自身行为。

# 运行流程与连接池

## 启动流程

1. Composer 自动加载 `bin/autoload.php`
2. `bin/autoload.php` 加载 helper 与 autorun
3. `bin/autorun.php` 在 `LIFECYCLE_BOOT` 把 `config('redis')` 注入 `RDb`
4. 第一次调用 `RDb::xxx()` 时，`Driver::__call()` 获取默认连接
5. `connector\Redis::__call()` 懒创建原生 `\Redis`
6. 连接器创建 `builder\Redis`，再执行目标方法

## Driver 实例缓存

`Driver` 按连接名缓存连接实例:

```php
RDb::connect('connection1');
RDb::connect('connection1'); // 复用同一个 connector 实例
RDb::connect('connection1', true); // 强制重建
```

`RDb::getInstance()` 返回这些 connector 实例，`prdb_pool_autoclose()` 用它做统一回收。

## 普通连接器

`connector\Redis` 内部只保存一个 `?Redis $redis`:

- 第一次调用方法时连接 Redis
- 后续方法复用同一 handler
- `close()` 或析构时关闭连接并置空
- 阻塞订阅前可以通过 `isolate()` 放弃当前 handler，让后续普通命令重新建连接

## Swoole 连接池

Swoole HTTP 模式下，`RDb::isPool(true)` 会让 `Driver` 选择 `RedisPool`。

`RedisPool` 的行为:

- 第一次连接时创建 `Swoole\ConnectionPool`
- pool size 来自 `pool_count`
- 每次获取连接调用 `$pool->get()`
- `close()` 时调用 `$pool->put($this->redis)` 归还连接
- `pool_wait_time` 非 `0` 时，连接上会写入 `last_time`，超过时间窗口后尝试重新取连接

## 请求结束回收

Swoole HTTP sandbox 销毁阶段会执行:

```php
prdb_pool_autoclose();
```

该 helper 遍历 `RDb::getInstance()` 中的 connector 并调用 `close()`。普通连接会关闭，池化连接会归还到 pool。

## 扩展点

- 新增连接器: 在 `Driver::$connectors` 增加映射，或在配置 `type` 中直接填完整类名
- 新增 builder: 在 `Driver::$builders` 增加映射，并让 connector 使用对应 builder
- 新增统一事件: 可用 `Driver::event()` 和 `Driver::trigger()`，但当前包内没有默认事件触发点

扩展时要保持构造签名兼容: connector 构造函数接收完整连接配置数组。

# superpms/program-redis

`program-redis` 是 PMS composer 体系里的 Redis program 包。它把项目级 `config('redis')` 接入 `pms\facade\RDb`，提供普通 Redis 连接、Swoole HTTP 连接池、常用 Redis helper、简单 Redis list 队列基类，以及阻塞订阅场景需要的连接隔离能力。

## 安装与挂载

```bash
composer require superpms/program-redis
```

包通过 `composer.json` 的 `autoload.files` 自动执行 `bin/autoload.php`。该入口会加载:

- `bin/helper.php`: 提供 `prdb_pool_autoclose()`
- `bin/autorun.php`: 挂载生命周期 hook

运行期接入顺序:

1. `LIFECYCLE_BOOT` 读取 `config('redis')` 并调用 `RDb::setConfig()`
2. Swoole 环境下，HTTP 生命周期启动时调用 `RDb::isPool(true)`
3. Swoole HTTP sandbox 销毁时调用 `prdb_pool_autoclose()` 归还连接池连接

## 快速使用

```php
use pms\facade\RDb;

RDb::set('cache:user:1', json_encode(['name' => 'Tom'], 320), 300);
$json = RDb::get('cache:user:1', '{}');

RDb::lock('order:pay:1001', 60, 10);
try {
    // critical section
} finally {
    RDb::unlock('order:pay:1001');
}
```

简单队列可以继承 `pms\RedisQueue`:

```php
use pms\RedisQueue;

class MailWork extends RedisQueue {}

MailWork::rPush(json_encode(['to' => 'user@example.com'], 320));
$payload = MailWork::lPop();
```

## 主要模块

- `pms\facade\RDb`: Redis facade，代理到 `pms\program\redis\Driver`
- `pms\program\redis\Driver`: 配置、连接实例、连接器切换和事件注册入口
- `pms\program\redis\RedisConfig`: 单个连接的配置值对象
- `pms\program\redis\connector\Redis`: 普通 ext-redis 连接器
- `pms\program\redis\connector\RedisPool`: Swoole `ConnectionPool` 连接器
- `pms\program\redis\builder\Redis`: 常用 Redis 操作封装和原生方法透传
- `pms\RedisQueue`: 基于 Redis list 的极简队列基类

## Docs 导航

- [文档入口](docs/00-index.md)
- [安装与配置](docs/guide/setup.md)
- [典型用法](docs/guide/usage.md)
- [公开 API](docs/reference/api.md)
- [配置项](docs/reference/configuration.md)
- [运行流程与连接池](docs/internals/runtime-and-pool.md)
- [队列与发布订阅](docs/internals/queue-and-subscribe.md)
- [异常、限制与排查](docs/operations/troubleshooting.md)

## 注意事项

- 本包没有自带 `resource/config.php`，真实配置由项目侧 `config('redis')` 提供。
- `type` 当前真实支持值是 `redis`；Swoole 池化由运行期开关切换为 `redis-pool`，不是在配置里直接写池化类。
- `RedisConfig` 的部分属性是有类型且无默认值的，项目配置必须提供 `host`、`port`、`connect_timeout`、`retry_interval`、`read_timeout`、`retry_times`、`password` 等被读取字段。
- `subscribe()` 是阻塞调用，会隔离当前连接并把 Redis prefix 临时清空；业务侧应避免在同一流程里混用长阻塞订阅和普通短命令。

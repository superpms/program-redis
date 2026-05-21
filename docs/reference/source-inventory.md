# 源码清单与包入口

本文记录 `superpms/program-redis` 的包级入口、自动加载声明和一方源码文件，作为功能覆盖核查的基准。

## composer.json

| 项 | 值 |
| --- | --- |
| `autoload.files` | `bin/autoload.php` |
| `autoload.psr-4` | `pms\\` -> `src/pms/` |
| `extra.pms` | 无 |
| `bin` | 无 |

## bin 文件

| 文件 | 作用 |
| --- | --- |
| `bin/autoload.php` | Composer files 入口，加载 helper 与 autorun |
| `bin/helper.php` | 定义 `prdb_pool_autoclose()`，用于关闭或归还当前请求内已创建的 Redis connector |
| `bin/autorun.php` | 普通 `LIFECYCLE_BOOT` 注入 `config('redis')`；Swoole HTTP 下启用池化，并在 sandbox 销毁阶段执行连接回收 |

## resource/config.php

无。本包只消费项目侧 `config('redis')`，不会通过 `extra.pms` 投影默认 Redis 配置。

## src 一方源码

| 文件 | 公开功能面 |
| --- | --- |
| `src/pms/facade/RDb.php` | `RDb` facade，代理 `pms\program\redis\Driver` |
| `src/pms/RedisQueue.php` | 抽象 list 队列基类；按类名生成队列名，提供 `lPush`、`rPush`、`lPop`、`rPop` |
| `src/pms/program/redis/Driver.php` | Redis driver；管理配置、连接实例、池化切换、事件回调，并通过 `__call` 代理默认连接 |
| `src/pms/program/redis/RedisConfig.php` | 单连接配置对象；封装 host、port、超时、密码、database、prefix、pool、options 等字段 |
| `src/pms/program/redis/builder/Redis.php` | Redis builder；封装 prefix、读写、删除、扫描、`setnx`、目录删除、锁、抢占式缓存、list helper、订阅/取消订阅和原生命令透传 |
| `src/pms/program/redis/connector/Redis.php` | 普通 ext-redis 懒连接 connector |
| `src/pms/program/redis/connector/RedisPool.php` | Swoole `ConnectionPool` 版 Redis connector，`close()` 时归还连接 |

## 覆盖入口

- 接入和配置见 [安装与配置](../guide/setup.md)、[配置项](configuration.md)。
- facade、队列、helper、connector 和 builder 方法见 [公开 API](api.md)。
- 典型读写、锁、扫描和 list 用法见 [典型用法](../guide/usage.md)。
- 队列和订阅见 [队列与发布订阅](../internals/queue-and-subscribe.md)。
- 生命周期、连接缓存和池化见 [运行流程与连接池](../internals/runtime-and-pool.md)。
- 返回值和排查见 [异常、限制与排查](../operations/troubleshooting.md)。

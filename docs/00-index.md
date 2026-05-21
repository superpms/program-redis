# program-redis 文档入口

这组文档面向开发者，说明 `superpms/program-redis` 在 PMS composer 包体系里的真实接入方式、公开 API、配置结构、连接池行为和常见开发用法。

## 先读

1. [安装与配置](guide/setup.md)
2. [典型用法](guide/usage.md)
3. [公开 API](reference/api.md)
4. [源码清单与包入口](reference/source-inventory.md)

## 按问题读

- 要接入包或确认自动挂载: [安装与配置](guide/setup.md)
- 要写业务代码调用 Redis: [典型用法](guide/usage.md)
- 要查 facade/helper/类方法: [公开 API](reference/api.md)
- 要核对 composer、bin、config、src 清单: [源码清单与包入口](reference/source-inventory.md)
- 要查 `redis.php` 配置字段: [配置项](reference/configuration.md)
- 要理解 Swoole 连接池和回收: [运行流程与连接池](internals/runtime-and-pool.md)
- 要理解 `RedisQueue`、list 队列或订阅: [队列与发布订阅](internals/queue-and-subscribe.md)
- 要排查异常、返回值和限制: [异常、限制与排查](operations/troubleshooting.md)

## 不在这里读

- 业务端 Redis 队列套件、workflow 触发器和数据库表结构不属于本 composer 包文档。
- Redis 服务部署、账号密码和环境差异由项目侧配置文档负责。
- `program-terminal-process` 的 Redis 键空间协议见该包自己的 docs。

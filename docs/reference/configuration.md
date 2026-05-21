# 配置项

配置由项目侧 `config('redis')` 提供，包内没有 `resource/config.php`。

## 顶层结构

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `default` | string | 默认连接名；缺省时使用 `redis` |
| `connections` | array | 连接配置表，key 是连接名 |

## 单连接配置

| 字段 | 类型 | 默认/要求 | 说明 |
| --- | --- | --- | --- |
| `type` | string | 默认 `mysql`，实际应填 `redis` | 连接器类型；池化时会变成 `redis-pool` |
| `host` | string | 必填 | Redis 地址 |
| `port` | int | 必填 | Redis 端口 |
| `connect_timeout` | float | 必填 | 连接超时；非 `0.0` 时传给 `connect()` |
| `retry_interval` | float | 必填 | 重连间隔；非 `0` 时作为 ext-redis connect 参数传入 |
| `read_timeout` | float | 必填 | 读取超时；非 `0.0` 时设置 `OPT_READ_TIMEOUT` |
| `retry_times` | int | 必填但当前连接器未使用 | 命令失败重试次数的配置位 |
| `password` | string | 必填，可为空字符串 | 非空时调用 `auth()` |
| `database` | int | 默认 `0` | 非 `0` 时调用 `select()` |
| `prefix` | string | 默认空字符串 | 非空时设置 `OPT_PREFIX` |
| `pool_count` | int | 默认 `64` | Swoole 连接池大小 |
| `pool_wait_time` | int | 默认 `0` | 池连接复用空闲时间窗口 |
| `options` | array | 默认 `[]` | 逐项传给 `setOption($key, $value)` |

`RedisConfig` 通过构造函数按属性名灌入配置。由于多个属性声明了类型但没有默认值，缺字段会在 getter 被调用时触发 PHP typed property 初始化错误。

## 连接器选择

`Driver::getConnectionConfig()` 会读取连接配置的 `type`:

- 非池化: `redis` -> `pms\program\redis\connector\Redis`
- 池化: `redis` + `RDb::isPool(true)` -> `pms\program\redis\connector\RedisPool`

如果 `type` 写成一个完整类名，`Driver::createConnection()` 会直接实例化该类。自定义类需要接受连接配置数组作为构造参数，并实现被业务调用的方法。

## Prefix 行为

prefix 是 ext-redis 层的 `OPT_PREFIX`。因此:

- 普通 `set/get/del` 会自动带 prefix
- `getPrefix()` 只是按当前 prefix 拼字符串
- `clearPrefix()` 只移除当前 prefix，不验证 key 是否真实存在
- `subscribe()` 会把 prefix 设为空，`unsubscribe()` 再恢复

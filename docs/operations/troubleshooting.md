# 异常、返回结构与排查

## 常见异常

| 现象 | 来源 | 处理 |
| --- | --- | --- |
| `Undefined db config:<name>` | `Driver::getConnectionConfig()` | 检查 `config('redis.connections')` 是否包含连接名 |
| `Redis扩展 未安装` | `connector\Redis::connect()` | 安装并启用 PHP ext-redis |
| typed property 未初始化 | `RedisConfig` getter | 补齐连接配置字段 |
| `Redis <method> 方法不存在` | connector fallback | 检查方法是否存在于 builder 或 ext-redis |
| `当前Redis 为 正确配置 notify-keyspace-events` | `subscribe()` | 修正 Redis keyspace event 配置，或传入 `$autoSetNotify=true` |
| `RedisException: 请求终止` | `setnxCache()` 等待超时 | 检查生成缓存的回调是否报错或耗时过长 |

## 返回值约定

- `set()` 固定返回 `true`，不返回 ext-redis 原始结果
- `get()` 在 Redis 返回 `false` 或 `null` 时返回传入默认值
- `delete()` 返回是否删除了至少一个 key
- `scanX()` 固定返回数组，扫描失败时返回已收集结果
- `setnx()` 返回 ext-redis `setnx()` 的布尔/数组风格结果
- 原生透传方法的返回值以 ext-redis 为准

## 连接池问题排查

1. 确认当前是否在 `in_swoole()` 环境
2. 确认 `pms\hook\HttpLifecycleHook` 是否存在并执行
3. 确认 `RDb::isPool(true)` 是否在 HTTP 生命周期启动阶段执行
4. 确认请求结束是否调用 `prdb_pool_autoclose()`
5. 检查 `pool_count` 是否超过 Redis `maxclients` 可承载范围

## Key 前缀问题排查

- 业务传入的 key 不需要手动加 prefix
- 如果拿到的是 scan 返回的完整 key，可用 `RDb::clearPrefix($key)` 转回业务 key
- TerminalProcess 等需要按 Redis 实际 key 查询时，注意 `scanX()` 返回值可能包含 prefix

## 限制与注意事项

- `retry_times` 当前只是配置字段，连接器没有实现命令级重试。
- `Driver::$builders` 当前只配置了 `redis`，其他 `type` 需要自定义完整类名或补映射。
- `RedisPool::close()` 假定 `$this->pool` 可用，调用时机应在已创建池之后。
- `setnxCache()` 用 `empty()` 判断缓存值，缓存内容为 `0`、空数组、空字符串等场景要谨慎。

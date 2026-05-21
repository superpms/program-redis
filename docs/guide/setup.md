# 安装与配置

## Composer 安装

```bash
composer require superpms/program-redis
```

`composer.json` 中的关键挂载:

```json
{
  "autoload": {
    "files": ["bin/autoload.php"],
    "psr-4": {
      "pms\\": "src/pms/"
    }
  }
}
```

`bin/autoload.php` 会先加载 `bin/helper.php`，再加载 `bin/autorun.php`。

## 自动挂载

`bin/autorun.php` 只在对应 hook 类存在时挂载:

- `pms\hook\LifecycleHook` 存在时，在 `LIFECYCLE_BOOT` 读取 `config('redis')`
- `in_swoole()` 为真且 `pms\hook\HttpLifecycleHook` 存在时，启用 Redis pool 模式
- Swoole HTTP sandbox 销毁时调用 `prdb_pool_autoclose()` 回收连接

这意味着包本身不主动读取某个固定文件路径，而是依赖框架项目的 `config('redis')` 能返回数组。

## 最小配置结构

```php
return [
    'default' => 'connection1',
    'connections' => [
        'connection1' => [
            'type' => 'redis',
            'host' => '127.0.0.1',
            'port' => 6379,
            'connect_timeout' => 10,
            'retry_interval' => 0,
            'read_timeout' => 0,
            'retry_times' => 0,
            'password' => '',
            'database' => 0,
            'prefix' => 'pms:',
            'pool_count' => 64,
            'pool_wait_time' => 300,
            'options' => [],
        ],
    ],
];
```

`default` 缺省时，`Driver` 会尝试使用连接名 `redis`。如果 `connections` 中不存在该连接，会抛出 `InvalidArgumentException('Undefined db config:...')`。

## 环境依赖

- PHP `>=8.1`
- 运行时需要 ext-redis
- Swoole 连接池路径需要 Swoole `ConnectionPool`

`composer.json` 里 ext-redis 和 Swoole helper 位于 `require-dev`，但实际运行 Redis 连接器时会实例化 `\Redis`；扩展缺失会抛出 `Redis扩展 未安装`。

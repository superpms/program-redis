<?php

namespace pms\program\redis;

use InvalidArgumentException;

/**
 * @method  bool set(string $name, $value, int $expire = 0) 设置缓存
 * @method  mixed get(string $key, $default = null) 获取缓存
 * @method  bool delete(string $key) 删除缓存
 * @method  array|false keys(string $key) 找所有符合给定模式 pattern 的 key
 * @method  array|false scan(string $key, int $length = null) 命令用于代替 keys 使用
 * @method  bool expire(string $key, int $ttl) 设置缓存过期时间
 * @method  bool|int ttl(string $key) 返回 key 剩余的过期时间
 * @method  array|bool setnx(string $key, $value, int $expire = 0) 在指定的 key 不存在时, 为 key 设置指定的值
 * @method  bool deleteFolder(string $key) 删除文件夹下所有缓存
 * @method  void lock(string $name, int $occupy = 3, int $putup = 50) Redis分布式锁 锁定
 * @method  void unlock(string $name) Redis分布式锁 解锁
 * @method  mixed setnxDCS(string $name, \Closure $callback, $expire = null) 如果缓存存在，读取缓存，如果不存在，Redis分布式锁型创建缓存
 * @method  bool|int exists(string $key, ...$other_keys) 判断 key 是否存在
 * @method  bool|mixed lPush(string $key, ...$values) 将字符串值添加到列表的开头（左侧）。如果键不存在，则创建列表。
 * @method  bool|mixed rPush(string $key, ...$values) 将字符串值添加到列表的结尾（右侧）。如果键不存在，则创建列表。
 * @method  bool|mixed lPop(string $key) 返回并删除列表的第一个元素。
 * @method  bool|mixed rPop(string $key) 返回并删除列表的最后一个元素。
 * @method  bool|int lLen(string $key)  返回由键标识的列表的大小。如果该列表不存在或为空，则该命令返回0。如果Key标识的数据类型不是列表，则命令返回FALSE。。
 * @method  Array LRangeAll(string $key)  返回List的所有项。
 * @method  Array LRangeLength(string $key, int $end)  返回List指定个数的项。
 * @method  Array LRange(string $key,int $start, int $end)  截取 List指定位置的项。
 * @method  bool|int hSet($key, $hashKey, $value) 将值添加到存储在键处的哈希中。如果该值已经在哈希中，则返回FALSE。
 * @method  bool|int hLen($key) 返回哈希的长度（以项数为单位）。
 * @method  bool|string hGet($key, $hashKey) 返回哈希表指定行的值。
 * @method  bool|array hGetAll($key) 返回哈希表所有行。
 * @method  bool|int hDel($key, $hashKey) 删除哈希表指定行。
 */
class Driver
{

    protected array $connectors = [
        'redis' => \pms\program\redis\connector\Redis::class,
        'redis-pool' => \pms\program\redis\connector\RedisPool::class,
    ];

    protected array $builders = [
        'redis' => builder\Redis::class,
    ];

    /**
     * 数据库连接实例.
     *
     * @var array
     */
    protected array $instance = [];

    /**
     * Redis数据库配置.
     *
     * @var array
     */
    protected array $config = [];

    /**
     * Event对象或者数组.
     *
     * @var array|object
     */
    protected array|object $event;

    /**
     * SQL监听.
     *
     * @var array
     */
    protected array $listen = [];

    /**
     * 查询次数.
     *
     * @var int
     */
    protected int $queryTimes = 0;

    /**
     * 初始化配置参数.
     * @param array $config 连接配置
     * @return void
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * 获取配置参数.
     *
     * @param string $name    配置参数
     * @param mixed|null $default 默认值
     *
     * @return mixed
     */
    public function getConfig(string $name = '', mixed $default = null): mixed
    {
        if ('' === $name) {
            return $this->config;
        }
        return $this->config[$name] ?? $default;
    }

    /**
     * 创建/切换数据库连接查询.
     * @param string|null $name  连接配置标识
     * @param bool        $force 强制重新连接
     */
    public function connect(string $name = null, bool $force = false)
    {
        return $this->instance($name, $force);
    }

    /**
     * 创建数据库连接实例.
     *
     * @param string|null $name  连接标识
     * @param bool        $force 强制重新连接
     */
    protected function instance(string $name = null, bool $force = false)
    {
        if (empty($name)) {
            $name = $this->getConfig('default', 'redis');
        }
        if ($force || !isset($this->instance[$name])) {
            $this->instance[$name] = $this->createConnection($name);
        }
        return $this->instance[$name];
    }

    protected bool $isPool = false;

    public function isPool(bool $status): void{
        $this->isPool = $status;
    }

    /**
     * 获取连接配置.
     * @param string $name
     * @return array
     */
    protected function getConnectionConfig(string $name): array
    {
        $connections = $this->getConfig('connections');
        if (!isset($connections[$name])) {
            throw new InvalidArgumentException('Undefined db config:' . $name);
        }
        $data = $connections[$name];
        $type = $connectorName = $data['type'] ?? 'mysql';
        if ($this->isPool) {
            $connectorName = $type . '-pool';
        }
        $connector = $this->connectors[$connectorName] ?? $type;
        $builder = $this->builders[$type] ?? $type;
        return [
            ...$data,
            'type' => $connector,
            'builder' => $builder,
        ];
    }

    /**
     * 创建连接.
     * @param string $name
     */
    protected function createConnection(string $name)
    {
        $config = $this->getConnectionConfig($name);
        $type = !empty($config['type']) ? $config['type'] : 'redis';
        if (str_contains($type, '\\')) {
            $class = $type;
        } else {
            $class = '\\pms\\redis\\connector\\' . ucfirst($type);
        }
        return new $class($config);
    }


    /**
     * 监听SQL执行.
     *
     * @param callable $callback 回调方法
     *
     * @return void
     */
    public function listen(callable $callback): void{
        $this->listen[] = $callback;
    }

    /**
     * 获取监听SQL执行.
     *
     * @return array
     */
    public function getListen(): array
    {
        return $this->listen;
    }

    /**
     * 获取所有连接实列.
     *
     * @return array
     */
    public function getInstance(): array
    {
        return $this->instance;
    }

    /**
     * 注册回调方法.
     * @param string   $event    事件名
     * @param callable $callback 回调方法
     * @return void
     */
    public function event(string $event, callable $callback): void
    {
        $this->event[$event][] = $callback;
    }

    /**
     * 触发事件.
     *
     * @param string $event  事件名
     * @param mixed|null $params 传入参数
     * @return void
     */
    public function trigger(string $event, mixed $params = null): void
    {
        if (isset($this->event[$event])) {
            foreach ($this->event[$event] as $callback) {
                call_user_func_array($callback, [$params]);
            }
        }
    }

    public function __call($method, $args)
    {
        return call_user_func_array([$this->connect(), $method], $args);
    }

}
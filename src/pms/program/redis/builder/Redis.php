<?php

namespace pms\program\redis\builder;

class Redis
{
    const autoLock = 'auto-lock:';
    /**
     * @var string 缓存前缀
     */
    protected string $prefix;

    /**
     * @var \Redis redis实例
     */
    protected \Redis $handler;


    public function getPrefix()
    {
        return $this->prefix;
    }

    public function __construct(\Redis $redis, $prefix = "")
    {
        $this->prefix = $prefix;
        $this->handler = $redis;
    }

    /**
     * 获取当前redis实例
     * @return \Redis
     */
    public function handler(): \Redis
    {
        return $this->handler;
    }

    /**
     * 设置缓存
     * @param string $key
     * @param mixed $value
     * @param int $expire
     * @return bool
     */
    public function set(string $key, mixed $value, int $expire = 0): bool
    {
        if ($expire) {
            $this->handler->setex($key, $expire, $value);
        } else {
            $this->handler->set($key, $value);
        }
        return true;
    }

    /**
     * 获取缓存
     * @param string $key
     * @param mixed|null $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->handler->get($key);
        if (false === $value || is_null($value)) {
            return $default;
        }
        return $value;
    }

    /**
     * 删除缓存
     * @param string $key
     * @param string ...$other_keys
     * @return bool
     */
    public function delete(string $key, string ...$other_keys): bool
    {
        $result = $this->handler->del($key, ...$other_keys);
        return $result > 0;
    }


    /**
     * scan 命令用于代替 keys 使用
     * @param string $pattern
     * @param int|null $length
     * @return array
     */
    public function scanX(string $pattern, int $length = null): array
    {
        $iterator = null;
        $data = [];
        while (true) {
            $keys = $this->handler->scan($iterator, $pattern);
            if ($keys === false) {
                break;
            }
            foreach ($keys as $item) {
                if (!empty($item)) {
                    $data[] = $item;
                }
            }
            unset($keys);
            if ($length !== null && count($data) >= $length) {
                break;
            }
        }
        return $data;
    }


    /**
     * 在指定的 key 不存在时,为 key设置指定的值
     * @param string $key
     * @param $value
     * @param int $expire
     * @return array|bool
     */
    public function setnx(string $key, $value, int $expire = 0): array|bool
    {
        $status = $this->handler->setnx($key, $value);
        if ($status && $expire) {
            $this->handler->expire($key, $expire);
        }
        return $status;
    }

    /**
     * 删除文件夹下所有缓存
     * @param string $path
     * @return bool
     */
    public function deleteFolder(string $path): bool
    {
        $path = trim($path, ":");
        if (!str_ends_with($path, ":*")) {
            $path = $path . ":*";
        }
        $iterator = -1;
        while (true) {
            $keys = $this->handler->scan($iterator, $path);
            if ($keys === false) {
                break;
            }
            foreach ($keys as $item) {
                if (!empty($item)) {
                    $this->handler->del($item);
                }
            }
        }
        return true;
    }

    /**
     * Redis分布式锁 锁定
     * @param string $name 锁名称
     * @param int $occupy 占锁时间（秒）
     * @param int $pause 抢锁间隔时间
     * @return void
     */
    public function lock(string $name, int $occupy = 3, int $pause = 50): void
    {
        $name = static::autoLock . $name;
        $last = $occupy + time();
        $pause *= 1000;
        // 如果抢占失败再挂起 ($pause) 毫秒
        do {
            usleep($pause); //暂停 ($pause) 毫秒
            //防止当持有锁的进程崩溃或删除锁失败时，其他进程将无法获取到锁
            $lock_time = $this->handler->get($name);
            // 锁已过期，重置
            if ($lock_time < time()) {
                $this->handler->del($name);
            }
        } while (!$this->setnx($name, $last, $occupy));
    }

    /**
     * Redis分布式锁 解锁
     * @param string $name
     * @return void
     */
    public function unlock(string $name): void
    {
        $name = static::autoLock . $name;
        $this->handler->del($name);
    }

    /**
     * 如果缓存存在，读取缓存，如果不存在，Redis抢占式创建缓存（在并发情况下,只会有一个进程创建缓存,其余进程阻塞等待）
     * @param string $name
     * @param \Closure $callback
     * @param int $expireTime 缓存过期时间
     * @return mixed
     */
    public function setnxCache(string $name, \Closure $callback, int $expireTime = 0, int $retryCount = 10): mixed
    {
        $data = $this->get($name);
        if (empty($data)) {
            $lockName = static::autoLock . $name;
            // 当前进程进行设置缓存
            if ($this->setnx($lockName, (time() + 3), 5)) {
                $data = $callback(function ($number) use (&$expireTime) {
                    $expireTime = $number;
                });
                if ($data !== false && $data !== null && $data !== "") {
                    $this->set($name, json_encode($data, 320), $expireTime);
                    $this->handler->del($lockName);
                }
            } else {
                // 其他进程等待缓存的加载
                $count = 0; // 等待的次数
                usleep(100000);
                while (empty($this->get($name))) {
                    // 如果循环了5次还没有等到结果（100毫秒 * $retryCount (默认10)），则判定去取数据的进程死亡（代码报错）
                    if ($count > $retryCount) {
                        throw new \RedisException("请求终止");
                        // 杀死所有进程（避免浪费服务器资源）
                    }
                    usleep(100000);
                    $count++;
                };
                $data = $this->handler->get($name);
            }
        } else {
            $data = json_decode($data, true);
        }
        return $data;
    }


    /**
     * 返回List的所有项。
     * @param string $key
     * @return array
     */
    public function LRangeAll(string $key): array
    {
        return $this->handler->lRange($key, 0, -1);
    }

    /**
     * 返回List指定个数的项。
     * @param string $key
     * @param int $end
     * @return array
     */
    public function LRangeLen(string $key, int $end): array
    {
        return $this->handler->lRange($key, 0, $end);
    }


}
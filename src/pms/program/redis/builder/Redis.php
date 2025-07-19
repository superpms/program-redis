<?php

namespace pms\program\redis\builder;

class Redis
{
    /**
     * @var string 缓存前缀
     */
    protected string $prefix;

    /**
     * @var \Redis redis实例
     */
    protected \Redis $handler;

    /**
     * 获取真实缓存名称
     * @access public
     * @param $key
     * @return string
     */
    public function cacheKey($key): string{
        // 如果传入的名称已存在前缀
        if (str_starts_with($key, $this->prefix)) {
            return $key;
        }
        return $this->prefix.$key;
    }

    public function __construct(\Redis $redis, $prefix = "")
    {
        $this->prefix = $prefix;
        $this->handler = $redis;
    }

    /**
     * 设置缓存
     * @param string $name
     * @param mixed $value
     * @param int $expire
     * @return bool
     */
    public function set(string $name, mixed $value, int $expire = 0): bool
    {
        $key = $this->cacheKey($name);
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
        $key = $this->cacheKey($key);
        $value = $this->handler->get($key);
        if (false === $value || is_null($value)) {
            return $default;
        }
        return $value;
    }

    /**
     * 删除缓存
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        $key = $this->cacheKey($key);
        $result = $this->handler->del($key);
        return $result > 0;
    }

    /**
     * 找所有符合给定模式 pattern 的 key
     * @param string $keys
     * @return string[]|false
     */
    public function keys(string $keys): array|false
    {
        $key = $this->cacheKey($keys);
        return $this->handler->keys($key);
    }

    /**
     * 命令用于代替 keys 使用
     * @param string $key
     * @param int|null $length
     * @return array
     */
    public function scan(string $key, int $length = null): array{
        $key = $this->cacheKey($key);
        $iterator = -1;
        $data = [];
        while (true) {
            $keys = $this->handler->scan($iterator, $key);
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
     * 设置缓存过期时间
     * @param string $key
     * @param int $ttl
     * @return bool
     */
    public function expire(string $key, int $ttl): bool
    {
        $key = $this->cacheKey($key);
        return $this->handler->expire($key,$ttl);
    }

    /**
     * 获取缓存过期时间
     * @param string $key
     * @return bool|int
     */
    public function ttl(string $key): bool|int
    {
        $key = $this->cacheKey($key);
        return $this->handler->ttl($key);
    }

    /**
     * 在指定的 key 不存在时,为 key设置指定的值
     * @param string $key
     * @param $value
     * @param int $expire
     * @return array|bool
     */
    public function setnx(string $key, $value,int $expire = 0): array|bool
    {
        $key = $this->cacheKey($key);
        $status = $this->handler->setnx($key, $value);
        if($status && $expire){
            $this->handler->expire($key,$expire);
        }
        return $status;
    }

    /**
     * 删除文件夹下所有缓存
     * @param string $path
     * @return bool
     */
    public function deleteFolder(string $path): bool{
        $path = trim($path, ":");
        $path = $this->cacheKey($path);
        if(!str_ends_with($path,":*")){
            $path = $path.":*";
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
        $name = 'lock:' . $name;
        $name = $this->cacheKey($name);
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
        } while (!$this->handler->setnx($name, $last));
        $this->handler->expire($name,$occupy);
    }

    /**
     * Redis分布式锁 解锁
     * @param string $name
     * @return void
     */
    public function unlock(string $name): void
    {
        $name = 'lock:'.$name;
        $name = $this->cacheKey($name);
        $this->handler->del($name);
    }

    /**
     * 如果缓存存在，读取缓存，如果不存在，Redis抢占式创建缓存（在并发情况下,只会有一个进程创建缓存,其余进程阻塞等待）
     * @param string $name
     * @param \Closure $callback
     * @param int $expireTime 缓存过期时间
     * @return mixed
     */
    public function setnxDCS(string $name, \Closure $callback, int $expireTime = 0): mixed
    {
        $name = $this->cacheKey($name);
        $data = $this->handler->get($name);
        if (empty($data)) {
            $lockName = $this->cacheKey('lock:'.$name);
            // 当前进程进行设置缓存
            if($this->handler->setnx($lockName, (time() + 3))){
                $this->handler->expire($lockName,5);
                $data = $callback(function ($number) use(&$expireTime){
                    $expireTime = $number;
                });
                if($data !== false && $data !== null && $data !== ""){
                    $this->set($name,json_encode($data, 320) , $expireTime);
                    $this->handler->del($lockName);
                }
            }else{
                // 其他进程等待缓存的加载
                $count = 0; // 等待的次数
                usleep(100000);
                while(empty($this->handler->get($name))){
                    // 如果循环了5次还没有等到结果（100毫秒 * 10），则判定去取数据的进程死亡（代码报错）
                    if($count > 10){
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
     * 判断 key 是否存在
     * @param string $key
     * @param ...$other_keys
     * @return bool|int
     */
    public function exists(string $key, ...$other_keys): bool|int{
        $key = $this->cacheKey($key);
        return $this->handler->exists($key,...$other_keys);
    }

    /**
     * 将字符串值添加到列表的开头（左侧）。
     * @param string $key
     * @param ...$value1
     * @return int|bool
     */
    public function lPush(string $key, ...$value1): int|bool
    {
        $key = $this->cacheKey($key);
        return $this->handler->lPush($key,...$value1);
    }

    /**
     * 将字符串值添加到列表的开头（右侧）。
     * @throws \RedisException
     */
    public function rPush(string $key, ...$value1): int|bool
    {
        $key = $this->cacheKey($key);
        return $this->handler->rPush($key,...$value1);
    }

    /**
     * 弹出（返回并删除）列表的第一个元素（左边）。
     * @return mixed
     */
    public function lPop(string $key): mixed{
        $key = $this->cacheKey($key);
        return $this->handler->lPop($key);
    }

    /**
     * 弹出（返回并删除）列表的最后一个元素（右边）。
     * @param string $key
     * @param $count
     * @return mixed
     * @throws \RedisException
     */
    public function rPop(string $key): mixed{
        $key = $this->cacheKey($key);
        return $this->handler->rPop($key);
    }


    /**
     * 返回由键标识的列表的大小。如果该列表不存在或为空，则该命令返回0。如果Key标识的数据类型不是列表，则命令返回FALSE。
     * @param string $key
     * @return int|bool
     */
    public function lLen(string $key): int|bool
    {
        $key = $this->cacheKey($key);
        return $this->handler->lLen($key);
    }

    /**
     * 返回List的所有项。
     * @param string $key
     * @return array
     */
    public function LRangeAll(string $key): array
    {
        return $this->LRange($key, 0, -1);
    }

    /**
     * 返回List指定个数的项。
     * @param string $key
     * @return array
     */
    public function LRangeLength(string $key, int $end): array
    {
        return $this->LRange($key, 0, $end);
    }

    /**
     * 截取 List指定位置的项。
     * @param string $key
     * @return array
     */
    public function LRange(string $key,int $start, int $end): array
    {
        $key = $this->cacheKey($key);
        // 获取redis list表的所有项
        return $this->handler->lRange($key, $start, $end);
    }

    /**
     * 将值添加到存储在键处的哈希中。如果该值已经在哈希中，则返回FALSE。
     * @param $key
     * @param $hashKey
     * @param $value
     * @return bool|int
     */
    public function hSet($key, $hashKey, $value): bool|int{
        $key = $this->cacheKey($key);
        return $this->handler->hset($key, $hashKey, $value);
    }

    /**
     * 返回哈希的长度（以项数为单位）。
     * @param $key
     * @return bool|int
     */
    public function hLen($key): bool|int{
        $key = $this->cacheKey($key);
        return $this->handler->hLen($key);
    }

    /**
     * 返回哈希表指定行的值
     * @param $key
     * @param $hashKey
     * @return bool|string
     */
    public function hGet($key, $hashKey): bool|string{
        $key = $this->cacheKey($key);
        return $this->handler->hget($key, $hashKey);
    }

    /**
     * 返回哈希表所有行
     * @param $key
     * @return bool|array
     */
    public function hGetAll($key): bool|array{
        $key = $this->cacheKey($key);
        return $this->handler->hGetAll($key);
    }

    /**
     * 删除哈希表指定行
     * @param $key
     * @param $hashKey
     * @return bool|int
     */
    public function hDel($key, $hashKey): bool|int{
        $key = $this->cacheKey($key);
        return $this->handler->hdel($key, $hashKey);
    }

}
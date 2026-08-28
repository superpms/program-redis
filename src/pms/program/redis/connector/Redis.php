<?php

namespace pms\program\redis\connector;

use pms\program\redis\RedisConfig;
use \Redis as handler;

class Redis
{
    public RedisConfig $config;
    protected string $prefix = '';
    protected ?handler $redis = null;

    public function __construct(array $config)
    {
        $this->config = new RedisConfig($config);
    }


    protected function connect(): handler
    {
        $this->prefix = $this->config->getPrefix();
        try {
            $redis = new handler();
        } catch (\Throwable $e) {
            throw new \Exception('Redis扩展 未安装');
        }
        $arguments = [
            $this->config->getHost(),
            $this->config->getPort(),
        ];
        if ($this->config->getConnectTimeout() !== 0.0) {
            $arguments[] = $this->config->getConnectTimeout();
        }
        if ($this->config->getRetryInterval() !== 0) {
            $arguments[] = null;
            $arguments[] = $this->config->getRetryInterval();
        }
        $redis->connect(...$arguments);
        if ($this->config->getPassword()) {
            $redis->auth($this->config->getPassword());
        }
        if ($this->config->getDatabase() !== 0) {
            $redis->select($this->config->getDatabase());
        }
        if ($this->config->getPrefix() !== '') {
            $redis->setOption(handler::OPT_PREFIX, $this->config->getPrefix());
        }
        if ($this->config->getReadTimeout() !== 0.0) {
            $redis->setOption(handler::OPT_READ_TIMEOUT, $this->config->getReadTimeout());
        }

        foreach ($this->config->getOptions() as $key => $value) {
            $redis->setOption($key, $value);
        }
        return $redis;
    }

    public function __call(string $name, array $arguments)
    {
        if (class_exists('\pms\program\redis\builder\Redis')) {
            try {
                return $this->dispatchCommand($name, $arguments);
            } catch (\RedisException $e) {
                // 命中断线特征说明当前连接已死：丢弃连接重建后重放一次本次命令，坏连接不再常驻
                if (!$this->isBreak($e)) {
                    throw $e;
                }
                $this->abandon();
                return $this->dispatchCommand($name, $arguments);
            }
        }
        throw new \Exception('Redis ' . $name . ' 方法不存在');

    }

    /**
     * 懒加载连接并在当前连接上执行一条命令。
     */
    protected function dispatchCommand(string $name, array $arguments)
    {
        if ($this->redis === null) {
            $this->redis = $this->connect();
        }
        $isolate = function () {
            $this->abandon();
        };
        $class = new \ReflectionClass('\pms\program\redis\builder\Redis');
        $ins = $class->newInstance($this->redis, $isolate);
        return call_user_func_array([$ins, $name], $arguments);
    }

    /**
     * 判断异常是否为连接断线特征。
     */
    protected function isBreak(\Throwable $throwable): bool
    {
        $message = strtolower($throwable->getMessage());
        foreach (['went away', 'connection lost', 'connection closed', 'error while sending', 'closed the connection'] as $flag) {
            if (str_contains($message, $flag)) {
                return true;
            }
        }
        return false;
    }

    protected function abandon(): void
    {
        if ($this->redis == null) {
            return;
        }
        $this->redis->close();
        $this->redis = null;
    }

    public function close()
    {
       $this->abandon();
    }

    public function __destruct()
    {
        $this->close();
    }

}
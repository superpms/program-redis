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
        $className = '\pms\program\redis\builder\Redis';
        if ($this->redis == null) {
            $this->redis = $this->connect();
        }
        if (class_exists($className)) {
            $isolate = function () {
                $this->abandon();
            };
            $class = new \ReflectionClass($className);
            $ins = $class->newInstance($this->redis,$isolate);
            return call_user_func_array([$ins, $name], $arguments);
        }
        throw new \Exception('Redis ' . $name . ' 方法不存在');

    }

    protected function abandon(): void
    {
        if ($this->redis == null) {
            return;
        }
        try{
            $this->redis->close();
        }catch (\Throwable $e){}
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
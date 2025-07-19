<?php

namespace pms\program\redis\connector;

use pms\program\redis\RedisConfig;
use \Redis as handle;

class Redis
{
    public RedisConfig $config;
    protected string $prefix = '';
    protected ?handle $redis = null;

    public function __construct(array $config){
        $this->config = new RedisConfig($config);
    }


    protected function connect(): handle
    {
        $this->prefix = $this->config->getPrefix();
        try {
            $redis = new handle();
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
        if ($this->config->getReadTimeout() !== 0.0) {
            $arguments[] = $this->config->getReadTimeout();
        }
        $redis->connect(...$arguments);
        if ($this->config->getPassword()) {
            $redis->auth($this->config->getPassword());
        }
        if ($this->config->getDatabase() !== 0) {
            $redis->select($this->config->getDatabase());
        }
        foreach ($this->config->getOptions() as $key => $value) {
            $redis->setOption($key, $value);
        }
        return $redis;
    }

    public function __call(string $name, array $arguments){
        $className = '\pms\redis\builder\Redis';
        if($this->redis == null){
            $this->redis = $this->connect();
        }

        if (class_exists($className)) {
            $class = new \ReflectionClass($className);
            $ins = $class->newInstance($this->redis, $this->prefix);
            return call_user_func_array([$ins, $name], $arguments);
        } else {
            if (!method_exists($this->redis, $name)) {
                throw new \Exception('Redis 方法不存在');
            }
            return call_user_func_array([$this->redis, $name], $arguments);
        }
    }


    public function close(){
        $this->redis->close();
        $this->redis = null;
    }

    public function __destruct()
    {
        $this->close();
    }

}
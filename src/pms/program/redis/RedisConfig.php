<?php

namespace pms\program\redis;

class RedisConfig
{

    protected string $host;
    protected int $port;
    protected float $connect_timeout;
    protected float $retry_interval;
    protected float $read_timeout;
    protected int $retry_times;
    protected string $password;
    protected int $database = 0;
    protected string $prefix = '';
    protected int $pool_count = 64;
    protected int $pool_wait_time = 0;
    protected array $options = [];

    public function __construct(array $config = []){
        if(!empty($config)){
            foreach ($config as $key => $value){
                if(property_exists($this, $key)){
                    $this->$key = $value;
                }
            }
        }
    }

    public function getHost(): string
    {
        return $this->host;
    }
    public function setHost(string $host): self
    {
        $this->host = $host;
        return $this;
    }

    public function getPort(): int
    {
        return $this->port;
    }
    public function setPort(int $port): self
    {
        $this->port = $port;
        return $this;
    }

    public function getConnectTimeout(): float
    {
        return $this->connect_timeout;
    }
    public function setConnectTimeout(float $timeout): self
    {
        $this->connect_timeout = $timeout;
        return $this;
    }

    public function getRetryInterval(): int
    {
        return $this->retry_interval;
    }
    public function setRetryInterval(int $retry_interval): self
    {
        $this->retry_interval = $retry_interval;
        return $this;
    }

    public function getReadTimeout(): float
    {
        return $this->read_timeout;
    }
    public function setReadTimeout(int $read_timeout): self
    {
        $this->read_timeout = $read_timeout;
        return $this;
    }
    public function getRetryTimes(): int
    {
        return $this->retry_times;
    }
    public function setRetryTimes(int $retry_times): self
    {
        $this->retry_times = $retry_times;
        return $this;
    }
    public function getPassword(): string
    {
        return $this->password;
    }
    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }
    public function getDatabase(): int
    {
        return $this->database;
    }
    public function setDatabase(int $database): self
    {
        $this->database = $database;
        return $this;
    }
    public function getPrefix(): string
    {
        return $this->prefix;
    }
    public function setPrefix(string $prefix): self
    {
        $this->prefix = $prefix;
        return $this;
    }
    public function getPoolCount(): int
    {
        return $this->pool_count;
    }
    public function setPoolCount(int $pool_count): self
    {
        $this->pool_count = $pool_count;
        return $this;
    }
    public function getOptions(): array
    {
        return $this->options;
    }
    public function setOptions(array $options): self
    {
        $this->options = $options;
        return $this;
    }

    public function getPoolWaitTime(): int
    {
        return $this->pool_wait_time;
    }

    public function setPoolWaitTime(int $pool_wait_time): self
    {
        $this->pool_wait_time = $pool_wait_time;
        return $this;
    }
}
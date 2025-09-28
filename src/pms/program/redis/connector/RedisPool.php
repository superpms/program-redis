<?php

namespace pms\program\redis\connector;

use Swoole\ConnectionPool;

class RedisPool extends Redis
{
    protected ?ConnectionPool $pool = null;

    protected function connect(): \Redis
    {
        if ($this->pool === null) {
            $this->pool = new ConnectionPool(function () {
                return parent::connect();
            }, $this->config->getPoolCount());
        }
        return $this->getRealConn();
    }

    protected function getRealConn()
    {
        $pdo = $this->pool->get();
        $wait_time = $this->config->getPoolWaitTime() ?? 0;
        if($wait_time !== 0){
            if (isset($pdo->last_time) && $pdo->last_time <= time()) {
                $pdo = null;
                $this->pool->put($pdo);
                $pdo = $this->getRealConn();
            } else {
                $pdo->last_time = time() + ($wait_time);
            }
        }
        return $pdo;
    }

    public function close(): void
    {
        $this->pool->put($this->redis);
        $this->redis = null;
    }


}
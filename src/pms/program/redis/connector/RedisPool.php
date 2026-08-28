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
                // 闲置过期的连接按坏连接协议归还null，池会丢弃它并补建新连接
                $this->pool->put(null);
                $pdo = $this->getRealConn();
            } else {
                @$pdo->last_time = time() + ($wait_time);
            }
        }
        return $pdo;
    }

    /**
     * 丢弃当前坏连接：按坏连接协议归还null触发计数回退与补建，不放回池中复用。
     */
    protected function abandon(): void
    {
        if ($this->redis === null) {
            return;
        }
        $this->redis = null;
        if ($this->pool !== null) {
            $this->pool->put(null);
        }
    }

    public function close(): void
    {
        if ($this->redis !== null) {
            $this->pool->put($this->redis);
            $this->redis = null;
        }
    }


}

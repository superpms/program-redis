<?php
\pms\hook\LifecycleHook::mount(function () {
    $dbConfig = config('redis');
    if ($dbConfig !== null) {
        \pms\facade\RDb::setConfig($dbConfig);
    }
});

if (in_swoole()) {
    if (defined(SWOOLE_HTTP_LIFECYCLE_START)) {
        \pms\hook\SwooleHttpLifecycleHook::mount(SWOOLE_HTTP_LIFECYCLE_START, function () {
            \pms\facade\RDb::isPool( true);
        });
        \pms\hook\SwooleHttpLifecycleHook::mount(SWOOLE_HTTP_LIFECYCLE_REQUEST_DESTRUCT, function () {
            try {
                prdb_pool_autoclose();
            } catch (\Throwable $e) {
                echo "Redis连接池归还错误：" . $e->getMessage() . "\r\n";
            }
        });
    }
}
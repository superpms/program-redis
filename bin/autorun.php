<?php
if (class_exists('\pms\hook\LifecycleHook')) {
    \pms\hook\LifecycleHook::mount(LIFECYCLE_BOOT, function () {
        $dbConfig = config('redis');
        if ($dbConfig !== null) {
            \pms\facade\RDb::setConfig($dbConfig);
        }
    });
}
if (in_swoole()) {
    if (class_exists('\pms\hook\SwooleHttpLifecycleHook')) {
        \pms\hook\SwooleHttpLifecycleHook::mount(LIFECYCLE_BOOT, function () {
            \pms\facade\RDb::isPool(true);
        });
        \pms\hook\SwooleHttpLifecycleHook::mount(SWOOLE_LIFECYCLE_HTTP_REQUEST_DESTRUCT, function () {
            try {
                prdb_pool_autoclose();
            } catch (\Throwable $e) {
                echo "Redis连接池错误：" . $e->getMessage() . "\r\n";
            }
        });
    }
}
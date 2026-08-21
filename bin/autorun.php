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
    $enablePool = function () {
        \pms\facade\RDb::isPool(true);
    };
    if (class_exists('\pms\hook\HttpLifecycleHook')) {
        \pms\hook\HttpLifecycleHook::mount(LIFECYCLE_BOOT, $enablePool);
        \pms\hook\HttpLifecycleHook::mount(LIFECYCLE_SANDBOX_DESTRUCT, function () {
            try {
                prdb_pool_autoclose();
            } catch (\Throwable $e) {
                echo "Redis连接池错误：" . $e->getMessage() . "\r\n";
            }
        });
    }
    if (class_exists('\pms\hook\SwooleNewsletterLifecycleHook')) {
        \pms\hook\SwooleNewsletterLifecycleHook::mount(LIFECYCLE_BOOT, $enablePool);
    }
}
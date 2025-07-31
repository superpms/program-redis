<?php

namespace pms;

use pms\facade\RDb;

abstract class RedisQueue{

    protected static function getName(): string
    {
        return str_replace('\\', '_', get_called_class());
    }

    /**
     * 将字符串值添加到列表的开头（左侧）。如果键不存在，则创建列表。
     * @param ...$items
     * @return bool|mixed
     */
    public static function lPush(...$items): mixed
    {
        return RDb::lPush(static::getName(), ...$items);
    }

    // 将字符串值添加到列表的结尾（右侧）
    public static function rPush(...$items){
        return RDb::rPush(static::getName(), ...$items);
    }

    /**
     * 返回并删除列表的第一个元素。
     * @return bool|mixed
     */
    public static function lPop(): mixed
    {
        return RDb::lPop(static::getName());
    }


    /**
     * 返回并删除列表的最后一个元素。
     * @return bool|mixed
     */
    public static function rPop(): mixed
    {
        return RDb::rPop(static::getName());
    }

}
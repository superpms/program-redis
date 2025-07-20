<?php
function prdb_pool_autoclose(): void{
    $connector = \pms\facade\RDb::getInstance();
    if(!empty($connector)){
        foreach ($connector as $value){
            $value->close();
        }
    }
}
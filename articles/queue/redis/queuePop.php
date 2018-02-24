<?php

$redis = new Redis();
$redis->connect('127.0.0.1');
$name = 'flux';
while (1) {
    $data = $redis->blPop(array($name), 10);
    if ($data == false) { // 没有数据
        sleep(1);
    } else { // 处理业务
        print_r($data);
    }
}
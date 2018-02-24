<?php

include_once("httpsqs_client.php");

$httpsqs = new httpsqs('127.0.0.1');
$name = 'flux';
while (1) {
    $data = $httpsqs->get($name);
    if ($data == false) { // 没有数据
        sleep(1);
    } else { // 处理业务
        print_r($data);
    }
}




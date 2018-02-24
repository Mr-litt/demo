<?php

require 'vendor/autoload.php';

use Beanstalk\Client;

$defaults = [
    'persistent' => true, // 长连接
    'host' => '127.0.0.1',
    'port' => 11300,
    'timeout' => 1, // 连接超时时间
    'logger' => null
];

$beanstalk = new Client($defaults);
$beanstalk->connect();
$beanstalk->watch('flux');

while (true) {
    $job = $beanstalk->reserve(); // 堵塞直到有新的job，job结构：['id' => 123, 'body' => '放入第一个内容']

    // 业务处理job
    echo $job['body'];
    $result = false;

    if ($result) { // 处理成功，删除
        $beanstalk->delete($job['id']);
    } else { // 处理未成功，休眠，如防止规定时间后job未处理会变成ready被重复处理
        $beanstalk->bury($job['id'], 23);
    }
}
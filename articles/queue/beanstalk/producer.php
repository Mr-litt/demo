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
$beanstalk->useTube('flux'); // 使用 tube `'flux'`.
$beanstalk->put(
    23, // 优先级
    0,  // 延时
    60, // 任务处理时间
    '放入第一内容' // 数据
);
$beanstalk->disconnect();
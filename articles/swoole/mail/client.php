<?php

$client = new swoole_client(SWOOLE_SOCK_TCP);
if (!$client->connect('127.0.0.1', 9501, -1))
{
    exit("connect failed. Error: {$client->errCode}\n");
}
$data = [
    'address' => '137057181@qq.com',
    'subject' => '消息提醒',
    'body' => '消息提醒：测试信息主体'
];
$client->send(json_encode($data));
echo $client->recv();
$client->close();
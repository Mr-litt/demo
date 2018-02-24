<?php

// 生产者：创建连接-->创建channel-->创建交换机对象-->发送消息

// 创建连接
$config = array(
    'host' => '127.0.0.1',
    'port' => '5672',
    'login' => 'guest',
    'password' => 'guest',
    'vhost'=>'/'
);
$conn = new AMQPConnection($config);
if (!$conn->connect()) {
    die("不能创建连接!\n");
}
// 创建信道
$channel = new AMQPChannel($conn);
// 创建交换机对象
$ex = new AMQPExchange($channel);
$exName = 'exName';
$ex->setName($exName); //设置交换机名称
$ex->setType(AMQP_EX_TYPE_DIRECT); // direct类型
$ex->setFlags(AMQP_DURABLE); // 持久化
$ex->declareExchange(); // 声明一个新交换机，如果已经存在，则不再需要声明

//发送消息
$route = 'key_1'; // 路由key
//$channel->startTransaction(); //开始事务
for($i=5; $i<10; ++$i){
    //sleep(1);//休眠1秒
    $ex->publish('放入一个内容' . $i, $route);
}
//$channel->commitTransaction(); //提交事务
$conn->disconnect();

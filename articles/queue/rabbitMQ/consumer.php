<?php

// 消费者：创建连接-->创建channel-->创建交换机-->创建队列-->绑定交换机/队列/路由键-->接收消息

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
$ex->setName($exName); // 设置交换机名称
$ex->setType(AMQP_EX_TYPE_DIRECT); // direct类型
$ex->setFlags(AMQP_DURABLE); // 持久化
//$ex->declareExchange(); // 声明一个新交换机，如果已经存在，则不再需要声明

// 创建队列
$q = new AMQPQueue($channel);
$qName = 'qName';
$q->setName($qName); // 设置队列名称
$q->setFlags(AMQP_DURABLE); //持久化
$q->declareQueue(); // 声明一个新队列，如果已经存在，则不再需要声明

// 绑定交换机与队列，并指定路由键
$route = 'key_1'; // 路由key
$q->bind($exName, $route);

// 堵塞接受消息
while(True){
    $q->consume('processMessage');
    //$q->consume('processMessage', AMQP_AUTOACK); //自动ACK应答
}
$conn->disconnect();

/**
 * 消费回调函数
 * 处理消息
 */
function processMessage($envelope, $queue) {
    $msg = $envelope->getBody();
    echo $msg."\n"; //处理消息
    $queue->ack($envelope->getDeliveryTag()); //手动发送ACK应答
}
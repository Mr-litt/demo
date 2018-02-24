<?php

$rk = new RdKafka\Producer();
$rk->setLogLevel(LOG_DEBUG);
$rk->addBrokers("127.0.0.1"); // 集群使用127.0.0.1,127.0.0.2的形式

$topic = $rk->newTopic("flux"); // 设置topic

for ($i = 0; $i < 5; $i++) {
    $topic->produce(
        RD_KAFKA_PARTITION_UA, // 分区，rd_kafka_partition_ua代表随机分区
        0, // 消息标志，始终为0
        '放入一个内容' . $i // 内容
    );
}



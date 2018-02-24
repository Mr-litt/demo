<?php

$conf = new RdKafka\Conf();
$conf->set('group.id', 'group1'); // 设置分组ID
$rk = new RdKafka\Consumer($conf);
$rk->setLogLevel(LOG_DEBUG);
$rk->addBrokers("127.0.0.1");

$topicConf = new RdKafka\TopicConf(); // 设置自动上传偏移量信息
$topicConf->set("auto.commit.interval.ms", 1e3);
$topicConf->set("offset.store.sync.interval.ms", 60e3);
$topic = $rk->newTopic("flux", $topicConf);

$topic->consumeStart(
    0, // 从哪个分区开始消费
    RD_KAFKA_OFFSET_STORED // 开始消费的偏移量，可选值：RD_KAFKA_OFFSET_BEGINNING, RD_KAFKA_OFFSET_END, RD_KAFKA_OFFSET_STORED.
);

while (true) {
    $msg = $topic->consume(
        0, // 分区
        1000 // 超时时间
    );
    if (empty($msg) || $msg->err) { // 当前队列中无数据
        sleep(1);
    } else { // 业务处理
        echo $msg->payload, "\n";
    }
}
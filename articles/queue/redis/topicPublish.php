<?php

$redis = new Redis();
$redis->connect('127.0.0.1');
$channel = 'flux';
$message = '放入第一个内容';
$redis->publish($channel, $message);
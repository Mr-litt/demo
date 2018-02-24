<?php

$redis = new Redis();
$redis->connect('127.0.0.1');
$name = 'flux';
$val = '放入第一个内容';
$redis->rPush($name, $val);
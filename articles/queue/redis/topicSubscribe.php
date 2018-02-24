<?php

$redis = new Redis();
$redis->connect('127.0.0.1');
$channel = 'flux';
$redis->subscribe(array($channel), 'callback');
function callback($redis, $chan, $msg){
    echo $msg;
}
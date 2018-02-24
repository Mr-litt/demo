<?php

include_once("httpsqs_client.php");

$httpsqs = new httpsqs('127.0.0.1');
$name = 'flux';
$val = '放入第一个内容';
$httpsqs->put($name, $val);

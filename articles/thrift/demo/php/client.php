<?php

/*// 使用composer类加载器
define('ROOT', dirname(__FILE__));
require 'vendor/autoload.php';
require ROOT . '/gen-php/Services/UserService/Types.php';
require ROOT . '/gen-php/Services/UserService/UserService.php';*/

// 使用thrift定义的类加载器
require_once __DIR__ . '/vendor/apache/Thrift/lib/php/lib/Thrift/ClassLoader/ThriftClassLoader.php';
use Thrift\ClassLoader\ThriftClassLoader;
$genDir = realpath(__DIR__.'/gen-php');
$thriftDir = __DIR__ . '/vendor/apache/Thrift/lib/php/lib';
$loader = new ThriftClassLoader();
$loader->registerNamespace('Thrift', $thriftDir);
$loader->registerDefinition('Services', $genDir); //生成的类和文件名不一致使用registerDefinition
$loader->register();

use Thrift\Transport\THttpClient;
use Thrift\Protocol\TBinaryProtocol;
use Thrift\Transport\TBufferedTransport;
use Thrift\Transport\TSocket;
use Thrift\Transport\TFramedTransport;

try {

    if (array_search('--http', $argv)) {
        $socket = new THttpClient('localhost', 80, '/php/PhpServer.php'); // 服务端使用http提供服务时使用http连接
    } else {
        $socket = new TSocket('localhost', 9090); // 服务端使用socket提供服务时使用socket连接
    }

    //$socket->setDebug(true); // 设置debug模式
    //$socket->setRecvTimeout(30000); //设置通信超时时间为30秒，源码默认为750毫秒
    //$transport = new TFramedTransport($socket); //传输层协议
    $transport = new TBufferedTransport($socket, 1024, 1024); //传输层协议
    $protocol = new TBinaryProtocol($transport); //数据层协议
    $transport->open(); // 打开连接

    $client = new \Services\UserService\userServiceClient($protocol);

    // 获取用户信息
    $reqInfo = array(
        'uidList' => array(1, 2),
    );
    $userListReq = new \Services\UserService\UserListReq($reqInfo);
    $result = $client->userList($userListReq);
    var_dump($result);
} catch (\Thrift\Exception\TTransportException $e) { // 连接异常
    echo " 连接异常：{$e->getMessage()}";
} catch (\Services\UserService\ApiErrorException $e) { // 定义文件自定义异常
    echo "自定义异常码：{$e->errCode}，自定义异常信息：{$e->errMsg}";
} catch (\Thrift\Exception\TException $e) {
    echo  "其它异常：{$e->getMessage()}";
} catch (\Exception $e) {
    echo  "异常：{$e->getMessage()}";
}
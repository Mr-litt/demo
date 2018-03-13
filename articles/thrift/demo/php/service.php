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

use Thrift\Protocol\TBinaryProtocol;
use Thrift\Transport\TBufferedTransport;
use Thrift\Transport\TPhpStream;

class Handler implements \Services\UserService\UserServiceIf{

    /**
     * 获取用户信息
     * @param $uidList
     * @return array
     */
    private function getUserByUidArr($uidList)
    {
        $allUser = array(
            1 => array(
                'uid' => 1,
                'name' => '用户NO1',
                'sex' => 1,
                'age' => 18,
                'nick' => '用户昵称1'
            ),
            2 => array(
                'uid' => 2,
                'name' => '用户NO2',
                'sex' => 2,
                'age' => 19,
                'nick' => '用户昵称2'
            ),
        );

        $userArr = array();
        foreach ($uidList as $uid) {
            isset($allUser[$uid]) && $userArr[] = $allUser[$uid];
        }

        return $userArr;
    }

    /**
     * @param \Services\UserService\UserListReq $req
     * @return \Services\UserService\UserListResp
     * @throws \Services\UserService\ApiErrorException
     */
    public function userList(\Services\UserService\UserListReq $req)
    {

        try {
            $uidList = $req->uidList;
            if (empty($uidList)) {
                throw new Exception('参数错误', \Services\UserService\ErrCodeEnum::PARAM_ERROR);
            }
            $userArr = $this->getUserByUidArr($uidList);
            if (empty($userArr)) {
                throw new Exception('服务器错误', \Services\UserService\ErrCodeEnum::SERVER_ERROR);
            }

            $list = array();
            foreach ($userArr as $user) {
                $userInfo = new \Services\UserService\UserInfo($user);
                $list[] =  $userInfo;
            }

            $userList['lists'] = $list;
            $result = new \Services\UserService\UserListResp($userList);
            return $result;
        } catch (Exception $e) {
            $errInfo = array(
                'errCode' => $e->getCode(),
                'errMsg' => $e->getMessage()
            );
            throw new \Services\UserService\ApiErrorException($errInfo); // 抛出自定义错误
        }
    }
}

$handler = new Handler(); // 处理程序
$processor = new \Services\UserService\UserServiceProcessor($handler);
$transport = new TBufferedTransport(new TPhpStream(TPhpStream::MODE_R | TPhpStream::MODE_W));
$protocol = new TBinaryProtocol($transport);
$transport->open();
$processor->process($protocol, $protocol);

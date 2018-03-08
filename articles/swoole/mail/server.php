<?php

$object = new MailServer();

$setting = [
    'log_file' => 'swoole.log',
    'worker_num' => 4, // 4个工作进程
    'task_worker_num' => 10, // 10个任务进程
];
$server = new swoole_server("127.0.0.1", 9501);
$server->set($setting);

$server->on('WorkerStart', array($object, 'onWorkerStart'));
$server->on('Connect', array($object, 'onConnect'));
$server->on('Receive', array($object, 'onReceive'));
$server->on('Close', array($object, 'onClose'));
$server->on('Task', array($object, 'onTask'));
$server->on('Finish', array($object, 'onFinish'));

$server->start();

class MailServer
{
    /** @var Mail */
    private $handle;

    public function __construct()
    {
        require 'Mail.php'; // PHPMailer邮件服务类
    }

    public function onWorkerStart($server, $workerId)
    {
        $mailConfig = require 'MailConfig.php'; // 发件人信息，重启时会重新加载配置文件
        $this->handle = new Mail($mailConfig);
    }

    public function onConnect($server, $fd, $reactorId)
    {

    }

    public function onReceive($server, $fd, $reactorId, $data)
    {
        $return = [];
        $dataArr = json_decode($data, true);
        if (empty($dataArr) || empty($dataArr['address']) || empty($dataArr['subject']) || empty($dataArr['body'])) {
            $return['code'] = -1;
            $return['msg'] = '参数不能为空';
        } else { // 参数校验成功
            $server->task($data); // 投递一个任务
            $return['code'] = 0;
            $return['msg'] = '投递任务成功';
        }
        $server->send($fd, json_encode($return));
    }

    public function onTask($server, $taskId, $workerId, $data)
    {
        $data = json_decode($data, true);
        $this->handle->send($data['address'], $data['subject'], $data['body']); // 发送邮件
    }

    public function onFinish($server, $task_id, $data)
    {

    }

    public function onClose($server, $fd, $reactorId)
    {

    }
}

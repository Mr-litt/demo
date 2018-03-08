<?php

$object = new ChatServer();

$setting = [
    'log_file' => 'swoole_ws.log',
    'worker_num' => 4, // 4个工作进程
];
$ws = new swoole_websocket_server("127.0.0.1", 9502);
$ws->set($setting);

$ws->on('WorkerStart', array($object, 'onWorkerStart'));
$ws->on('open', array($object, 'onOpen'));
$ws->on('message', array($object, 'onMessage'));
$ws->on('close', array($object, 'onClose'));

$ws->start();

class ChatServer
{
    /** @var  Redis */
    private $redis;

    public function __construct()
    {
        echo "启动前清理数据\n";
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        if ($redis->ping() != '+PONG') {
            echo "redis连接失败\n";exit;
        }
        $delKeys = $redis->keys('fd_*');
        foreach ($delKeys as $key) {
            $redis->del($key);
        }
        $delKeys = $redis->keys('roomId_*');
        foreach ($delKeys as $key) {
            $redis->del($key);
        }
    }

    public function onWorkerStart($ws, $workerId)
    {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379);
        if ($redis->ping() != '+PONG') {
            echo "redis连接失败\n";
        }
        $this->redis = $redis;
    }

    public function onOpen($ws, $request)
    {
        echo "fd:{$request->fd} is open\n";
        if (empty($request->get['roomId']) || empty($request->get['nick'])) {
            $status = 'fail';
        } else {
            //建立身份关联
            $this->redis->hSet("fd_".$request->fd, 'roomId', $request->get['roomId']);
            $this->redis->hSet("fd_".$request->fd, 'nick', $request->get['nick']);
            $this->redis->sAdd("roomId_".$request->get['roomId'], $request->fd);

            $status = 'success';
        }
        $sendData = [
            'cmd' => 'open',
            'data' => [
                'status' => $status
            ]
        ];
        $ws->push($request->fd, json_encode($sendData));
    }

    public function onMessage($ws, $frame)
    {
        echo "fd:[$frame->fd}, Message: {$frame->data}\n";
        if (!empty($frame->data)) {
            $fdInfo = $this->redis->hGetAll("fd_".$frame->fd);
            if (!empty($fdInfo['nick']) && !empty($fdInfo['roomId'])) {
                $sendData = [
                    'cmd' => 'ReceiveMessage',
                    'data' => [
                        'nick' => $fdInfo['nick'],
                        'msg' => $frame->data,
                    ]
                ];
                $fdArr = $this->redis->sMembers("roomId_".$fdInfo['roomId']);
                foreach ($fdArr as $fd) {
                    $ws->push($fd, json_encode($sendData));
                }
            }
        }
    }

    public function onClose($ws, $fd, $reactorId)
    {
        echo "fd:{$fd} is closed\n";
        //删除fd身份数据并在房间内移动该fd
        $fdInfo = $this->redis->hGetAll("fd_".$fd);
        if (!empty($fdInfo['roomId'])) {
            $this->redis->sRem("roomId_".$fdInfo['roomId'], $fd);
        }
        $this->redis->del("fd_".$fd);
    }
}

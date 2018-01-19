<?php

// 创建tcp服务器
$errno = 0;
$errmsg = '';
$socket = stream_socket_server('tcp://0.0.0.0:1234', $errno, $errmsg);
stream_set_blocking($socket, 0);

// 创建事件并监听socket文件描述符，监听事件EV_READ：可读，EV_PERSIST：持续化（断开连接事件被关闭可被监听）
$base = event_base_new();
$event = event_new();
event_set($event, $socket, EV_READ | EV_PERSIST, "acceptConnect", array($event, $base));
event_base_set($event, $base);
event_add($event);
event_base_loop($base); // 开始event轮询，当socket文件描述符可读时会触发acceptConnect函数处理

function acceptConnect($socket, $events, $arg)
{
    $newSocket = @stream_socket_accept($socket, 0, $remote_address); // 第二个参数设置0，不堵塞，未获取到会警告
    if (!$newSocket) {
        return;
    }
    stream_set_blocking($newSocket, 0);
    echo "接受一个新连接\n";

    // 监听newSocket文件描述符，监听事件EV_READ：可读，EV_PERSIST：持续化（断开连接事件被关闭可被监听）
    $event = event_new();
    event_set($event, $newSocket, EV_READ | EV_PERSIST, "acceptData", array($event, $arg[1]));
    event_base_set($event, $arg[1]);
    event_add($event);
}

function acceptData($newSocket, $events, $arg)
{
    $buffer = @fread($newSocket,1024); //获取数据
    if ($buffer === '' || $buffer === false) {
        if (feof($newSocket) || !is_resource($newSocket) || $buffer === false) {
            echo "连接退出\n";
            event_del($arg[0]); //关闭连接事件
            @fclose($newSocket); // 关闭连接
            return;
        }
    }
    echo "get client data:{$buffer}\n";
    $msg = "server say hello world\n";
    fwrite($newSocket, $msg);
}
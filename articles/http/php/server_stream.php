<?php

$errno = 0;
$errmsg = '';
$socket = stream_socket_server('tcp://0.0.0.0:1234', $errno, $errmsg); //创建tcp服务器
stream_set_blocking($socket, 0); // 设置非堵塞

while(1) {
    $newSocket = stream_socket_accept($socket); // 接受请求
    if ($newSocket) {
        while ($buffer = fread($newSocket, 1024) !== false) { //获取数据，fread堵塞到获取数据，fgets堵塞到获取指定大小数据
            echo "get client data:{$buffer}\n";
            $msg = "server say hello world\n";
            fwrite($newSocket, $msg);
        }
        fclose($newSocket);
    } else {
        echo "no newSocket\n";
    }
}
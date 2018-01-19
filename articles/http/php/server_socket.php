<?php

$address = '127.0.0.1'; // 设置地址
$port = 1234; // 设置端口
$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP); // AF_INET=ipv4，AF_INET6=ipv6，SOCK_STREAM=tcp，SOCK_DGRAM=udp
socket_set_block($sock); // 阻塞模式
socket_bind($sock, $address, $port); // 绑定到socket端口
socket_listen($sock, 4); // 开始监听

while(1) {
    $newSocket = socket_accept($sock); //接受请求
    if ($newSocket) {
        while (($buffer = socket_read($newSocket,1024)) !== false ) {
            echo "get client data:{$buffer}\n";
            $msg = "server say hello world\n";
            socket_write($newSocket, $msg);
        }
        socket_close($newSocket);
    } else {
        echo "no newSocket\n";
    }
}
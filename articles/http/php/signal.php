<?php

function signalHandler($sigNo)
{
    switch ($sigNo) {
        // Stop.
        case SIGINT:
            echo "进程退出\n";
            exit(0);
        // user1
        case SIGUSR1:
            echo "处理用户自定义信号\n";
            break;
        default:
            // 处理所有其他信号
    }
}
$pid = posix_getgid();
echo "pid:{$pid}\n";

// 注册SIGINT信号，ctrl+c，或命令行kill SIGINT $pid，或使用函数posix_kill触发
pcntl_signal(SIGINT, 'SignalHandler', false);
// 注册SIGUSR1信号，
pcntl_signal(SIGUSR1, 'SignalHandler', false);

posix_kill($pid, SIGUSR1);

while(1) {
    sleep(1);
    pcntl_signal_dispatch(); // 信号分发
}
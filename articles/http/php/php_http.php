<?php

// 解析命令
$command  = isset($argv[1]) ? trim($argv[1]) : '';
$available_commands = array(
    'start',
    'stop',
    'reload',
    'status',
);
$usage = "Usage: php index.php {" . implode('|', $available_commands) . "}\n";
if (empty($command) || !in_array($command, $available_commands)) {
    exit($usage);
}

$worker = new Worker();

switch ($command) {
    case 'start':
        $worker->start();
        break;
    case 'stop':
    case 'reload':
    case 'status':
        $worker->sendSignalToMaster($command);
        break;
}


class Worker
{

    public $count = 4; //子进程数
    public $localSocket = 'tcp://0.0.0.0:2345'; // 监听地址
    public $onMessage = null; // 处理函数
    
    private $masterPidFile = 'masterPidFile.pid'; // 主进程pid
    private $masterStatusFile = 'masterStatusFile.status'; // 主进程状态文件
    private $forkArr = array(); // 子进程pid数组
    private $socket = null; // 监听socket
    private $newSocket = null; // 连接socket
    private $masterStop = 0; // 主进程是否停止
    private $connectionCount = 0; //每个子进程到连接数
    private $requestNum = 0; //每个子进程总请求数

    public function __construct()
    {
        if (!$this->onMessage) { // 默认处理
            $this->onMessage = function($connection)
            {
                //var_dump($_GET, $_POST, $_COOKIE, $_SESSION, $_SERVER, $_FILES);
                // 发送数据给客户端
                $connection->sendData("hello world \n");
            };
        }
    }

    /**
     * 主进程启动
     */
    public function start()
    {
        // 判断当前程序是否已经启动
        $masterPidFileExist = is_file($this->masterPidFile);
        if ($masterPidFileExist) {
            exit("当前程序已经在运行，请不要重启启动\n");
        }

        // 保存主进程pid到文件用于stop,reload,status等命令操作
        $masterPid = posix_getpid();
        file_put_contents($this->masterPidFile, $masterPid);

        // 注册主进程信号，pcntl_signal第三个参数设置成false，才会有信号时被pcntl_wait调用
        pcntl_signal(SIGINT, array($this, 'masterSignalHandler'), false); // 退出，用于stop命令或主进程窗口按下ctrl+c
        pcntl_signal(SIGUSR1, array($this, 'masterSignalHandler'), false); // 自定义信号1，用于reload命令
        pcntl_signal(SIGUSR2, array($this, 'masterSignalHandler'), false); // 自定义信号2，用户status命令

        // 主进程创建tcp服务器
        $errno = 0;
        $errmsg = '';
        $socket = stream_socket_server($this->localSocket, $errno, $errmsg);

        // 尝试打开KeepAlive TCP和禁用Nagle算法。
        if (function_exists('socket_import_stream')) {
            $socketImport = socket_import_stream($socket);
            @socket_set_option($socketImport, SOL_SOCKET, SO_KEEPALIVE, 1);
            @socket_set_option($socketImport, SOL_TCP, TCP_NODELAY, 1);
        }

        // Non blocking.
        stream_set_blocking($socket, 0);
        $this->socket = $socket;

        // 创建count个子进程，用于接受请求和处理数据
        while(count($this->forkArr) < $this->count) {
            $this->fork();
        }

        // 主进程接受信号和监听子进程信号
        while(1) {

            //sleep(1);
            pcntl_signal_dispatch(); // 信号分发
            $status = 0;
            $pid = pcntl_wait($status, WUNTRACED); // 堵塞直至获取子进程退出或中断信号或调用一个信号处理器，或者没有子进程时返回错误
            pcntl_signal_dispatch();
            if ($pid > 0) {
                // 子进程退出
                echo "子进程退出pid：{$pid}\n";
                unset($this->forkArr[$pid]);
                // 关闭还是重启
                if (!$this->masterStop) {
                    // 重启
                    $this->fork();
                }
            } else {
                // 主进程退出状态并且没有子进程时退出
                if ($this->masterStop && empty($this->forkArr)) {
                    unlink($this->masterPidFile);
                    fclose($this->socket);
                    echo "主进程退出\n";
                    exit(0);
                }
            }
        }
    }

    /**
     * 主进程处理信号
     * @param $sigNo
     */
    public function masterSignalHandler($sigNo)
    {
        switch ($sigNo) {
            // Stop.
            case SIGINT:
                // 退出，先发送子进程信号关闭子进程，再等待主进程退出
                foreach ($this->forkArr as $pid) {
                    echo "关闭子进程pid：{$pid}\n" ;
                    posix_kill($pid, SIGKILL);
                }
                $this->masterStop = 1; // 将主进程状态置成退出
                break;
            // user1
            case SIGUSR1:
                // 重启，关闭当前存在但子进程，主进程会监视退出的子进程并重启一个新子进程
                foreach ($this->forkArr as $pid) {
                    echo "关闭子进程pid：{$pid}\n" ;
                    posix_kill($pid, SIGKILL);
                }
                break;
            // user2
            case SIGUSR2:
                echo "将状态信息保存至文件：{$this->masterStatusFile}\n";
                // 将状态信息保存至文件
                $str = "---------------------STATUS---------------------\n";
                $str .= 'PHP version:' . PHP_VERSION . "\n";
                $str .= 'processes num:' . $this->count . "\n";
                $str .= "---------------------PROCESS STATUS---------------------\n";
                $str .= "pid\n";

                foreach ($this->forkArr as $childPid) {
                    $str .= $childPid."\n";
                }
                file_put_contents($this->masterStatusFile, $str);
                break;
            default:
                // 处理所有其他信号
        }
    }

    /**
     * 创建子进程
     */
    public function fork()
    {
        $pid = pcntl_fork();
        if ($pid == -1) {
            die('子进程创建失败');
        } else if ($pid == 0) {
            // 以下代码在子进程中运行

            // 创建event事件
            $base = event_base_new();
            $event = event_new();

            // 设置event监听事件，监听socket文件描述符，监听事件EV_READ：可读，EV_PERSIST：持续化（断开连接可被监听到）
            event_set($event, $this->socket, EV_READ | EV_PERSIST, array($this, "acceptConnect"), array($event, $base));
            // 设置event
            event_base_set($event, $base);
            event_add($event);
            // 开始event轮询，当socket文件描述符有可读或断开会触发acceptConnect函数处理
            event_base_loop($base);
        } else {
            // 主进程将子进程pid保存到数组
            echo "创建子进程pid：{$pid}\n";
            $this->forkArr[$pid] = $pid;
        }
    }

    /**
     * 子进程接受请求
     * @param $socket
     * @param $events
     * @param $arg
     */
    public function acceptConnect($socket, $events, $arg)
    {
        $newSocket = @stream_socket_accept($socket, 0, $remote_address); // 第二个参数设置0，不堵塞，未获取到会警告
        //有一个连接过来时，子进程都会触发本函数，但只有一个子进程获取到连接并处理
        if (!$newSocket) {
            return;
        }

        echo "acceptConnect\n";
        $this->connectionCount++;

        stream_set_blocking($newSocket, 0);
        // 兼容hhvm
        if (function_exists('stream_set_read_buffer')) {
            stream_set_read_buffer($newSocket, 0);
        }

        // 子进程添加一个事件在newSocket文件描述符上
        $event = event_new();
        // 设置event监听事件，监听newSocket文件描述符，事件为EV_READ：可读，EV_PERSIST：持续化（断开连接可被监听到）
        event_set($event, $newSocket, EV_READ | EV_PERSIST, array($this, "acceptData"), array($event, $arg[1]));
        event_base_set($event, $arg[1]);
        event_add($event);
    }

    /**
     * 子进程处理数据
     * @param $newSocket
     * @param $events
     * @param $arg
     */
    public function acceptData($newSocket, $events, $arg)
    {
        $this->newSocket = $newSocket;
        // http服务器（HTTP1.1默认使用keep-alive保持连接）
        $buffer = @fread($newSocket,65535); //获取数据
        //echo "获取客户端数据:{$buffer}\n";
        if ($buffer === '' || $buffer === false) {
            if (feof($newSocket) || !is_resource($newSocket) || $buffer === false) {
                echo "客户端关闭\n";
                event_del($arg[0]); //关闭连接事件
                @fclose($this->newSocket); // 关闭连接
                $this->connectionCount--;
                return;
            }
        }
        $this->requestNum++;
        $this->httpDecode($buffer); // http解码
        call_user_func($this->onMessage, $this); // 调用处理函数

        /*// tcp服务器
        $buffer = fread($newSocket,1024);
        if ($buffer === '' || $buffer === false) {
            if (feof($newSocket) || $buffer === false) {
                echo "客户端关闭连接\n";
                event_del($arg[0]);
                fclose($newSocket);
            }
        } else {
            echo "获取客户端数据：" . $buffer;
            $msg = "hello client\n";
            fwrite($newSocket, $msg);
        }*/
    }

    /**
     * http服务器返回数据
     * @param $sendBuffer
     * @return bool
     */
    public function sendData($sendBuffer) {
        $msg = $this->httpEncode($sendBuffer); // http编码
        fwrite($this->newSocket, $msg, 8192);
        return true;
    }

    /**
     * http解码（仅GET请求）
     * @param $content
     * @return array
     */
    public function httpDecode($content)
    {
        // 全局变量
        $_POST = $_GET = $_COOKIE = $_REQUEST = $_SESSION = $_FILES = array();

        $_SERVER = array(
            'QUERY_STRING'         => '',
            'REQUEST_METHOD'       => '',
            'REQUEST_URI'          => '',
            'SERVER_PROTOCOL'      => '',
            'SERVER_NAME'          => '',
            'HTTP_HOST'            => '',
            'HTTP_USER_AGENT'      => '',
            'HTTP_ACCEPT'          => '',
            'HTTP_ACCEPT_LANGUAGE' => '',
            'HTTP_ACCEPT_ENCODING' => '',
            'HTTP_COOKIE'          => '',
            'HTTP_CONNECTION'      => '',
            'REMOTE_ADDR'          => '',
            'REMOTE_PORT'          => '0',
            'REQUEST_TIME'         => time()
        );

        // 解析头部
        list($http_header, $http_body) = explode("\r\n\r\n", $content, 2);
        $header_data = explode("\r\n", $http_header);

        list($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SERVER['SERVER_PROTOCOL']) = explode(' ',
            $header_data[0]);
        unset($header_data[0]);
        foreach ($header_data as $content) {
            // \r\n\r\n
            if (empty($content)) {
                continue;
            }
            list($key, $value)       = explode(':', $content, 2);
            $key                     = str_replace('-', '_', strtoupper($key));
            $value                   = trim($value);
            $_SERVER['HTTP_' . $key] = $value;
        }

        // 查询字符串
        $_SERVER['QUERY_STRING'] = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        if ($_SERVER['QUERY_STRING']) {
            // $GET
            parse_str($_SERVER['QUERY_STRING'], $_GET);
        } else {
            $_SERVER['QUERY_STRING'] = '';
        }

        // REQUEST
        $_REQUEST = array_merge($_GET, $_POST);

        return array('get' => $_GET, 'post' => $_POST, 'cookie' => $_COOKIE, 'server' => $_SERVER, 'files' => $_FILES);
    }

    /**
     * http编码（仅GET请求）
     * @param $content
     * @return string
     */
    public function httpEncode($content)
    {
        $header = "HTTP/1.1 200 OK\r\n";
        $header .= "Content-Type: text/html;charset=utf-8\r\n";
        $header .= "Connection: keep-alive\r\n";
        $header .= "Server: workerman/3.5.4\r\n";
        $header .= "Content-Length: " . strlen($content) . "\r\n\r\n";
        return $header . $content;
    }

    /**
     * 发送命令给主进程
     * @param $command
     */
    public function sendSignalToMaster($command)
    {
        $masterPid = file_get_contents($this->masterPidFile);
        if ($masterPid) {
            switch ($command) {
                case 'stop':
                    posix_kill($masterPid, SIGINT);
                    break;
                case 'reload':
                    posix_kill($masterPid, SIGUSR1);
                    break;
                case 'status':
                    posix_kill($masterPid, SIGUSR2);
                    sleep(1); // 等待主进程将状态信息放入文件
                    $masterStatus = file_get_contents($this->masterStatusFile);
                    echo $masterStatus;
                    unlink($this->masterStatusFile);
                    break;
            }
            exit;
        } else {
            echo "主进程不存在\n";
            exit;
        }
    }
}
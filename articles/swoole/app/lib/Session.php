<?php

namespace lib;

class Session
{
    private $sessionId;
    private $cookieKey;
    private $storeDir;
    private $file;
    private $isStart;

    public function __construct()
    {
        $this->cookieKey = 'PHPSESSID';
        $this->storeDir = 'tmp/';
        $this->isStart = false;
    }

    public function start()
    {
        $this->isStart = true;
        $appServer = \AppServer::getInstance();
        $request = $appServer->request;
        $response = $appServer->response;
        $sessionId = $request->cookie[$this->cookieKey];
        if (empty($sessionId)){
            $sessionId = uniqid();
            $response->cookie($this->cookieKey, $sessionId);
        }
        $this->sessionId = $sessionId;
        $storeFile = $this->storeDir . $sessionId;
        if (!is_file($storeFile)) {
            touch($storeFile);
        }
        $session = $this->get($storeFile);
        $_SESSION = $session;
    }

    public function end()
    {
        $this->save();
    }

    public function commit()
    {
        $this->save();
    }

    private function save()
    {
        if ($this->isStart) {
            $data = json_encode($_SESSION);
            ftruncate($this->file, 0);

            if ($data) {
                rewind($this->file);
                fwrite($this->file, $data);
            }
            flock($this->file, LOCK_UN);
            fclose($this->file);
        }
    }

    private function get($fileName)
    {
        $this->file = fopen($fileName, 'c+b');
        if(flock($this->file, LOCK_EX | LOCK_NB)) {
            $data = [];
            clearstatcache(); // 清除缓存，不然filesize函数会受影响
            if (filesize($fileName) > 0) {
                $data = fread($this->file, filesize($fileName));
                $data = json_decode($data, true);
            }
            return $data;
        }
    }
}
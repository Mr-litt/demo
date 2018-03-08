<?php

namespace controller;

/**
 * Class Base
 * @property \swoole_http_request $request
 * @property \swoole_http_response $response
 * @property \PDO $db
 * @property \lib\Session $session
 */
class Base
{
    protected $appServer;
    public function __construct($appServer)
    {
        $this->appServer = $appServer;
    }

    public function __get($name)
    {
        return $this->appServer->$name;
    }

}
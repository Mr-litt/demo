<?php

namespace controller;

class Session extends Base
{
    public function write()
    {
        $this->session->start();
        $_SESSION['session_test'] = 1;
    }

    public function read()
    {
        $this->session->start();
        var_dump($_SESSION);
        sleep(5);
    }
}
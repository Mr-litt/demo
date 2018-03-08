<?php

namespace controller;

class Http extends Base
{
    public function header()
    {
        //发送Http状态码，如500, 404等等
        $this->response->status(302);
        //使用此函数代替PHP的header函数
        $this->response->header('Location', 'http://www.baidu.com/');
    }

    public function cookie()
    {
        $this->response->cookie('http_cookie','http_cookie_value');
    }
}
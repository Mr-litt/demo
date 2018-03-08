<?php

require 'vendor/phpmailer/phpmailer/src/Exception.php';
require 'vendor/phpmailer/phpmailer/src/PHPMailer.php';
require 'vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

class Mail
{
    private $host;
    private $port;
    private $fromName;
    private $username;
    private $password;

    public function __construct($config)
    {
        !empty($config['host']) && $this->host = $config['host'];
        !empty($config['port']) && $this->port = $config['port'];
        !empty($config['fromName']) && $this->fromName = $config['fromName'];
        !empty($config['username']) && $this->username = $config['username'];
        !empty($config['password']) && $this->password = $config['password'];
        if (empty($this->host) || empty($this->port) || empty($this->fromName) ||
            empty($this->username) || empty($this->password)) {
            throw new Exception('发件人信息错误');
        }
    }

    public function send($address, $subject, $body)
    {
        if (empty($address) || empty($subject) || empty($body)) {
            throw new Exception('收件人信息错误');
        }
        // 实例化PHPMailer核心类
        $mail = new PHPMailer();
        // 是否启用smtp的debug进行调试 开发环境建议开启 生产环境注释掉即可 默认关闭debug调试模式
        $mail->SMTPDebug = 0;
        // 使用smtp鉴权方式发送邮件
        $mail->isSMTP();
        // smtp需要鉴权 这个必须是true
        $mail->SMTPAuth = true;
        // 链接邮箱的服务器地址
        $mail->Host = $this->host;
        // 设置使用ssl加密方式登录鉴权
        $mail->SMTPSecure = 'ssl';
        // 设置ssl连接smtp服务器的远程服务器端口号
        $mail->Port = $this->port;
        // 设置发送的邮件的编码
        $mail->CharSet = 'UTF-8';
        // 设置发件人昵称 显示在收件人邮件的发件人邮箱地址前的发件人姓名
        $mail->FromName = $this->fromName;
        // smtp登录的账号 QQ邮箱即可
        $mail->Username = $this->username;
        // smtp登录的密码 使用生成的授权码
        $mail->Password = $this->password;
        // 设置发件人邮箱地址 同登录账号
        $mail->From = $this->username;
        // 邮件正文是否为html编码 注意此处是一个方法
        $mail->isHTML(true);
        // 设置收件人邮箱地址
        $mail->addAddress($address);
        // 添加多个收件人 则多次调用方法即可
        //$mail->addAddress('87654321@163.com');
        // 添加该邮件的主题
        $mail->Subject = $subject;
        // 添加邮件正文
        $mail->Body = $body;
        // 为该邮件添加附件
        //$mail->addAttachment('./example.pdf');
        // 发送邮件 返回状态
        $status = $mail->send();
        return $status;
    }
}
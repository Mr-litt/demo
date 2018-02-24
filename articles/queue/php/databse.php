<?php

class QueueDataBase
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = new PDO('mysql:host=localhost;dbname=test', 'root', '123456');
    }

    public function push($name, $data)
    {
        // 将队列数据插入数据表
        $sql = 'INSERT INTO `queue` (`name`, `data`, `is_popped`) VALUE (?, ?, ?)';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(array($name, $data, 0));
    }

    public function pop($name)
    {
        // 从数据表获取一条未被获取的队列数据
        $sql = 'SELECT `id`, `data` FROM `queue` WHERE `name` = ? AND `is_popped` = ? ORDER BY id ASC LIMIT 1 ';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array($name, 0));
        $info = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($info) {
            // 获取到队列数据则修改为已获取状态
            $sql = 'update `queue` set is_popped = ? where `id` = ?';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array(1, $info['id']));
            return $info;
        }
        return false;
    }
}

$dataBase = new QueueDataBase();
$name = 'flux';
$val = '放入第一个内容';
$dataBase->push($name, $val);

$info = $dataBase->pop($name);
print_r($info);
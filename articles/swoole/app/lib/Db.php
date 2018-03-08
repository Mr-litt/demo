<?php

namespace lib;

class Db
{
    private $pdo;
    public function __construct()
    {
        $this->pdo = new \PDO('mysql:host=localhost;dbname=test', 'root', '123456');
    }

    public function __call($name, $arguments)
    {
        if (count($arguments) == 1) {
            return $this->pdo->$name($arguments[0]);
        } else if(count($arguments) == 2) {
            return $this->pdo->$name($arguments[0], $arguments[1]);
        }
    }
}
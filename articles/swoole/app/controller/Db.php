<?php

namespace controller;

class Db extends Base
{
    public function test()
    {
        $tables = $this->db->query("show tables")->fetchall();
        var_dump($tables);
    }

}
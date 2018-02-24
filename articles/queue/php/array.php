<?php

class QueueArray
{
    public $arr = array();

    public function tailEnqueue($val)
    {
        return array_push($this->arr, $val); // 队尾入队
    }

    public function tailDequeue()
    {
        return array_pop($this->arr); // 队尾出队
    }

    public function headEnqueue($val)
    {
        return array_unshift($this->arr, $val); // 队首入队
    }

    public function headDequeue()
    {
        return array_shift($this->arr); //队首出队
    }

    public function length()
    {
        return count($this->arr); // 队列长度
    }

    public function head()
    {
        return reset($this->arr); // 获取队首元素
    }

    public function tail()
    {
        return end($this->arr); // 获取队尾元素
    }

    public function clear()
    {
        unset($this->arr); // 清空队列
        return true;
    }
}
<?php

$s = new SphinxClient;
$s->setServer("localhost", 9312);
$s->setMatchMode(SPH_MATCH_ANY); //匹配模式 SPH_MATCH_ALL：完全匹配
$result = $s->query("管理", 'test1');
$list = $result['matches']; // 匹配的内容，会返回配置文件设定到属性，key为id，可根据id到MySQL查询详细到信息
print_r($list);
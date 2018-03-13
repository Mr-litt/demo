<?php

declare(strict_types=1);

function add(int $a, int $b):int {
    return $a+$b;
}

var_dump(1 + 2); // 3
var_dump(add(1.5 + 2.5)); // declare(strict_types=0)时输出3，declare(strict_types=1)时抛出异常
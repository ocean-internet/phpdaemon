<?php

gc_enable();

$data = str_repeat(chr(rand(32, 126)), 100 * 1024 * 1024);

echo $data;

exit();

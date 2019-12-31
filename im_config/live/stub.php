<?php
Phar::interceptFileFuncs();
set_include_path('phar://' . __FILE__ . PATH_SEPARATOR . get_include_path());
include 'phar://' . __FILE__ . '/initializer.php';
include 'phar://' . __FILE__ . '/db.php';
include 'phar://' . __FILE__ . '/va_map.php';
__HALT_COMPILER();
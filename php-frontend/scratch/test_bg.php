<?php
$cmd = 'cmd /c start /B "" "C:\\xampp\\php\\php.exe" -r "file_put_contents(\'' . addslashes(__DIR__) . '/bg_test.txt\', \'hello\');"';
pclose(popen($cmd, 'r'));
echo "Triggered OK";


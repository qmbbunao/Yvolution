<?php
session_name('YVOLUTION_SID');
session_start();
setcookie('YVOLUTION_SID', session_id(), 0, '/');
$_SESSION['debug'] = 'ok';
echo 'done';

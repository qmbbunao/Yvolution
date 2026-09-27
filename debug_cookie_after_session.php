<?php
session_start();
setcookie('aftersession', 'test', 0, '/');
echo 'after_session_cookie';

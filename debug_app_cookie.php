<?php
require_once __DIR__ . '/config/app.php';
echo 'session_name=' . session_name() . '; cookie=' . ($_COOKIE[session_name()] ?? 'missing');

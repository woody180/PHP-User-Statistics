<?php
require_once 'analytics/Analytics.php';
$analytics = new Analytics([
    'host' => 'localhost',
    'dbname' => 'analytics',
    'user' => 'root',
    'password' => '',
],
"Asia/Tbilisi");


$analytics->init();
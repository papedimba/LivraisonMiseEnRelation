<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/Auth.php';

Auth::logout();
header('Location: /login.php');
exit;

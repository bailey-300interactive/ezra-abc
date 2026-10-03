<?php
declare(strict_types=1);
require __DIR__ . '/../inc/auth.php';

start_admin_session();
$_SESSION = [];
session_destroy();
header('Location: login.php');

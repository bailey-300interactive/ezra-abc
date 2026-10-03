<?php
declare(strict_types=1);
require __DIR__ . '/../inc/auth.php';
require_login();
header('Location: letters.php');

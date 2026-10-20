<?php
declare(strict_types=1);

require_once __DIR__ . '/php/auth.php';
startAuthSession();
requireLogin();

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: index.html', true, 302);
    exit;
}

header('Cache-Control: no-store');
readfile(__DIR__ . '/admin.html');
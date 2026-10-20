<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
startAuthSession();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('Method not allowed.');
}

if (!validCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid logout request.');
}

$rememberCookie = $_COOKIE[REMEMBER_COOKIE] ?? '';
if (is_string($rememberCookie) && preg_match('/^([a-f0-9]{32})\.[a-f0-9]{64}$/', $rememberCookie, $matches) === 1) {
    require_once __DIR__ . '/database.php';
    $statement = $conn->prepare('DELETE FROM remember_tokens WHERE selector = ?');
    $statement->bind_param('s', $matches[1]);
    $statement->execute();
    $statement->close();
    $conn->close();
}
clearRememberCookie();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}
session_destroy();

header('Location: ../pages/Login.html?logout=1', true, 303);
exit;

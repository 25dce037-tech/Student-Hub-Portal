<?php
declare(strict_types=1);

const REMEMBER_COOKIE = 'studenthub_remember';
const REMEMBER_LIFETIME = 2592000;

function startAuthSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();

    if (
        isset($_SESSION['last_activity'])
        && time() - (int) $_SESSION['last_activity'] > 1800
    ) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['auth_timed_out'] = true;
    }

    if (isset($_SESSION['user_id'])) {
        $_SESSION['last_activity'] = time();
    } else {
        restoreRememberedLogin();
    }
}

function rememberCookieOptions(int $expires): array
{
    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function clearRememberCookie(): void
{
    setcookie(REMEMBER_COOKIE, '', rememberCookieOptions(time() - 42000));
    unset($_COOKIE[REMEMBER_COOKIE]);
}

function restoreRememberedLogin(): void
{
    $cookie = $_COOKIE[REMEMBER_COOKIE] ?? '';
    if ($cookie === '') {
        return;
    }

    if (!is_string($cookie) || preg_match('/^([a-f0-9]{32})\.([a-f0-9]{64})$/', $cookie, $matches) !== 1) {
        clearRememberCookie();
        return;
    }

    require_once __DIR__ . '/database.php';
    $statement = $conn->prepare(
        'SELECT rt.user_id, rt.validator_hash, ua.email, ua.`role` AS role
         FROM remember_tokens AS rt
         JOIN user_accounts AS ua ON ua.id = rt.user_id
         WHERE rt.selector = ? AND rt.expires_at > CURRENT_TIMESTAMP
         LIMIT 1'
    );
    $statement->bind_param('s', $matches[1]);
    $statement->execute();
    $token = $statement->get_result()->fetch_assoc();
    $statement->close();

    if (!$token || !hash_equals($token['validator_hash'], hash('sha256', $matches[2]))) {
        clearRememberCookie();
        return;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $token['user_id'];
    $_SESSION['email'] = $token['email'];
    $_SESSION['role'] = $token['role'];
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function requireLogin(): void
{
    if (!isset($_SESSION['user_id'])) {
        $expired = isset($_SESSION['auth_timed_out']);
        unset($_SESSION['auth_timed_out']);
        $scriptDirectory = dirname(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''));
        $basePath = preg_replace('~/(?:php|pages)$~', '', $scriptDirectory) ?? $scriptDirectory;
        header(
            'Location: ' . rtrim($basePath, '/') . '/pages/Login.html'
                . ($expired ? '?error=timeout' : ''),
            true,
            302
        );
        exit;
    }
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validCsrfToken(mixed $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

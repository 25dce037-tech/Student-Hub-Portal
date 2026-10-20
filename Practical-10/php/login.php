<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
startAuthSession();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && isset($_GET['csrf'])) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode(['csrf_token' => csrfToken()], JSON_THROW_ON_ERROR);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: ../pages/Login.html', true, 302);
    exit;
}

if (!validCsrfToken($_POST['csrf_token'] ?? null)) {
    header('Location: ../pages/Login.html?error=expired', true, 303);
    exit;
}

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
if (!is_string($email) || !is_string($password)) {
    header('Location: ../pages/Login.html?error=invalid', true, 303);
    exit;
}
$email = trim($email);
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 254) {
    header('Location: ../pages/Login.html?error=invalid', true, 303);
    exit;
}

try {
    require_once __DIR__ . '/database.php';
    $statement = $conn->prepare(
            'SELECT id, email, password_hash, `role` AS role FROM user_accounts WHERE email = ? LIMIT 1'
    );
    $statement->bind_param('s', $email);
    $statement->execute();
    $account = $statement->get_result()->fetch_assoc();
    $statement->close();
} catch (mysqli_sql_exception $exception) {
    error_log('Student Hub login database error: ' . $exception->getMessage());
    header('Location: ../pages/Login.html?error=unavailable', true, 303);
    exit;
}

if (!$account || !password_verify($password, $account['password_hash'])) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    header('Location: ../pages/Login.html?error=invalid', true, 303);
    exit;
}

try {
    $statement = $conn->prepare(
        'UPDATE user_accounts SET last_login_at = CURRENT_TIMESTAMP WHERE id = ?'
    );
    $statement->bind_param('i', $account['id']);
    $statement->execute();
    $statement->close();

    $rememberMe = ($_POST['remember_me'] ?? '') === '1';
    $existingCookie = $_COOKIE[REMEMBER_COOKIE] ?? '';
    if ($existingCookie !== '') {
        if (
            is_string($existingCookie)
            && preg_match('/^([a-f0-9]{32})\.[a-f0-9]{64}$/', $existingCookie, $matches) === 1
        ) {
            $statement = $conn->prepare('DELETE FROM remember_tokens WHERE selector = ?');
            $statement->bind_param('s', $matches[1]);
            $statement->execute();
            $statement->close();
        }
        clearRememberCookie();
    }

    if ($rememberMe) {
        $selector = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));
        $validatorHash = hash('sha256', $validator);
        $expiresAt = date('Y-m-d H:i:s', time() + REMEMBER_LIFETIME);

        $statement = $conn->prepare('DELETE FROM remember_tokens WHERE expires_at <= CURRENT_TIMESTAMP');
        $statement->execute();
        $statement->close();

        $statement = $conn->prepare(
            'INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at)
             VALUES (?, ?, ?, ?)'
        );
        $statement->bind_param('isss', $account['id'], $selector, $validatorHash, $expiresAt);
        $statement->execute();
        $statement->close();
        setcookie(REMEMBER_COOKIE, $selector . '.' . $validator, rememberCookieOptions(time() + REMEMBER_LIFETIME));
    }
    $conn->close();
} catch (mysqli_sql_exception $exception) {
    error_log('Student Hub login update error: ' . $exception->getMessage());
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    header('Location: ../pages/Login.html?error=unavailable', true, 303);
    exit;
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $account['id'];
$_SESSION['email'] = $account['email'];
$_SESSION['role'] = $account['role'];
$_SESSION['last_activity'] = time();
unset($_SESSION['auth_timed_out']);
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

header(
    'Location: ' . ($account['role'] === 'admin' ? '../admin.html' : '../index.html'),
    true,
    303
);
exit;

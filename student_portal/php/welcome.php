<?php
declare(strict_types=1);

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
ini_set('session.use_strict_mode', '1');
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => $isHttps,
]);

if (!isset($_SESSION['auth_user_id'], $_SESSION['auth_role'])) {
    $_SESSION['login_notice'] = 'Please log in to view your account.';
    header('Location: login.php', true, 303);
    exit;
}

$lastActivity = filter_var($_SESSION['last_activity'] ?? null, FILTER_VALIDATE_INT);
$loginTime = filter_var($_SESSION['login_time'] ?? null, FILTER_VALIDATE_INT);
if (
    $lastActivity === false || $loginTime === false
    || time() - $lastActivity > 900
    || time() - $loginTime > 28800
) {
    $_SESSION = [];
    session_destroy();
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => $isHttps,
    ]);
    $_SESSION['login_notice'] = 'Your session expired. Please log in again.';
    header('Location: login.php', true, 303);
    exit;
}

if ($_SESSION['auth_role'] === 'admin') {
    header('Location: admin-dashboard.php', true, 303);
    exit;
}
if ($_SESSION['auth_role'] !== 'student') {
    http_response_code(403);
    exit('Your account role is not authorized to access this page.');
}

header('Location: dashboard.php', true, 303);
exit;

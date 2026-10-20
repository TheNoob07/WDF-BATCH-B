<?php
declare(strict_types=1);

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => $isHttps,
]);

$token = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['logout_csrf'] ?? '';
if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    || !is_string($token)
    || !is_string($sessionToken)
    || $sessionToken === ''
    || !hash_equals($sessionToken, $token)
) {
    http_response_code(400);
    exit('Invalid logout request. Return to the portal and try again.');
}

$rememberValue = $_COOKIE['student_portal_remember'] ?? '';
$rememberRevocationFailed = false;
if (is_string($rememberValue)) {
    $parts = explode('.', $rememberValue);
    if (count($parts) === 2 && preg_match('/^[a-f0-9]{32}$/', $parts[0]) === 1) {
        $host = getenv('STUDENT_PORTAL_DB_HOST') ?: '127.0.0.1';
        $database = getenv('STUDENT_PORTAL_DB_NAME') ?: 'studenthub';
        $databaseUser = getenv('STUDENT_PORTAL_DB_USER') ?: 'root';
        $databasePassword = getenv('STUDENT_PORTAL_DB_PASSWORD');
        $databasePassword = $databasePassword === false ? '' : $databasePassword;
        $connection = null;
        try {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $connection = new mysqli($host, $databaseUser, $databasePassword, $database);
            $statement = $connection->prepare('DELETE FROM remember_tokens WHERE selector = ?');
            $statement->bind_param('s', $parts[0]);
            $statement->execute();
            $statement->close();
        } catch (mysqli_sql_exception $exception) {
            error_log('Student portal remember-token logout error: ' . $exception->getMessage());
            $rememberRevocationFailed = true;
        } finally {
            if ($connection instanceof mysqli) {
                $connection->close();
            }
        }
    }
}

setcookie('student_portal_remember', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);

unset(
    $_SESSION['auth_user_id'],
    $_SESSION['auth_username'],
    $_SESSION['auth_role'],
    $_SESSION['login_time'],
    $_SESSION['last_activity'],
    $_SESSION['remember_me'],
    $_SESSION['remember_me_token'],
    $_SESSION['logout_csrf']
);
$_SESSION['login_notice'] = $rememberRevocationFailed
    ? 'You have been logged out on this device, but the saved sign-in token could not be revoked because the database was unavailable.'
    : 'You have been logged out.';
session_regenerate_id(true);
header('Location: login.php', true, 303);
exit;

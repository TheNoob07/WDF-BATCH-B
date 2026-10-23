<?php
declare(strict_types=1);

const PAGE_IDLE_TIMEOUT = 900;
const PAGE_ABSOLUTE_TIMEOUT = 28800;

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$portalBase = rtrim(dirname(dirname($scriptName)), '/');
ini_set('session.use_strict_mode', '1');
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => $isHttps,
]);

$requestedPath = $_GET['path'] ?? '';
if (!is_string($requestedPath) || preg_match('/^[a-zA-Z0-9_-]+\.html$/', $requestedPath) !== 1) {
    http_response_code(404);
    exit('Page not found.');
}

if (!isset($_SESSION['auth_user_id'], $_SESSION['auth_username'], $_SESSION['auth_role'])) {
    $_SESSION['requested_portal_page'] = $requestedPath;
    session_write_close();
    header('Location: ' . $portalBase . '/php/login.php', true, 303);
    exit;
}
if (!in_array($_SESSION['auth_role'], ['student', 'admin'], true)) {
    http_response_code(403);
    exit('Your account role is not authorized to access this page.');
}

$lastActivity = filter_var($_SESSION['last_activity'] ?? null, FILTER_VALIDATE_INT);
$loginTime = filter_var($_SESSION['login_time'] ?? null, FILTER_VALIDATE_INT);
if (
    $lastActivity === false || $loginTime === false
    || time() - $lastActivity > PAGE_IDLE_TIMEOUT
    || time() - $loginTime > PAGE_ABSOLUTE_TIMEOUT
) {
    $_SESSION = [];
    session_destroy();
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => $isHttps,
    ]);
    $_SESSION['login_notice'] = 'Your session expired. Please log in again.';
    $_SESSION['requested_portal_page'] = $requestedPath;
    session_write_close();
    header('Location: ' . $portalBase . '/php/login.php', true, 303);
    exit;
}

$_SESSION['last_activity'] = time();
$projectRoot = realpath(dirname(__DIR__));
$pagePath = realpath(dirname(__DIR__) . DIRECTORY_SEPARATOR . $requestedPath);
if (
    $projectRoot === false
    || $pagePath === false
    || strpos($pagePath, $projectRoot . DIRECTORY_SEPARATOR) !== 0
    || strtolower(pathinfo($pagePath, PATHINFO_EXTENSION)) !== 'html'
) {
    http_response_code(404);
    exit('Page not found.');
}

$page = file_get_contents($pagePath);
if ($page === false) {
    http_response_code(500);
    exit('The requested page could not be loaded.');
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
$role = htmlspecialchars((string) $_SESSION['auth_role'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$logoutToken = htmlspecialchars((string) ($_SESSION['logout_csrf'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$page = preg_replace(
    '/<body(?=[\\s>])/',
    '<body data-portal-role="' . $role . '" data-logout-csrf="' . $logoutToken . '"',
    $page,
    1
);
if (!is_string($page)) {
    http_response_code(500);
    exit('The requested page could not be prepared.');
}
echo $page;

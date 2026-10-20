<?php
declare(strict_types=1);

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
ini_set('session.use_strict_mode', '1');
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => $isHttps,
]);

function redirectAdminToLogin(string $message): void
{
    $_SESSION = [];
    session_destroy();
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'cookie_secure' => $isHttps,
    ]);
    $_SESSION['login_notice'] = $message;
    header('Location: login.php', true, 303);
    exit;
}

if (!isset($_SESSION['auth_user_id'], $_SESSION['auth_username'], $_SESSION['auth_role'])) {
    redirectAdminToLogin('Please log in to view your dashboard.');
}

$lastActivity = filter_var($_SESSION['last_activity'] ?? null, FILTER_VALIDATE_INT);
$loginTime = filter_var($_SESSION['login_time'] ?? null, FILTER_VALIDATE_INT);
if (
    $lastActivity === false || $loginTime === false
    || time() - $lastActivity > 900
    || time() - $loginTime > 28800
) {
    redirectAdminToLogin('Your session expired. Please log in again.');
}

if ($_SESSION['auth_role'] !== 'admin') {
    if ($_SESSION['auth_role'] === 'student') {
        header('Location: dashboard.php', true, 303);
        exit;
    }
    http_response_code(403);
    exit('Your account role is not authorized to access this page.');
}

$_SESSION['last_activity'] = time();
$username = htmlspecialchars((string) $_SESSION['auth_username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$logoutToken = $_SESSION['logout_csrf'] ?? bin2hex(random_bytes(32));
$_SESSION['logout_csrf'] = $logoutToken;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Administrator dashboard for the Student Portal.">
    <title>Admin Dashboard | Student Portal</title>
    <link rel="stylesheet" href="registration.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<main class="signup-shell">
    <a class="brand" href="../index.html"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> Student Portal</a>
    <section class="signup-card welcome-card">
        <div class="heading-icon"><i class="fa-solid fa-user-shield" aria-hidden="true"></i></div>
        <p class="eyebrow">ADMINISTRATOR DASHBOARD</p>
        <h1>Welcome, <?= $username ?>!</h1>
        <p class="intro">You are signed in with the administrator role. Your session expires after 15 minutes of inactivity or 8 hours total.</p>
        <div class="account-actions">
            <a class="button action-link" href="database.php">Manage student and event records</a>
            <a class="secondary-link" href="../index.html">View portal home</a>
        </div>
        <form method="post" action="logout.php" class="logout-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($logoutToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <button class="logout-button" type="submit">Log out</button>
        </form>
    </section>
</main>
</body>
</html>

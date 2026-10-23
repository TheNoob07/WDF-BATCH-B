<?php
declare(strict_types=1);

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
ini_set('session.use_strict_mode', '1');
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => $isHttps,
]);

function redirectToLogin(string $message): void
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
    redirectToLogin('Please log in to view your dashboard.');
}

$lastActivity = filter_var($_SESSION['last_activity'] ?? null, FILTER_VALIDATE_INT);
$loginTime = filter_var($_SESSION['login_time'] ?? null, FILTER_VALIDATE_INT);
if (
    $lastActivity === false || $loginTime === false
    || time() - $lastActivity > 900
    || time() - $loginTime > 28800
) {
    redirectToLogin('Your session expired. Please log in again.');
}

if ($_SESSION['auth_role'] === 'admin') {
    header('Location: admin-dashboard.php', true, 303);
    exit;
}
if ($_SESSION['auth_role'] !== 'student') {
    http_response_code(403);
    exit('Your account role is not authorized to access this page.');
}

$_SESSION['last_activity'] = time();
$username = htmlspecialchars((string) $_SESSION['auth_username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$csrfToken = $_SESSION['logout_csrf'] ?? bin2hex(random_bytes(32));
$_SESSION['logout_csrf'] = $csrfToken;
$portalRole = htmlspecialchars((string) $_SESSION['auth_role'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$portalLogoutToken = htmlspecialchars((string) $csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Your signed-in Student Portal dashboard.">
    <title>Student Dashboard | Student Portal</title>
    <link rel="stylesheet" href="../CSS%20Files/registration.css">
    <link rel="stylesheet" href="../CSS%20Files/portal-nav.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="../Java%20Script%20Files/portal-nav.js" defer></script>
</head>
<body data-portal-role="<?= $portalRole ?>" data-logout-csrf="<?= $portalLogoutToken ?>">
<main class="signup-shell">
    <a class="brand" href="../index.html"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> Student Portal</a>
    <section class="signup-card welcome-card">
        <div class="heading-icon"><i class="fa-solid fa-user-check" aria-hidden="true"></i></div>
        <p class="eyebrow">STUDENT DASHBOARD</p>
        <h1>Welcome, <?= $username ?>!</h1>
        <p class="intro">You are signed in with the student role. Your session expires after 15 minutes of inactivity or 8 hours total.</p>
        <div class="account-actions">
            <a class="button action-link" href="../index.html">Go to portal home</a>
            <a class="secondary-link" href="../profile.html">View student profile</a>
        </div>
        <form method="post" action="logout.php" class="logout-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
            <button class="logout-button" type="submit">Log out</button>
        </form>
    </section>
</main>
</body>
</html>

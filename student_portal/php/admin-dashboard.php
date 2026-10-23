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
$portalRole = htmlspecialchars((string) $_SESSION['auth_role'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$portalLogoutToken = htmlspecialchars((string) $logoutToken, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$dashboardStats = [];
$recentRegistrations = [];
$recentActions = [];
$dashboardError = '';

function adminEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$host = getenv('STUDENT_PORTAL_DB_HOST') ?: '127.0.0.1';
$database = getenv('STUDENT_PORTAL_DB_NAME') ?: 'studenthub';
$databaseUser = getenv('STUDENT_PORTAL_DB_USER') ?: 'root';
$databasePassword = getenv('STUDENT_PORTAL_DB_PASSWORD');
$databasePassword = $databasePassword === false ? '' : $databasePassword;
$connection = null;
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $connection = new mysqli($host, $databaseUser, $databasePassword, $database);
    $connection->set_charset('utf8mb4');
    $dashboardStats = [
        'users' => (int) $connection->query('SELECT COUNT(*) FROM users')->fetch_row()[0],
        'students' => (int) $connection->query('SELECT COUNT(*) FROM students')->fetch_row()[0],
        'events' => (int) $connection->query('SELECT COUNT(*) FROM events')->fetch_row()[0],
        'registrations' => (int) $connection->query('SELECT COUNT(*) FROM registrations')->fetch_row()[0],
    ];
    $recentRegistrations = $connection->query(
        'SELECT students.name AS student_name, events.title AS event_title, registrations.registered_at
         FROM registrations
         INNER JOIN students ON students.id = registrations.student_id
         INNER JOIN events ON events.id = registrations.event_id
         ORDER BY registrations.registered_at DESC LIMIT 5'
    )->fetch_all(MYSQLI_ASSOC);
    $recentActions = $connection->query(
        'SELECT audit_logs.action, audit_logs.entity_type, audit_logs.entity_id,
                audit_logs.details, audit_logs.created_at, users.username
         FROM audit_logs
         LEFT JOIN users ON users.id = audit_logs.actor_id
         ORDER BY audit_logs.created_at DESC, audit_logs.id DESC LIMIT 8'
    )->fetch_all(MYSQLI_ASSOC);
} catch (mysqli_sql_exception $exception) {
    error_log('Student portal admin dashboard database error: ' . $exception->getMessage());
    $dashboardError = 'Dashboard data is unavailable. Check that the MySQL schema and Practical 11–14 migration are installed.';
} finally {
    if ($connection instanceof mysqli) {
        $connection->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Administrator dashboard for the Student Portal.">
    <title>Admin Dashboard | Student Portal</title>
    <link rel="stylesheet" href="../CSS%20Files/registration.css">
    <link rel="stylesheet" href="../CSS%20Files/admin-dashboard.css">
    <link rel="stylesheet" href="../CSS%20Files/portal-nav.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="../Java%20Script%20Files/portal-nav.js" defer></script>
</head>
<body data-portal-role="<?= $portalRole ?>" data-logout-csrf="<?= $portalLogoutToken ?>">
<main class="signup-shell admin-dashboard-shell">
    <a class="brand" href="../index.html"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> Student Portal</a>
    <section class="signup-card welcome-card">
        <div class="heading-icon"><i class="fa-solid fa-user-shield" aria-hidden="true"></i></div>
        <p class="eyebrow">ADMINISTRATOR DASHBOARD</p>
        <h1>Welcome, <?= $username ?>!</h1>
        <p class="intro">You are signed in with the administrator role. Your session expires after 15 minutes of inactivity or 8 hours total.</p>
        <?php if ($dashboardError !== ''): ?>
            <p class="notice error" role="alert"><?= adminEscape($dashboardError) ?></p>
        <?php else: ?>
            <section class="admin-stats" aria-label="Portal statistics">
                <article><span>Users</span><strong><?= $dashboardStats['users'] ?></strong></article>
                <article><span>Students</span><strong><?= $dashboardStats['students'] ?></strong></article>
                <article><span>Events</span><strong><?= $dashboardStats['events'] ?></strong></article>
                <article><span>Registrations</span><strong><?= $dashboardStats['registrations'] ?></strong></article>
            </section>

            <section class="admin-activity-grid">
                <div class="admin-activity-panel">
                    <h2>Latest event registrations</h2>
                    <?php if ($recentRegistrations === []): ?>
                        <p class="admin-empty">No event registrations yet.</p>
                    <?php else: ?>
                        <ul>
                            <?php foreach ($recentRegistrations as $registration): ?>
                                <li>
                                    <strong><?= adminEscape($registration['student_name']) ?></strong>
                                    <span>registered for <?= adminEscape($registration['event_title']) ?></span>
                                    <time><?= adminEscape($registration['registered_at']) ?></time>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <div class="admin-activity-panel">
                    <h2>Recent administrator actions</h2>
                    <?php if ($recentActions === []): ?>
                        <p class="admin-empty">No management actions have been recorded yet.</p>
                    <?php else: ?>
                        <ul>
                            <?php foreach ($recentActions as $action): ?>
                                <li>
                                    <strong><?= adminEscape($action['action']) ?></strong>
                                    <span><?= adminEscape(($action['username'] ?? 'Former user') . ' · ' . $action['entity_type'] . ' · ' . $action['details']) ?></span>
                                    <time><?= adminEscape($action['created_at']) ?></time>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
        <div class="account-actions">
            <a class="button action-link" href="manage-records.php">Manage students and events</a>
            <a class="secondary-link" href="database.php">Database setup and registration demo</a>
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

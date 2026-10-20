<?php
declare(strict_types=1);

const LOGIN_IDLE_TIMEOUT = 900;
const LOGIN_ABSOLUTE_TIMEOUT = 28800;

ini_set('session.use_strict_mode', '1');
$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => $isHttps,
]);

function loginEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function expireRememberCookie(bool $isHttps): void
{
    setcookie('student_portal_remember', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function loginSessionExpired(): bool
{
    $lastActivity = filter_var($_SESSION['last_activity'] ?? null, FILTER_VALIDATE_INT);
    $loginTime = filter_var($_SESSION['login_time'] ?? null, FILTER_VALIDATE_INT);
    return $lastActivity === false
        || $loginTime === false
        || time() - $lastActivity > LOGIN_IDLE_TIMEOUT
        || time() - $loginTime > LOGIN_ABSOLUTE_TIMEOUT;
}

function destroyLoginSession(bool $isHttps): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $cookie = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $cookie['path'],
            'domain' => $cookie['domain'],
            'secure' => $cookie['secure'],
            'httponly' => $cookie['httponly'],
            'samesite' => $cookie['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}

function beginAuthenticatedSession(array $user): void
{
    session_regenerate_id(true);
    $now = time();
    $_SESSION['auth_user_id'] = (int) $user['id'];
    $_SESSION['auth_username'] = (string) $user['username'];
    $_SESSION['auth_role'] = (string) $user['role'];
    $_SESSION['login_time'] = $now;
    $_SESSION['last_activity'] = $now;
    $_SESSION['dashboard_csrf'] = bin2hex(random_bytes(32));
    $_SESSION['logout_csrf'] = bin2hex(random_bytes(32));
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));

    $requestedPage = $_SESSION['requested_portal_page'] ?? '';
    unset($_SESSION['requested_portal_page']);
    if (is_string($requestedPage) && preg_match('/^[a-zA-Z0-9_-]+\.html$/', $requestedPage) === 1) {
        $destination = '../' . $requestedPage;
    } else {
        $destination = $user['role'] === 'admin' ? 'admin-dashboard.php' : 'dashboard.php';
    }
    header('Location: ' . $destination, true, 303);
    exit;
}

if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}

$errorMessage = $_SESSION['login_notice'] ?? '';
unset($_SESSION['login_notice']);
$identifier = '';
$databaseAvailable = false;
$connection = null;

$host = getenv('STUDENT_PORTAL_DB_HOST') ?: '127.0.0.1';
$database = getenv('STUDENT_PORTAL_DB_NAME') ?: 'studenthub';
$databaseUser = getenv('STUDENT_PORTAL_DB_USER') ?: 'root';
$databasePassword = getenv('STUDENT_PORTAL_DB_PASSWORD');
$databasePassword = $databasePassword === false ? '' : $databasePassword;

if (isset($_SESSION['auth_user_id'])) {
    if (loginSessionExpired()) {
        destroyLoginSession($isHttps);
        session_start([
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
            'cookie_secure' => $isHttps,
        ]);
        $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
        $_SESSION['login_notice'] = 'Your session expired. Please log in again.';
        $errorMessage = $_SESSION['login_notice'];
        unset($_SESSION['login_notice']);
    } else {
        $destination = ($_SESSION['auth_role'] ?? '') === 'admin' ? 'admin-dashboard.php' : 'dashboard.php';
        header('Location: ' . $destination, true, 303);
        exit;
    }
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $connection = new mysqli($host, $databaseUser, $databasePassword, $database);
    $connection->set_charset('utf8mb4');
    $databaseAvailable = true;

    if (isset($_COOKIE['student_portal_remember'])) {
        $rememberValue = $_COOKIE['student_portal_remember'];
        $parts = is_string($rememberValue) ? explode('.', $rememberValue) : [];
        if (count($parts) === 2 && preg_match('/^[a-f0-9]{32}$/', $parts[0]) === 1) {
            $selector = $parts[0];
            $removeInvalidToken = $connection->prepare('DELETE FROM remember_tokens WHERE selector = ?');
            $removeInvalidToken->bind_param('s', $selector);
            $removeInvalidToken->execute();
            $removeInvalidToken->close();
        }
        expireRememberCookie($isHttps);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['login_csrf'], $token)) {
            throw new InvalidArgumentException('Your form session expired. Reload the page and try again.');
        }

        $submittedIdentifier = $_POST['identifier'] ?? null;
        $password = $_POST['password'] ?? null;
        if (!is_string($submittedIdentifier) || !is_string($password)) {
            throw new InvalidArgumentException('Enter your username or email and password.');
        }
        $identifier = trim($submittedIdentifier);
        if (
            $identifier === ''
            || strlen($identifier) > 254
            || preg_match('//u', $identifier) !== 1
            || preg_match('/[\x00-\x1F\x7F]/u', $identifier) === 1
            || $password === ''
            || strlen($password) > 72
            || preg_match('//u', $password) !== 1
            || preg_match('/[\x00-\x1F\x7F]/u', $password) === 1
        ) {
            throw new InvalidArgumentException('The username/email or password is incorrect.');
        }

        $statement = $connection->prepare(
            'SELECT id, username, password_hash, role FROM users
             WHERE username = ? OR email = ? LIMIT 1'
        );
        $statement->bind_param('ss', $identifier, $identifier);
        $statement->execute();
        $statement->bind_result($userId, $username, $passwordHash, $role);
        $foundUser = $statement->fetch();
        $statement->close();

        if (!$foundUser || !password_verify($password, $passwordHash)) {
            throw new InvalidArgumentException('The username/email or password is incorrect.');
        }
        if (!in_array($role, ['student', 'admin'], true)) {
            throw new InvalidArgumentException('This account has an unsupported role. Contact the portal administrator.');
        }

        $user = ['id' => (int) $userId, 'username' => $username, 'role' => $role];
        $connection->begin_transaction();
        try {
            $lastLogin = $connection->prepare('UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?');
            $lastLogin->bind_param('i', $user['id']);
            $lastLogin->execute();
            $lastLogin->close();

            $action = 'user_logged_in';
            $audit = $connection->prepare(
                'INSERT INTO registration_audit (user_id, action) VALUES (?, ?)'
            );
            $audit->bind_param('is', $user['id'], $action);
            $audit->execute();
            $audit->close();

            $connection->commit();
        } catch (mysqli_sql_exception $exception) {
            $connection->rollback();
            throw $exception;
        }

        expireRememberCookie($isHttps);
        beginAuthenticatedSession($user);
    }
} catch (InvalidArgumentException $exception) {
    $errorMessage = $exception->getMessage();
} catch (mysqli_sql_exception $exception) {
    error_log('Student portal login database error: ' . $exception->getMessage());
    $errorMessage = 'Login is unavailable. Check that MySQL is running and the required database schema has been imported.';
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
    <meta name="description" content="Sign in to the Student Portal using your registered username or email.">
    <title>Student Login | Student Portal</title>
    <link rel="stylesheet" href="registration.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<main class="signup-shell">
    <a class="brand" href="../index.html"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> Student Portal</a>
    <section class="signup-card">
        <div class="heading-icon"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i></div>
        <h1>Welcome back</h1>
        <p class="intro">Sign in with the username or email and password you used to register.</p>

        <?php if ($errorMessage !== ''): ?>
            <p class="notice error" role="alert"><?= loginEscape($errorMessage) ?></p>
        <?php endif; ?>

        <?php if (!$databaseAvailable): ?>
            <div class="setup-note">
                <h2>Database setup required</h2>
                <p>Start MySQL in XAMPP and import <code>php/database-schema.sql</code> in phpMyAdmin. Existing databases may need the one-time <code>php/practical10-migration.sql</code> update.</p>
            </div>
        <?php else: ?>
            <form method="post" action="login.php" class="signup-form">
                <input type="hidden" name="csrf_token" value="<?= loginEscape($_SESSION['login_csrf']) ?>">
                <label for="identifier">Username or email</label>
                <input id="identifier" name="identifier" type="text" maxlength="254" autocomplete="username" required value="<?= loginEscape($identifier) ?>">

                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>

                <button class="button" type="submit">Log in</button>
            </form>
        <?php endif; ?>

        <p class="signup-prompt">New to the portal? <a href="registration.php">Create an account</a></p>
        <a class="back-link" href="../index.html"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to portal</a>
    </section>
</main>
</body>
</html>

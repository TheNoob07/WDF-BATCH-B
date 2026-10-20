<?php
declare(strict_types=1);

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
ini_set('session.use_strict_mode', '1');
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => $isHttps,
]);

if (empty($_SESSION['signup_csrf'])) {
    $_SESSION['signup_csrf'] = bin2hex(random_bytes(32));
}

$successMessage = $_SESSION['signup_notice'] ?? '';
unset($_SESSION['signup_notice']);

function signupEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function signupField(string $key, string $label, int $limit): string
{
    $value = $_POST[$key] ?? null;
    if (!is_string($value)) {
        throw new InvalidArgumentException("Enter a valid {$label}.");
    }

    $value = trim($value);
    $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    if (
        $value === ''
        || preg_match('//u', $value) !== 1
        || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1
        || $length > $limit
    ) {
        throw new InvalidArgumentException("Enter a valid {$label} up to {$limit} characters.");
    }

    return $value;
}

$host = getenv('STUDENT_PORTAL_DB_HOST') ?: '127.0.0.1';
$database = getenv('STUDENT_PORTAL_DB_NAME') ?: 'studenthub';
$databaseUser = getenv('STUDENT_PORTAL_DB_USER') ?: 'root';
$databasePassword = getenv('STUDENT_PORTAL_DB_PASSWORD');
$databasePassword = $databasePassword === false ? '' : $databasePassword;
$errorMessage = '';
$formValues = ['username' => '', 'email' => ''];
$databaseAvailable = false;
$connection = null;

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $connection = new mysqli($host, $databaseUser, $databasePassword, $database);
    $connection->set_charset('utf8mb4');
    $databaseAvailable = true;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['signup_csrf'], $token)) {
            throw new InvalidArgumentException('Your form session expired. Reload the page and try again.');
        }

        $formValues['username'] = signupField('username', 'username', 30);
        if (preg_match('/^[A-Za-z0-9_.-]{3,30}$/', $formValues['username']) !== 1) {
            throw new InvalidArgumentException('Username must be 3–30 characters and use only letters, numbers, dots, hyphens, or underscores.');
        }

        $formValues['email'] = strtolower(signupField('email', 'email address', 254));
        if (filter_var($formValues['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }

        $password = $_POST['password'] ?? null;
        $confirmPassword = $_POST['confirm_password'] ?? null;
        if (!is_string($password) || !is_string($confirmPassword)) {
            throw new InvalidArgumentException('Enter and confirm your password.');
        }
        $passwordLength = strlen($password);
        if (
            $passwordLength < 8
            || $passwordLength > 72
            || preg_match('//u', $password) !== 1
            || preg_match('/[\x00-\x1F\x7F]/u', $password) === 1
        ) {
            throw new InvalidArgumentException('Password must be 8–72 bytes and cannot contain control characters.');
        }
        if (!hash_equals($password, $confirmPassword)) {
            throw new InvalidArgumentException('The password and confirmation do not match.');
        }

        $duplicateCheck = $connection->prepare(
            'SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1'
        );
        $duplicateCheck->bind_param('ss', $formValues['username'], $formValues['email']);
        $duplicateCheck->execute();
        $duplicateCheck->store_result();
        $alreadyExists = $duplicateCheck->num_rows > 0;
        $duplicateCheck->close();
        if ($alreadyExists) {
            throw new InvalidArgumentException('That username or email is already registered.');
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        if ($passwordHash === false) {
            throw new RuntimeException('Password hashing is not available.');
        }

        $connection->begin_transaction();
        try {
            $insertUser = $connection->prepare(
                "INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'student')"
            );
            $insertUser->bind_param('sss', $formValues['username'], $formValues['email'], $passwordHash);
            $insertUser->execute();
            $userId = $connection->insert_id;
            $insertUser->close();

            $action = 'user_registered';
            $auditEntry = $connection->prepare(
                'INSERT INTO registration_audit (user_id, action) VALUES (?, ?)'
            );
            $auditEntry->bind_param('is', $userId, $action);
            $auditEntry->execute();
            $auditEntry->close();
            $connection->commit();
        } catch (mysqli_sql_exception $exception) {
            $connection->rollback();
            if ((int) $exception->getCode() === 1062) {
                throw new InvalidArgumentException('That username or email is already registered.');
            }
            throw $exception;
        }

        $_SESSION['signup_notice'] = 'Your account was created successfully.';
        $_SESSION['signup_csrf'] = bin2hex(random_bytes(32));
        header('Location: registration.php', true, 303);
        exit;
    }
} catch (InvalidArgumentException $exception) {
    $errorMessage = $exception->getMessage();
} catch (mysqli_sql_exception $exception) {
    error_log('Student portal registration database error: ' . $exception->getMessage());
    $errorMessage = 'Registration is unavailable. Check that MySQL is running and the Practical 8/9 schema has been imported.';
} catch (RuntimeException $exception) {
    error_log('Student portal registration error: ' . $exception->getMessage());
    $errorMessage = 'The account could not be created. Please try again.';
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
    <meta name="description" content="Create a Student Portal account using secure MySQLi registration and password hashing.">
    <title>Practical 9: Secure Registration | Student Portal</title>
    <link rel="stylesheet" href="registration.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="registration.js" defer></script>
</head>
<body>
<main class="signup-shell">
    <a class="brand" href="../index.html"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> Student Portal</a>
    <section class="signup-card">
        <div class="heading-icon"><i class="fa-solid fa-user-shield" aria-hidden="true"></i></div>
        <p class="eyebrow">PRACTICAL 9</p>
        <h1>Create your account</h1>
        <p class="intro">Register securely with a unique username and email address.</p>

        <?php if ($errorMessage !== ''): ?>
            <p class="notice error" role="alert"><?= signupEscape($errorMessage) ?></p>
        <?php endif; ?>
        <?php if ($successMessage !== ''): ?>
            <p class="notice success" role="status"><?= signupEscape($successMessage) ?></p>
            <p class="signup-prompt">You can now <a href="login.php">log in to your account</a>.</p>
        <?php endif; ?>

        <?php if (!$databaseAvailable): ?>
            <div class="setup-note">
                <h2>Database setup required</h2>
                <p>Start MySQL in XAMPP, then import <code>php/database-schema.sql</code> in phpMyAdmin. That script creates the users and registration audit tables as well as the Practical 8 tables.</p>
                <p>To use a custom database password or host, set the <code>STUDENT_PORTAL_DB_HOST</code>, <code>STUDENT_PORTAL_DB_NAME</code>, <code>STUDENT_PORTAL_DB_USER</code>, and <code>STUDENT_PORTAL_DB_PASSWORD</code> environment variables.</p>
            </div>
        <?php else: ?>
            <form method="post" action="registration.php" class="signup-form">
                <input type="hidden" name="csrf_token" value="<?= signupEscape($_SESSION['signup_csrf']) ?>">

                <label for="username">Username</label>
                <input id="username" name="username" type="text" minlength="3" maxlength="30" pattern="[A-Za-z0-9_.-]{3,30}" autocomplete="username" required value="<?= signupEscape($formValues['username']) ?>" aria-describedby="username-help">
                <small id="username-help">3–30 letters, numbers, dots, hyphens, or underscores.</small>

                <label for="email">Email address</label>
                <input id="email" name="email" type="email" maxlength="254" autocomplete="email" required value="<?= signupEscape($formValues['email']) ?>">

                <label for="password">Password</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required aria-describedby="password-help">
                <small id="password-help">Use 8–72 bytes. Passwords are case-sensitive.</small>

                <label for="confirm-password">Confirm password</label>
                <input id="confirm-password" name="confirm_password" type="password" autocomplete="new-password" required>

                <button class="button" type="submit">Create account</button>
            </form>
        <?php endif; ?>

        <?php if ($successMessage === ''): ?>
            <p class="signup-prompt">Already registered? <a href="login.php">Log in</a>.</p>
        <?php endif; ?>
        <a class="back-link" href="../index.html"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to portal</a>
    </section>
    <p class="security-note"><i class="fa-solid fa-lock" aria-hidden="true"></i> Passwords are hashed before they are stored.</p>
</main>
</body>
</html>

<?php
declare(strict_types=1);

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
ini_set('session.use_strict_mode', '1');
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => $isHttps,
]);

if (!isset($_SESSION['auth_user_id'], $_SESSION['auth_username'], $_SESSION['auth_role'])) {
    $_SESSION['login_notice'] = 'Please log in with an administrator account to manage database records.';
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

if ($_SESSION['auth_role'] !== 'admin') {
    if ($_SESSION['auth_role'] === 'student') {
        header('Location: dashboard.php', true, 303);
        exit;
    }
    http_response_code(403);
    exit('Your account role is not authorized to manage database records.');
}

$_SESSION['last_activity'] = time();

if (empty($_SESSION['database_csrf'])) {
    $_SESSION['database_csrf'] = bin2hex(random_bytes(32));
}

$successMessage = $_SESSION['database_notice'] ?? '';
unset($_SESSION['database_notice']);

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirectWithNotice(string $message): void
{
    $_SESSION['database_notice'] = $message;
    header('Location: database.php', true, 303);
    exit;
}

function requiredField(string $key, string $label, int $limit): string
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
        throw new InvalidArgumentException("Enter a {$label} of up to {$limit} characters.");
    }

    return $value;
}

$pdo = null;
$errorMessage = '';

try {
    $pdo = require __DIR__ . '/db.php';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !hash_equals($_SESSION['database_csrf'], $token)) {
            throw new InvalidArgumentException('Your form session expired. Reload the page and try again.');
        }

        $action = $_POST['action'] ?? '';
        if ($action === 'add_student') {
            $name = requiredField('name', 'student name', 80);
            if (preg_match("/^[\\p{L}\\p{M} .'-]+$/u", $name) !== 1) {
                throw new InvalidArgumentException('Use letters, spaces, apostrophes, periods, or hyphens for the student name.');
            }

            $email = requiredField('email', 'email address', 254);
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException('Enter a valid email address.');
            }
            $year = filter_var($_POST['year'] ?? null, FILTER_VALIDATE_INT);
            if ($year === false || $year < 1 || $year > 8) {
                throw new InvalidArgumentException('Choose a study year from 1 to 8.');
            }

            $statement = $pdo->prepare(
                'INSERT INTO students (name, email, program, department, year, country, state, city)
                 VALUES (:name, :email, :program, :department, :year, :country, :state, :city)'
            );
            $statement->execute([
                'name' => $name,
                'email' => $email,
                'program' => requiredField('program', 'program', 120),
                'department' => requiredField('department', 'department', 80),
                'year' => $year,
                'country' => requiredField('country', 'country', 80),
                'state' => requiredField('state', 'state or province', 80),
                'city' => requiredField('city', 'city', 80),
            ]);
            redirectWithNotice('Student added successfully.');
        } elseif ($action === 'add_event') {
            $title = requiredField('title', 'event title', 120);
            $category = requiredField('category', 'event category', 60);
            $eventDate = requiredField('event_date', 'event date', 10);
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $eventDate);
            if ($date === false || $date->format('Y-m-d') !== $eventDate) {
                throw new InvalidArgumentException('Choose a valid event date.');
            }

            $statement = $pdo->prepare(
                'INSERT INTO events (title, category, event_date, location, description)
                 VALUES (:title, :category, :event_date, :location, :description)'
            );
            $statement->execute([
                'title' => $title,
                'category' => $category,
                'event_date' => $eventDate,
                'location' => requiredField('location', 'event location', 120),
                'description' => requiredField('description', 'event description', 1000),
            ]);
            redirectWithNotice('Event added successfully.');
        } elseif ($action === 'register') {
            $studentId = filter_var($_POST['student_id'] ?? null, FILTER_VALIDATE_INT);
            $eventId = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT);
            if ($studentId === false || $studentId < 1 || $eventId === false || $eventId < 1) {
                throw new InvalidArgumentException('Choose a valid student and event.');
            }

            $statement = $pdo->prepare(
                'INSERT INTO registrations (student_id, event_id) VALUES (:student_id, :event_id)'
            );
            $statement->execute(['student_id' => $studentId, 'event_id' => $eventId]);
            redirectWithNotice('Student registered for the event.');
        } else {
            throw new InvalidArgumentException('Choose a valid database action.');
        }
    }
} catch (InvalidArgumentException $exception) {
    $errorMessage = $exception->getMessage();
} catch (PDOException $exception) {
    error_log('Student portal database error: ' . $exception->getMessage());
    $driverCode = isset($exception->errorInfo[1]) ? (int) $exception->errorInfo[1] : 0;
    if ($driverCode === 1062) {
        $errorMessage = 'That email address, event, or student registration already exists.';
    } else {
        $errorMessage = 'The database request failed. Check that MySQL is running and the Practical 8 schema is imported.';
    }
}

$students = [];
$events = [];
$registrations = [];
$loadError = '';
if ($pdo instanceof PDO) {
    try {
        $students = $pdo->query(
            'SELECT id, name, email, program, department, year, country, state, city
             FROM students ORDER BY id DESC'
        )->fetchAll();
        $events = $pdo->query(
            'SELECT id, title, category, event_date, location, description
             FROM events ORDER BY event_date, id'
        )->fetchAll();
        $registrations = $pdo->query(
            'SELECT registrations.id, students.name AS student_name, events.title AS event_title,
                    events.event_date, registrations.registered_at
             FROM registrations
             INNER JOIN students ON students.id = registrations.student_id
             INNER JOIN events ON events.id = registrations.event_id
             ORDER BY registrations.registered_at DESC'
        )->fetchAll();
    } catch (PDOException $exception) {
        error_log('Student portal database read error: ' . $exception->getMessage());
        $loadError = 'Could not load records. Import the Practical 8 schema and sample data, then reload this page.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Practical 8 MySQL database demonstration for Student Portal.">
    <title>Practical 8: MySQL Database | Student Portal</title>
    <link rel="stylesheet" href="database.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<div class="portal-shell">
    <aside class="sidebar">
        <a class="logo" href="../index.html">Student<br>Portal</a>
        <nav class="main-nav" aria-label="Main navigation">
            <a href="../index.html"><i class="fa-solid fa-house" aria-hidden="true"></i>Home</a>
            <a href="../profile.html"><i class="fa-solid fa-user" aria-hidden="true"></i>Profile</a>
            <a href="../course.html"><i class="fa-solid fa-book-open" aria-hidden="true"></i>Courses</a>
            <a href="../attendance.html"><i class="fa-solid fa-chart-pie" aria-hidden="true"></i>Attendance</a>
            <a href="../timetable.html"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i>Timetable</a>
            <a href="../assignments.html"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i>Assignments</a>
            <a href="../result.html"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i>Results</a>
            <a href="../notice.html"><i class="fa-solid fa-bell" aria-hidden="true"></i>Notes</a>
            <a href="../help.html"><i class="fa-solid fa-circle-question" aria-hidden="true"></i>Help</a>
            <a href="registration.php"><i class="fa-solid fa-user-plus" aria-hidden="true"></i>Sign Up</a>
            <a href="login.php"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>Log in</a>
            <a href="database.php" class="active" aria-current="page"><i class="fa-solid fa-database" aria-hidden="true"></i>Database</a>
        </nav>
    </aside>

    <main class="dashboard">
        <header class="page-heading">
            <div class="heading-icon"><i class="fa-solid fa-database" aria-hidden="true"></i></div>
            <div>
                <p class="eyebrow">PRACTICAL 8</p>
                <h1>MySQL Database</h1>
                <p>PDO connection, normalized tables, prepared statements, and student event registrations.</p>
            </div>
        </header>

        <?php if ($errorMessage !== ''): ?>
            <p class="notice error" role="alert"><?= escapeHtml($errorMessage) ?></p>
        <?php endif; ?>
        <?php if ($successMessage !== ''): ?>
            <p class="notice success" role="status"><?= escapeHtml($successMessage) ?></p>
        <?php endif; ?>
        <?php if ($loadError !== ''): ?>
            <p class="notice error" role="alert"><?= escapeHtml($loadError) ?></p>
        <?php endif; ?>
        <?php if ($pdo === null): ?>
            <section class="setup-note">
                <h2>Database setup required</h2>
                <p>Start MySQL in XAMPP, import <code>php/database-schema.sql</code> in phpMyAdmin, and check the connection settings in <code>php/db.php</code>.</p>
                <p>The default connection is <code>127.0.0.1</code>, database <code>studenthub</code>, user <code>root</code>, and an empty password. Set the <code>STUDENT_PORTAL_DB_HOST</code>, <code>STUDENT_PORTAL_DB_NAME</code>, <code>STUDENT_PORTAL_DB_USER</code>, and <code>STUDENT_PORTAL_DB_PASSWORD</code> environment variables to use different values.</p>
            </section>
        <?php else: ?>
            <section class="forms-grid" aria-label="Database forms">
                <form class="panel form-panel" method="post">
                    <h2><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Add student</h2>
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($_SESSION['database_csrf']) ?>">
                    <input type="hidden" name="action" value="add_student">
                    <div class="field-grid">
                        <label>Name<input name="name" maxlength="80" required></label>
                        <label>Email<input name="email" type="email" maxlength="254" required></label>
                        <label>Program<input name="program" maxlength="120" required></label>
                        <label>Department<input name="department" maxlength="80" required></label>
                        <label>Year
                            <select name="year" required>
                                <option value="">Select year</option>
                                <?php for ($year = 1; $year <= 8; $year++): ?>
                                    <option value="<?= $year ?>"><?= $year ?></option>
                                <?php endfor; ?>
                            </select>
                        </label>
                        <label>Country<input name="country" maxlength="80" required></label>
                        <label>State / Province<input name="state" maxlength="80" required></label>
                        <label>City<input name="city" maxlength="80" required></label>
                    </div>
                    <button class="button" type="submit">Save student</button>
                </form>

                <form class="panel form-panel" method="post">
                    <h2><i class="fa-solid fa-calendar-plus" aria-hidden="true"></i> Add event</h2>
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($_SESSION['database_csrf']) ?>">
                    <input type="hidden" name="action" value="add_event">
                    <div class="field-grid">
                        <label>Title<input name="title" maxlength="120" required></label>
                        <label>Category<input name="category" maxlength="60" required></label>
                        <label>Date<input name="event_date" type="date" required></label>
                        <label>Location<input name="location" maxlength="120" required></label>
                        <label class="full-width">Description<textarea name="description" maxlength="1000" rows="3" required></textarea></label>
                    </div>
                    <button class="button" type="submit">Save event</button>
                </form>

                <form class="panel form-panel registration-form" method="post">
                    <h2><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Register for an event</h2>
                    <input type="hidden" name="csrf_token" value="<?= escapeHtml($_SESSION['database_csrf']) ?>">
                    <input type="hidden" name="action" value="register">
                    <div class="field-grid registration-fields">
                        <label>Student
                            <select name="student_id" required>
                                <option value="">Select student</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?= (int) $student['id'] ?>"><?= escapeHtml($student['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Event
                            <select name="event_id" required>
                                <option value="">Select event</option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?= (int) $event['id'] ?>"><?= escapeHtml($event['title']) ?> — <?= escapeHtml($event['event_date']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                    <button class="button" type="submit" <?= $students === [] || $events === [] ? 'disabled' : '' ?>>Register student</button>
                </form>
            </section>

            <section class="panel table-panel">
                <div class="section-heading"><div><p class="eyebrow">STUDENT RECORDS</p><h2>Students <span class="count"><?= count($students) ?></span></h2></div></div>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Program</th><th>Department</th><th>Year</th><th>Location</th></tr></thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr><td><?= (int) $student['id'] ?></td><td><?= escapeHtml($student['name']) ?></td><td><?= escapeHtml($student['email']) ?></td><td><?= escapeHtml($student['program']) ?></td><td><?= escapeHtml($student['department']) ?></td><td><?= (int) $student['year'] ?></td><td><?= escapeHtml($student['city'] . ', ' . $student['state'] . ', ' . $student['country']) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ($students === []): ?><p class="empty-state">No students yet. Add a student above.</p><?php endif; ?>
                </div>
            </section>

            <section class="panel table-panel">
                <div class="section-heading"><div><p class="eyebrow">CAMPUS ACTIVITIES</p><h2>Events <span class="count"><?= count($events) ?></span></h2></div></div>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>ID</th><th>Title</th><th>Category</th><th>Date</th><th>Location</th><th>Description</th></tr></thead>
                        <tbody>
                            <?php foreach ($events as $event): ?>
                                <tr><td><?= (int) $event['id'] ?></td><td><?= escapeHtml($event['title']) ?></td><td><?= escapeHtml($event['category']) ?></td><td><?= escapeHtml($event['event_date']) ?></td><td><?= escapeHtml($event['location']) ?></td><td><?= escapeHtml($event['description']) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ($events === []): ?><p class="empty-state">No events yet. Add an event above.</p><?php endif; ?>
                </div>
            </section>

            <section class="panel table-panel" id="registrations">
                <div class="section-heading"><div><p class="eyebrow">STUDENT ↔ EVENT</p><h2>Registrations <span class="count"><?= count($registrations) ?></span></h2></div></div>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>ID</th><th>Student</th><th>Event</th><th>Event date</th><th>Registered at</th></tr></thead>
                        <tbody>
                            <?php foreach ($registrations as $registration): ?>
                                <tr><td><?= (int) $registration['id'] ?></td><td><?= escapeHtml($registration['student_name']) ?></td><td><?= escapeHtml($registration['event_title']) ?></td><td><?= escapeHtml($registration['event_date']) ?></td><td><?= escapeHtml($registration['registered_at']) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ($registrations === []): ?><p class="empty-state">No registrations yet. Register a student above.</p><?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
        <a class="back-link" href="../index.html"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to portal</a>
    </main>
</div>
</body>
</html>

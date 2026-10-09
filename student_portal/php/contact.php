<?php
declare(strict_types=1);

session_start();

if (empty($_SESSION['contact_csrf'])) {
    $_SESSION['contact_csrf'] = bin2hex(random_bytes(32));
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function inputLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function csvSafeValue(string $value): string
{
    return preg_match('/^[\s]*[=+\-@]/u', $value) === 1 ? "'" . $value : $value;
}

$errors = [];
$success = isset($_GET['sent']) && $_GET['sent'] === '1';
$values = ['name' => '', 'email' => '', 'message' => ''];
$storageParent = dirname(__DIR__, 2);
$documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
$resolvedStorageParent = realpath($storageParent);
if ($documentRoot !== false && $resolvedStorageParent !== false) {
    $documentRoot = rtrim(str_replace('\\', '/', strtolower($documentRoot)), '/');
    $resolvedStorageParent = rtrim(str_replace('\\', '/', strtolower($resolvedStorageParent)), '/');
    if ($resolvedStorageParent === $documentRoot || strpos($resolvedStorageParent, $documentRoot . '/') === 0) {
        $storageParent = sys_get_temp_dir();
    }
}
$storageDirectory = $storageParent . DIRECTORY_SEPARATOR . 'student-portal-private-data';
$storageFile = $storageDirectory . '/contact-submissions.csv';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $field => $default) {
        $posted = $_POST[$field] ?? '';
        $values[$field] = is_string($posted) ? trim($posted) : '';
    }
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['contact_csrf'], $token)) {
        $errors[] = 'Your form session expired. Reload the page and try again.';
    }

    foreach ($values as $field => $value) {
        if (preg_match('//u', $value) !== 1) {
            $errors[] = 'Please use valid text in all fields.';
            break;
        }
        $values[$field] = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    }

    if ($values['name'] === '' || inputLength($values['name']) > 80 || preg_match("/^[\\p{L}\\p{M} .'-]+$/u", $values['name']) !== 1) {
        $errors[] = 'Enter a name up to 80 characters using letters, spaces, apostrophes, periods, or hyphens.';
    }
    if (strlen($values['email']) > 254 || filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = 'Enter a valid email address.';
    }
    if ($values['message'] === '' || inputLength($values['message']) > 2000) {
        $errors[] = 'Enter a message of up to 2,000 characters.';
    }

    if ($errors === []) {
        if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0700, true) && !is_dir($storageDirectory)) {
            $errors[] = 'We could not prepare secure storage. Please try again later.';
        } else {
            $handle = @fopen($storageFile, 'c+');
            if ($handle === false || !flock($handle, LOCK_EX)) {
                if (is_resource($handle)) {
                    fclose($handle);
                }
                $errors[] = 'We could not save your message. Please try again later.';
            } else {
                fseek($handle, 0, SEEK_END);
                $isNewFile = ftell($handle) === 0;
                $written = true;
                if ($isNewFile) {
                    $written = fputcsv($handle, ['Submitted at', 'Name', 'Email', 'Message'], ',', '"', '\\') !== false;
                }
                if ($written) {
                    $written = fputcsv($handle, [date(DATE_ATOM), csvSafeValue($values['name']), csvSafeValue($values['email']), csvSafeValue($values['message'])], ',', '"', '\\') !== false;
                }
                fflush($handle);
                flock($handle, LOCK_UN);
                fclose($handle);
                @chmod($storageFile, 0600);

                if ($written) {
                    $_SESSION['contact_csrf'] = bin2hex(random_bytes(32));
                    header('Location: contact.php?sent=1', true, 303);
                    exit;
                }
                $errors[] = 'We could not save your message. Please try again later.';
            }
        }
    }
}

$records = [];
if (is_readable($storageFile) && ($handle = fopen($storageFile, 'r')) !== false) {
    fgetcsv($handle, 0, ',', '"', '\\');
    while (($record = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        if (count($record) === 4) {
            $records[] = $record;
        }
    }
    fclose($handle);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Contact student support with server-side form validation and secure file storage.">
    <title>Contact Support | Student Portal</title>
    <link rel="stylesheet" href="../CSS%20Files/help.css">
    <link rel="stylesheet" href="contact.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<div class="portal-shell">
    <aside class="sidebar">
        <a class="logo" href="../index.html">Student<br>Portal</a>
        <nav class="main-nav" aria-label="Main navigation">
            <a href="../index.html"><span class="nav-icon"><i class="fa-solid fa-house" aria-hidden="true"></i></span>Home</a>
            <a href="../profile.html"><span class="nav-icon"><i class="fa-solid fa-user" aria-hidden="true"></i></span>Profile</a>
            <a href="../course.html"><span class="nav-icon"><i class="fa-solid fa-book-open" aria-hidden="true"></i></span>Courses</a>
            <a href="../attendance.html"><span class="nav-icon"><i class="fa-solid fa-chart-pie" aria-hidden="true"></i></span>Attendance</a>
            <a href="../timetable.html"><span class="nav-icon"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i></span>Timetable</a>
            <a href="../assignments.html"><span class="nav-icon"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i></span>Assignments</a>
            <a href="../result.html"><span class="nav-icon"><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i></span>Results</a>
            <a href="../notice.html"><span class="nav-icon"><i class="fa-solid fa-bell" aria-hidden="true"></i></span>Notes</a>
            <a href="../help.html" class="active" aria-current="page"><span class="nav-icon"><i class="fa-solid fa-circle-question" aria-hidden="true"></i></span>Help</a>
            <a href="../json-explorer.html"><span class="nav-icon"><i class="fa-solid fa-database" aria-hidden="true"></i></span>Data View</a>
        </nav>
    </aside>

    <main class="dashboard container contact-page">
        <header class="page-heading">
            <div class="heading-icon"><i class="fa-solid fa-headset" aria-hidden="true"></i></div>
            <div>
                <p class="eyebrow">STUDENT SUPPORT</p>
                <h1>Contact Us</h1>
                <p>Send a message to the student support team.</p>
            </div>
        </header>

        <?php if ($success): ?>
            <p class="notice success" role="status">Your message was submitted successfully.</p>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
            <div class="notice error" role="alert">
                <p>Please check the following:</p>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= escapeHtml($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form class="contact-form server-form" action="contact.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= escapeHtml($_SESSION['contact_csrf']) ?>">
            <label for="name">Name</label>
            <input id="name" name="name" type="text" maxlength="80" autocomplete="name" required value="<?= escapeHtml($values['name']) ?>">

            <label for="email">Email</label>
            <input id="email" name="email" type="email" maxlength="254" autocomplete="email" required value="<?= escapeHtml($values['email']) ?>">

            <label for="message">Message</label>
            <textarea id="message" name="message" rows="5" maxlength="2000" required><?= escapeHtml($values['message']) ?></textarea>

            <button class="btn" type="submit"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Send Message</button>
        </form>

        <section class="submissions" aria-labelledby="submissions-title">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">CSV FILE STORAGE</p>
                    <h2 id="submissions-title">Recent submissions</h2>
                </div>
                <span class="record-count"><?= count($records) ?> <?= count($records) === 1 ? 'record' : 'records' ?></span>
            </div>
            <?php if ($records === []): ?>
                <p class="empty-state">No messages have been submitted yet.</p>
            <?php else: ?>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th scope="col">Submitted</th><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Message</th></tr></thead>
                        <tbody>
                            <?php foreach (array_reverse($records) as $record): ?>
                                <tr>
                                    <td><?= escapeHtml($record[0]) ?></td>
                                    <td><?= escapeHtml($record[1]) ?></td>
                                    <td><?= escapeHtml($record[2]) ?></td>
                                    <td><?= nl2br(escapeHtml($record[3])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <a class="back-link" href="../help.html"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to Help</a>
    </main>
</div>
</body>
</html>
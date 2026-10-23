<?php
declare(strict_types=1);

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
ini_set('session.use_strict_mode', '1');
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => $isHttps,
]);

$lastActivity = filter_var($_SESSION['last_activity'] ?? null, FILTER_VALIDATE_INT);
$loginTime = filter_var($_SESSION['login_time'] ?? null, FILTER_VALIDATE_INT);
if (
    !isset($_SESSION['auth_user_id'], $_SESSION['auth_username'], $_SESSION['auth_role'])
    || $lastActivity === false || $loginTime === false
    || time() - $lastActivity > 900
    || time() - $loginTime > 28800
) {
    $_SESSION['login_notice'] = 'Please log in with an administrator account to manage records.';
    header('Location: login.php', true, 303);
    exit;
}
if ($_SESSION['auth_role'] !== 'admin') {
    if ($_SESSION['auth_role'] === 'student') {
        header('Location: dashboard.php', true, 303);
        exit;
    }
    http_response_code(403);
    exit('Your account role is not authorized to manage records.');
}

$_SESSION['last_activity'] = time();
if (empty($_SESSION['dashboard_csrf'])) {
    $_SESSION['dashboard_csrf'] = bin2hex(random_bytes(32));
}
$username = htmlspecialchars((string) $_SESSION['auth_username'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$csrfToken = htmlspecialchars((string) $_SESSION['dashboard_csrf'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$logoutToken = htmlspecialchars((string) ($_SESSION['logout_csrf'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$portalRole = htmlspecialchars((string) $_SESSION['auth_role'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="description" content="Search and manage StudentHub student and event records.">
    <meta name="csrf-token" content="<?= $csrfToken ?>">
    <title>Manage Records | Student Portal</title>
    <link rel="stylesheet" href="database.css">
    <link rel="stylesheet" href="../CSS%20Files/portal-nav.css">
    <link rel="stylesheet" href="../CSS%20Files/records.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="../Java%20Script%20Files/portal-nav.js" defer></script>
    <script src="../Java%20Script%20Files/records.js" defer></script>
</head>
<body data-portal-role="<?= $portalRole ?>">
<div class="portal-shell">
    <aside class="sidebar">
        <a class="logo" href="../index.html">Student<br>Portal</a>
        <nav class="main-nav" aria-label="Administrator navigation">
            <a href="admin-dashboard.php"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i>Dashboard</a>
            <a href="manage-records.php" class="active" aria-current="page"><i class="fa-solid fa-users-gear" aria-hidden="true"></i>Manage records</a>
            <a href="database.php"><i class="fa-solid fa-database" aria-hidden="true"></i>Database setup</a>
            <a href="../index.html"><i class="fa-solid fa-house" aria-hidden="true"></i>Portal home</a>
        </nav>
        <form method="post" action="logout.php" class="records-logout">
            <input type="hidden" name="csrf_token" value="<?= $logoutToken ?>">
            <button type="submit"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> Log out <?= $username ?></button>
        </form>
    </aside>

    <main class="dashboard records-dashboard">
        <header class="page-heading">
            <div class="heading-icon"><i class="fa-solid fa-users-gear" aria-hidden="true"></i></div>
            <div>
                <p class="eyebrow">PRACTICALS 11–13</p>
                <h1>Manage records</h1>
                <p>Add, search, edit, and remove student and event records.</p>
            </div>
        </header>

        <section class="record-manager" aria-label="Student and event management">
            <div class="record-manager-heading">
                <div class="record-tabs" role="tablist" aria-label="Record type">
                    <button type="button" class="record-tab active" role="tab" aria-selected="true" data-collection="students">Students</button>
                    <button type="button" class="record-tab" role="tab" aria-selected="false" data-collection="events">Events</button>
                </div>
                <button type="button" class="button add-record-button" id="add-record-button">
                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Add record
                </button>
            </div>

            <div class="record-filters">
                <label class="record-search">
                    <span class="visually-hidden">Search records</span>
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input id="record-search" type="search" placeholder="Search name, email, program..." autocomplete="off">
                </label>
                <label for="record-filter" class="visually-hidden">Filter records</label>
                <select id="record-filter" aria-label="Filter records">
                    <option value="">All departments</option>
                </select>
            </div>

            <p id="record-status" class="record-status" role="status" aria-live="polite">Loading records...</p>
            <div class="table-scroll">
                <table class="records-table">
                    <thead id="records-head"></thead>
                    <tbody id="records-body"></tbody>
                </table>
            </div>
            <div class="record-pagination">
                <span id="record-count">0 records</span>
                <div>
                    <button type="button" id="previous-page" class="pagination-button" disabled>Previous</button>
                    <span id="record-page">Page 1 of 1</span>
                    <button type="button" id="next-page" class="pagination-button" disabled>Next</button>
                </div>
            </div>
        </section>
        <a class="back-link" href="admin-dashboard.php"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Back to admin dashboard</a>
    </main>
</div>

<dialog id="student-dialog" class="record-dialog">
    <form id="student-form" class="record-dialog-content">
        <div class="dialog-heading">
            <div><p class="eyebrow">STUDENT RECORD</p><h2 id="student-dialog-title">Add student</h2></div>
            <button type="button" class="dialog-close" data-dialog-close aria-label="Close dialog">&times;</button>
        </div>
        <input type="hidden" name="id">
        <div class="record-fields">
            <label>Name<input name="name" maxlength="80" required></label>
            <label>Email<input name="email" type="email" maxlength="254" required></label>
            <label>Program<input name="program" maxlength="120" required></label>
            <label>Department<input name="department" maxlength="80" required></label>
            <label>Year<input name="year" type="number" min="1" max="8" required></label>
            <label>Country<input name="country" maxlength="80" required></label>
            <label>State / province<input name="state" maxlength="80" required></label>
            <label>City<input name="city" maxlength="80" required></label>
        </div>
        <p class="record-form-feedback" aria-live="polite"></p>
        <div class="record-dialog-actions">
            <button type="button" class="pagination-button" data-dialog-close>Cancel</button>
            <button type="submit" class="button">Save student</button>
        </div>
    </form>
</dialog>

<dialog id="event-dialog" class="record-dialog">
    <form id="event-form" class="record-dialog-content" enctype="multipart/form-data">
        <div class="dialog-heading">
            <div><p class="eyebrow">CAMPUS EVENT</p><h2 id="event-dialog-title">Add event</h2></div>
            <button type="button" class="dialog-close" data-dialog-close aria-label="Close dialog">&times;</button>
        </div>
        <input type="hidden" name="id">
        <div class="record-fields">
            <label>Title<input name="title" maxlength="120" required></label>
            <label>Category<input name="category" maxlength="60" required></label>
            <label>Date<input name="event_date" type="date" required></label>
            <label>Location<input name="location" maxlength="120" required></label>
            <label class="record-field-wide">Description<textarea name="description" maxlength="1000" rows="4" required></textarea></label>
            <label class="record-field-wide">Poster (JPEG, PNG, or WebP; max 2 MB)<input name="poster" type="file" accept="image/jpeg,image/png,image/webp"></label>
        </div>
        <p class="record-form-feedback" aria-live="polite"></p>
        <div class="record-dialog-actions">
            <button type="button" class="pagination-button" data-dialog-close>Cancel</button>
            <button type="submit" class="button">Save event</button>
        </div>
    </form>
</dialog>
</body>
</html>

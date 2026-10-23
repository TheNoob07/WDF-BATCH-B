<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function apiRespond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function apiText(mixed $value, string $label, int $limit): string
{
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

function apiPositiveId(mixed $value): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT);
    if ($id === false || $id < 1) {
        throw new InvalidArgumentException('Choose a valid record.');
    }
    return $id;
}

function apiStudentFields(array $input): array
{
    $name = apiText($input['name'] ?? null, 'student name', 80);
    if (preg_match("/^[\\p{L}\\p{M} .'-]+$/u", $name) !== 1) {
        throw new InvalidArgumentException('Use letters, spaces, apostrophes, periods, or hyphens for the student name.');
    }
    $email = apiText($input['email'] ?? null, 'email address', 254);
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new InvalidArgumentException('Enter a valid email address.');
    }
    $year = filter_var($input['year'] ?? null, FILTER_VALIDATE_INT);
    if ($year === false || $year < 1 || $year > 8) {
        throw new InvalidArgumentException('Choose a study year from 1 to 8.');
    }
    return [
        'name' => $name,
        'email' => $email,
        'program' => apiText($input['program'] ?? null, 'program', 120),
        'department' => apiText($input['department'] ?? null, 'department', 80),
        'year' => $year,
        'country' => apiText($input['country'] ?? null, 'country', 80),
        'state' => apiText($input['state'] ?? null, 'state or province', 80),
        'city' => apiText($input['city'] ?? null, 'city', 80),
    ];
}

function apiEventFields(array $input): array
{
    $dateValue = apiText($input['event_date'] ?? null, 'event date', 10);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
    if ($date === false || $date->format('Y-m-d') !== $dateValue) {
        throw new InvalidArgumentException('Choose a valid event date.');
    }
    return [
        'title' => apiText($input['title'] ?? null, 'event title', 120),
        'category' => apiText($input['category'] ?? null, 'event category', 60),
        'event_date' => $dateValue,
        'location' => apiText($input['location'] ?? null, 'event location', 120),
        'description' => apiText($input['description'] ?? null, 'event description', 1000),
    ];
}

function apiAudit(mysqli $connection, int $actorId, string $action, string $entity, ?int $entityId, string $details): void
{
    $statement = $connection->prepare(
        'INSERT INTO audit_logs (actor_id, action, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?)'
    );
    $statement->bind_param('issis', $actorId, $action, $entity, $entityId, $details);
    $statement->execute();
    $statement->close();
}

function apiSavePoster(): ?string
{
    $upload = $_FILES['poster'] ?? null;
    if (!is_array($upload)) {
        return null;
    }
    $uploadError = filter_var($upload['error'] ?? null, FILTER_VALIDATE_INT);
    $temporaryPath = $upload['tmp_name'] ?? null;
    $uploadSize = filter_var($upload['size'] ?? null, FILTER_VALIDATE_INT);
    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (
        $uploadError !== UPLOAD_ERR_OK
        || !is_string($temporaryPath)
        || !is_uploaded_file($temporaryPath)
        || $uploadSize === false
    ) {
        throw new InvalidArgumentException('The poster upload did not complete. Choose the image again.');
    }
    if ($uploadSize < 1 || $uploadSize > 2 * 1024 * 1024) {
        throw new InvalidArgumentException('Choose an image no larger than 2 MB.');
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($temporaryPath);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!is_string($mimeType) || !isset($extensions[$mimeType])) {
        throw new InvalidArgumentException('Posters must be JPEG, PNG, or WebP images.');
    }

    $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'events';
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('The event poster folder could not be created.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
    if (!move_uploaded_file($temporaryPath, $directory . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('The event poster could not be saved. Check folder permissions.');
    }
    return 'uploads/events/' . $filename;
}

function apiRemovePoster(?string $posterPath): void
{
    if (
        $posterPath === null
        || preg_match('#^uploads/events/[a-f0-9]{32}\.(?:jpg|png|webp)$#', $posterPath) !== 1
    ) {
        return;
    }
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $posterPath);
    if (is_file($path) && !unlink($path)) {
        error_log('Student portal could not remove event poster: ' . $path);
    }
}

ini_set('session.use_strict_mode', '1');
$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => $isHttps,
]);

$lastActivity = filter_var($_SESSION['last_activity'] ?? null, FILTER_VALIDATE_INT);
$loginTime = filter_var($_SESSION['login_time'] ?? null, FILTER_VALIDATE_INT);
if (
    !isset($_SESSION['auth_user_id'], $_SESSION['auth_role'])
    || $_SESSION['auth_role'] !== 'admin'
    || $lastActivity === false || $loginTime === false
    || time() - $lastActivity > 900
    || time() - $loginTime > 28800
) {
    apiRespond(401, ['error' => 'Log in as an administrator to manage records.']);
}
$_SESSION['last_activity'] = time();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $collection = $_GET['collection'] ?? '';
    if (!in_array($collection, ['students', 'events'], true)) {
        apiRespond(400, ['error' => 'Choose Students or Events.']);
    }
    $query = $_GET['q'] ?? '';
    $filter = $_GET['filter'] ?? '';
    if (!is_string($query) || strlen($query) > 100 || !is_string($filter) || strlen($filter) > 80) {
        apiRespond(400, ['error' => 'Search and filter values are too long.']);
    }
    $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
    if ($page === false || $page < 1) {
        apiRespond(400, ['error' => 'Choose a valid page.']);
    }

    $host = getenv('STUDENT_PORTAL_DB_HOST') ?: '127.0.0.1';
    $database = getenv('STUDENT_PORTAL_DB_NAME') ?: 'studenthub';
    $username = getenv('STUDENT_PORTAL_DB_USER') ?: 'root';
    $password = getenv('STUDENT_PORTAL_DB_PASSWORD');
    $password = $password === false ? '' : $password;
    $connection = null;
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $connection = new mysqli($host, $username, $password, $database);
        $connection->set_charset('utf8mb4');
        $pattern = '%' . $query . '%';
        $limit = 10;

        if ($collection === 'students') {
            $count = $connection->prepare(
                "SELECT COUNT(*) FROM students
                 WHERE (? = '' OR name LIKE ? OR email LIKE ? OR program LIKE ?)
                   AND (? = '' OR department = ?)"
            );
            $count->bind_param('ssssss', $query, $pattern, $pattern, $pattern, $filter, $filter);
            $count->execute();
            $total = (int) $count->get_result()->fetch_row()[0];
            $count->close();
            $page = min($page, max(1, (int) ceil($total / $limit)));
            $offset = ($page - 1) * $limit;

            $statement = $connection->prepare(
                "SELECT id, name, email, program, department, year, country, state, city
                 FROM students
                 WHERE (? = '' OR name LIKE ? OR email LIKE ? OR program LIKE ?)
                   AND (? = '' OR department = ?)
                 ORDER BY name, id LIMIT ? OFFSET ?"
            );
            $statement->bind_param('ssssssii', $query, $pattern, $pattern, $pattern, $filter, $filter, $limit, $offset);
            $statement->execute();
            $records = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
            $statement->close();
            $filterRows = $connection->query('SELECT DISTINCT department FROM students ORDER BY department')
                ->fetch_all(MYSQLI_NUM);
            $filters = array_column($filterRows, 0);
        } else {
            $count = $connection->prepare(
                "SELECT COUNT(*) FROM events
                 WHERE (? = '' OR title LIKE ? OR category LIKE ? OR location LIKE ?)
                   AND (? = '' OR category = ?)"
            );
            $count->bind_param('ssssss', $query, $pattern, $pattern, $pattern, $filter, $filter);
            $count->execute();
            $total = (int) $count->get_result()->fetch_row()[0];
            $count->close();
            $page = min($page, max(1, (int) ceil($total / $limit)));
            $offset = ($page - 1) * $limit;

            $statement = $connection->prepare(
                "SELECT id, title, category, event_date, location, description, poster_path
                 FROM events
                 WHERE (? = '' OR title LIKE ? OR category LIKE ? OR location LIKE ?)
                   AND (? = '' OR category = ?)
                 ORDER BY event_date, id LIMIT ? OFFSET ?"
            );
            $statement->bind_param('ssssssii', $query, $pattern, $pattern, $pattern, $filter, $filter, $limit, $offset);
            $statement->execute();
            $records = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
            $statement->close();
            $filterRows = $connection->query('SELECT DISTINCT category FROM events ORDER BY category')
                ->fetch_all(MYSQLI_NUM);
            $filters = array_column($filterRows, 0);
        }

        foreach ($records as &$record) {
            if ($collection === 'events') {
                $record['poster_url'] = $record['poster_path'] === null ? null : '../' . $record['poster_path'];
                $record['id'] = (int) $record['id'];
            } else {
                $record['id'] = (int) $record['id'];
                $record['year'] = (int) $record['year'];
            }
        }
        unset($record);
        apiRespond(200, [
            'records' => $records,
            'filters' => $filters,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $limit)),
        ]);
    } catch (mysqli_sql_exception $exception) {
        error_log('Student portal management API read error: ' . $exception->getMessage());
        apiRespond(500, ['error' => 'Records could not be loaded. Check the MySQL setup and Practical 11–14 migration.']);
    } finally {
        if ($connection instanceof mysqli) {
            $connection->close();
        }
    }
}

if ($method !== 'POST') {
    header('Allow: GET, POST');
    apiRespond(405, ['error' => 'Use GET to list records or POST to change a record.']);
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$sessionToken = $_SESSION['dashboard_csrf'] ?? '';
if (
    !is_string($csrfToken)
    || !is_string($sessionToken)
    || $sessionToken === ''
    || !hash_equals($sessionToken, $csrfToken)
) {
    apiRespond(403, ['error' => 'Your form session expired. Reload the page and try again.']);
}

$contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
if (strpos($contentType, 'application/json') !== false) {
    $rawBody = file_get_contents('php://input');
    $payload = is_string($rawBody) && strlen($rawBody) <= 20000
        ? json_decode($rawBody, true)
        : null;
} else {
    $payload = $_POST;
}
if (!is_array($payload)) {
    apiRespond(400, ['error' => 'The submitted record is invalid or too large.']);
}

$collection = $payload['collection'] ?? '';
$operation = $payload['operation'] ?? '';
if (!in_array($collection, ['students', 'events'], true) || !in_array($operation, ['create', 'update', 'delete'], true)) {
    apiRespond(400, ['error' => 'Choose a valid record action.']);
}

$recordId = null;
try {
    if ($operation !== 'create') {
        $recordId = apiPositiveId($payload['id'] ?? null);
    }
    $record = $operation === 'delete'
        ? []
        : ($collection === 'students' ? apiStudentFields($payload) : apiEventFields($payload));
    $newPoster = $collection === 'events' && $operation !== 'delete' ? apiSavePoster() : null;
} catch (InvalidArgumentException $exception) {
    apiRespond(422, ['error' => $exception->getMessage()]);
} catch (RuntimeException $exception) {
    error_log('Student portal poster upload error: ' . $exception->getMessage());
    apiRespond(500, ['error' => $exception->getMessage()]);
}

$host = getenv('STUDENT_PORTAL_DB_HOST') ?: '127.0.0.1';
$database = getenv('STUDENT_PORTAL_DB_NAME') ?: 'studenthub';
$username = getenv('STUDENT_PORTAL_DB_USER') ?: 'root';
$password = getenv('STUDENT_PORTAL_DB_PASSWORD');
$password = $password === false ? '' : $password;
$connection = null;
$oldPoster = null;
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $connection = new mysqli($host, $username, $password, $database);
    $connection->set_charset('utf8mb4');
    $connection->begin_transaction();

    if ($collection === 'students') {
        if ($operation === 'delete') {
            $statement = $connection->prepare('DELETE FROM students WHERE id = ?');
            $statement->bind_param('i', $recordId);
            $statement->execute();
            if ($statement->affected_rows === 0) {
                throw new InvalidArgumentException('That student record no longer exists.');
            }
            $statement->close();
        } elseif ($operation === 'create') {
            $statement = $connection->prepare(
                'INSERT INTO students (name, email, program, department, year, country, state, city)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->bind_param(
                'ssssisss',
                $record['name'],
                $record['email'],
                $record['program'],
                $record['department'],
                $record['year'],
                $record['country'],
                $record['state'],
                $record['city']
            );
            $statement->execute();
            $recordId = $connection->insert_id;
            $statement->close();
        } else {
            $statement = $connection->prepare(
                'UPDATE students SET name = ?, email = ?, program = ?, department = ?, year = ?,
                    country = ?, state = ?, city = ? WHERE id = ?'
            );
            $statement->bind_param(
                'ssssisssi',
                $record['name'],
                $record['email'],
                $record['program'],
                $record['department'],
                $record['year'],
                $record['country'],
                $record['state'],
                $record['city'],
                $recordId
            );
            $statement->execute();
            if ($statement->affected_rows === 0) {
                $exists = $connection->prepare('SELECT id FROM students WHERE id = ?');
                $exists->bind_param('i', $recordId);
                $exists->execute();
                $exists->store_result();
                $recordExists = $exists->num_rows > 0;
                $exists->close();
                if (!$recordExists) {
                    throw new InvalidArgumentException('That student record no longer exists.');
                }
            }
            $statement->close();
        }
    } else {
        if ($operation === 'delete') {
            $lookup = $connection->prepare('SELECT poster_path FROM events WHERE id = ? FOR UPDATE');
            $lookup->bind_param('i', $recordId);
            $lookup->execute();
            $lookup->bind_result($oldPoster);
            if (!$lookup->fetch()) {
                $lookup->close();
                throw new InvalidArgumentException('That event record no longer exists.');
            }
            $lookup->close();
            $statement = $connection->prepare('DELETE FROM events WHERE id = ?');
            $statement->bind_param('i', $recordId);
            $statement->execute();
            $statement->close();
        } elseif ($operation === 'create') {
            $statement = $connection->prepare(
                'INSERT INTO events (title, category, event_date, location, description, poster_path)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $statement->bind_param(
                'ssssss',
                $record['title'],
                $record['category'],
                $record['event_date'],
                $record['location'],
                $record['description'],
                $newPoster
            );
            $statement->execute();
            $recordId = $connection->insert_id;
            $statement->close();
        } else {
            $lookup = $connection->prepare('SELECT poster_path FROM events WHERE id = ? FOR UPDATE');
            $lookup->bind_param('i', $recordId);
            $lookup->execute();
            $lookup->bind_result($oldPoster);
            if (!$lookup->fetch()) {
                $lookup->close();
                throw new InvalidArgumentException('That event record no longer exists.');
            }
            $lookup->close();
            $posterPath = $newPoster ?? $oldPoster;
            $statement = $connection->prepare(
                'UPDATE events SET title = ?, category = ?, event_date = ?, location = ?,
                    description = ?, poster_path = ? WHERE id = ?'
            );
            $statement->bind_param(
                'ssssssi',
                $record['title'],
                $record['category'],
                $record['event_date'],
                $record['location'],
                $record['description'],
                $posterPath,
                $recordId
            );
            $statement->execute();
            $statement->close();
        }
    }

    $action = $collection . '_' . ($operation === 'create' ? 'created' : ($operation === 'update' ? 'updated' : 'deleted'));
    $details = $operation === 'delete'
        ? 'Record deleted'
        : (string) ($record['name'] ?? $record['title']);
    apiAudit($connection, (int) $_SESSION['auth_user_id'], $action, $collection, $recordId, $details);
    $connection->commit();

    if ($operation === 'delete' || ($operation === 'update' && $newPoster !== null && $oldPoster !== $newPoster)) {
        apiRemovePoster($oldPoster);
    }
    apiRespond(200, [
        'message' => ucfirst($collection === 'events' ? 'Event' : 'Student') . ' ' . $operation . 'd successfully.',
        'id' => $recordId,
    ]);
} catch (InvalidArgumentException $exception) {
    if ($connection instanceof mysqli) {
        $connection->rollback();
    }
    apiRemovePoster($newPoster);
    apiRespond(422, ['error' => $exception->getMessage()]);
} catch (mysqli_sql_exception $exception) {
    if ($connection instanceof mysqli) {
        $connection->rollback();
    }
    apiRemovePoster($newPoster);
    error_log('Student portal management API write error: ' . $exception->getMessage());
    $code = (int) $exception->getCode();
    apiRespond($code === 1062 ? 409 : 500, [
        'error' => $code === 1062
            ? 'That email address or event and date already exists.'
            : 'The database change failed. Check the MySQL setup and try again.',
    ]);
} finally {
    if ($connection instanceof mysqli) {
        $connection->close();
    }
}

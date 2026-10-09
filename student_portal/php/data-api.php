<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function textLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function requiredText(array $input, string $key, string $label, int $limit): string
{
    $value = $input[$key] ?? null;
    if (!is_string($value)) {
        throw new InvalidArgumentException("Enter a valid {$label}.");
    }
    $value = trim($value);
    if ($value === '' || preg_match('//u', $value) !== 1 || textLength($value) > $limit || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1) {
        throw new InvalidArgumentException("Enter a valid {$label} up to {$limit} characters.");
    }
    return $value;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, ['error' => 'Use POST to add a record.']);
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$originHost = parse_url($origin, PHP_URL_HOST);
$originPort = parse_url($origin, PHP_URL_PORT);
$originAuthority = is_string($originHost) ? strtolower($originHost . ($originPort ? ':' . $originPort : '')) : '';
$requestAuthority = strtolower($_SERVER['HTTP_HOST'] ?? '');
if ($originAuthority === '' || $requestAuthority === '' || !hash_equals($requestAuthority, $originAuthority)) {
    respond(403, ['error' => 'This request is not allowed. Reload Data View and try again.']);
}

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || strlen($rawBody) > 20000) {
    respond(400, ['error' => 'The submitted data is invalid or too large.']);
}
$payload = json_decode($rawBody, true);
if (!is_array($payload) || !is_array($payload['record'] ?? null)) {
    respond(400, ['error' => 'The submitted record is invalid.']);
}

$collection = $payload['collection'] ?? '';
$input = $payload['record'];
try {
    if ($collection === 'events') {
        $record = [
            'title' => requiredText($input, 'title', 'event title', 120),
            'category' => requiredText($input, 'category', 'event category', 60),
            'date' => requiredText($input, 'date', 'event date', 10),
            'location' => requiredText($input, 'location', 'event location', 120),
            'description' => requiredText($input, 'description', 'event description', 1000)
        ];
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $record['date']);
        if ($date === false || $date->format('Y-m-d') !== $record['date']) {
            throw new InvalidArgumentException('Choose a valid event date.');
        }
        $fileName = 'events.json';
    } elseif ($collection === 'students') {
        $name = requiredText($input, 'name', 'student name', 80);
        if (preg_match("/^[\\p{L}\\p{M} .'-]+$/u", $name) !== 1) {
            throw new InvalidArgumentException('Use letters, spaces, apostrophes, periods, or hyphens for the student name.');
        }
        $email = requiredText($input, 'email', 'email address', 254);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }
        $year = filter_var($input['year'] ?? null, FILTER_VALIDATE_INT);
        if ($year === false || $year < 1 || $year > 8) {
            throw new InvalidArgumentException('Choose a study year from 1 to 8.');
        }
        $record = [
            'name' => $name,
            'program' => requiredText($input, 'program', 'program', 120),
            'department' => requiredText($input, 'department', 'department', 80),
            'year' => $year,
            'email' => $email,
            'country' => requiredText($input, 'country', 'country', 80),
            'state' => requiredText($input, 'state', 'state or province', 80),
            'city' => requiredText($input, 'city', 'city', 80)
        ];
        $fileName = 'students.json';
    } else {
        respond(400, ['error' => 'Choose Events or Students to add a record.']);
    }
} catch (InvalidArgumentException $error) {
    respond(422, ['error' => $error->getMessage()]);
}

$projectRoot = dirname(__DIR__);
$dataFile = $projectRoot . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . $fileName;
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
$lockDirectory = $storageParent . DIRECTORY_SEPARATOR . 'student-portal-private-data';
if (!is_dir($lockDirectory) && !mkdir($lockDirectory, 0700, true) && !is_dir($lockDirectory)) {
    respond(500, ['error' => 'Could not prepare storage. Check the PHP server folder permissions.']);
}
$lock = @fopen($lockDirectory . DIRECTORY_SEPARATOR . $collection . '.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX)) {
    if (is_resource($lock)) {
        fclose($lock);
    }
    respond(500, ['error' => 'Could not lock the data file. Try again.']);
}

$contents = @file_get_contents($dataFile);
$records = is_string($contents) ? json_decode($contents, true) : null;
if (!is_array($records)) {
    flock($lock, LOCK_UN);
    fclose($lock);
    respond(500, ['error' => 'The JSON data file could not be read.']);
}

$ids = array_map(static function ($existing): int {
    return is_array($existing) ? (int) ($existing['id'] ?? 0) : 0;
}, $records);
$record = ['id' => ($ids === [] ? 0 : max($ids)) + 1] + $record;
$records[] = $record;
$encoded = json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$temporaryFile = $encoded === false ? false : tempnam(dirname($dataFile), '.data-');
if ($temporaryFile === false || file_put_contents($temporaryFile, $encoded . PHP_EOL, LOCK_EX) === false || !@rename($temporaryFile, $dataFile)) {
    if (is_string($temporaryFile) && is_file($temporaryFile)) {
        @unlink($temporaryFile);
    }
    flock($lock, LOCK_UN);
    fclose($lock);
    respond(500, ['error' => 'Could not save the JSON data. Check that the data folder is writable.']);
}

flock($lock, LOCK_UN);
fclose($lock);
respond(201, ['record' => $record]);
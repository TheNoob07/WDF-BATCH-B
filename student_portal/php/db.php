<?php
declare(strict_types=1);

$host = getenv('STUDENT_PORTAL_DB_HOST') ?: '127.0.0.1';
$database = getenv('STUDENT_PORTAL_DB_NAME') ?: 'studenthub';
$username = getenv('STUDENT_PORTAL_DB_USER') ?: 'root';
$password = getenv('STUDENT_PORTAL_DB_PASSWORD');
$password = $password === false ? '' : $password;

$dsn = "mysql:host={$host};dbname={$database};charset=utf8mb4";

return new PDO($dsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

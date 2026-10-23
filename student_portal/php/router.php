<?php
declare(strict_types=1);

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';

if ($requestPath === '/') {
    require __DIR__ . '/login.php';
    return true;
}

if ($requestPath === '/login.html') {
    header('Location: /php/login.php', true, 303);
    return true;
}

if (preg_match('~^/([A-Za-z0-9_-]+\.html)$~i', $requestPath, $matches) === 1) {
    $_GET['path'] = $matches[1];
    require __DIR__ . '/protected-page.php';
    return true;
}

return false;

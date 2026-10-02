<?php

declare(strict_types=1);

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);

if ($path === '/redirect') {
    header('Location: /final', true, 302);
    exit;
}

if ($path === '/final') {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><title>final</title>';
    exit;
}

http_response_code(404);
header('Content-Type: text/plain; charset=UTF-8');
echo 'not found';

<?php
// Маршрутизатор встроенного веб-сервера PHP.
// Запуск: php -S localhost:3000 router.php
// Обеспечивает «чистые» адреса API из Прил_5: GET /api/notes и GET /notes.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/api/notes' || $path === '/notes') {
    require __DIR__ . '/api/notes.php';
    exit;
}
if ($path === '/') {
    require __DIR__ . '/index.php';
    exit;
}
// Во всех остальных случаях файл существует — пусть сервер отдаст его сам
return false;
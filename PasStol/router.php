<?php
// Маршрутизатор встроенного веб-сервера PHP.
// Запуск: php -S localhost:3000 router.php
// Обеспечивает «чистые» адреса API из Прил_5: GET /api/notes и GET /notes.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Прямой доступ к резервным копиям запрещён (в них есть хеши паролей и персональные
// данные): скачивание копий — только через раздел «Импорт/Экспорт» (do=backup_get).
if ($path === '/BackUp' || strncmp($path, '/BackUp/', 8) === 0) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Доступ к папке BackUp через веб-сервер запрещён. Скачивание копий — в разделе «Импорт/Экспорт».');
}

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
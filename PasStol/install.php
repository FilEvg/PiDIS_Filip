<?php
require __DIR__ . '/config.php';

function make_hash(string $p): string
{
    // По ТЗ — Argon2id; если сборка PHP без него — используем bcrypt
    return defined('PASSWORD_ARGON2ID')
        ? password_hash($p, PASSWORD_ARGON2ID)
        : password_hash($p, PASSWORD_DEFAULT);
}

$pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('USE `' . DB_NAME . '`');

$sql = file_get_contents(__DIR__ . '/schema.sql');
foreach (preg_split('/;\s*[\r\n]+/', $sql) as $stmt) {
    $stmt = trim($stmt);
    if ($stmt !== '') $pdo->exec($stmt);
}

// Демо-пользователи (пароли соответствуют политике ТЗ)
$users = [
    ['admin',    make_hash('Admin7x2'),  'администратор'],
    ['operator', make_hash('Operat0r'),  'оператор'],
    ['user',     make_hash('User2026q'), 'пользователь'],
];
$st = $pdo->prepare('INSERT IGNORE INTO users (login, password_hash, role, mfa_secret, last_password_change)
                     VALUES (?, ?, ?, ?, CURDATE())');
foreach ($users as $u) {
    $st->execute([$u[0], $u[1], $u[2], crypto_encrypt(base64_encode(random_bytes(16)))]); // в проде mfa_secret шифруется AES-256-GCM
}

$pdo->exec("INSERT IGNORE INTO citizens
    (citizen_id, last_name, first_name, middle_name, birth_date, sex, phone, email, reg_address, reg_status, reg_start_date) VALUES
    (1, 'Иванов',  'Иван',   'Иванович',   '1990-05-12', 'М', '+79170000001', 'ivanov@mail.ru',  'г. Казань, ул. Баумана, д. 12, кв. 34', 'активна', '2018-03-01'),
    (2, 'Петрова', 'Анна',   'Сергеевна',  '1985-11-03', 'Ж', '+79170000002', 'petrova@mail.ru', 'г. Казань, ул. Гагарина, д. 8, кв. 12',  'активна', '2015-07-20'),
    (3, 'Сидоров', 'Пётр',   'Алексеевич', '2000-02-29', 'М', '+79170000003', NULL,               'г. Казань, ул. Ленина, д. 45, кв. 7',    'активна', '2020-01-15')");

$pdo->exec("INSERT IGNORE INTO passports
    (passport_id, citizen_id, series, number, issue_date, issuer, status, reason) VALUES
    (1, 1, '1234', '456789', '2018-04-10', 'ГУ МВД по Республике Татарстан', 'действует', 'Достижение 14 лет')");

$pdo->exec("INSERT IGNORE INTO applications
    (application_id, citizen_id, operator_id, service_type, status, created_at) VALUES
    (1, 1, 2, 'Регистрация гражданина', 'завершено', '2018-03-01 10:00:00')");
?>
<!doctype html>
<html lang="ru"><head><meta charset="utf-8"><title>Установка</title><link rel="stylesheet" href="assets/style.css"></head>
<body><main class="wrap" style="padding:48px 16px">
<div class="card" style="max-width:640px">
<h1>Установка завершена</h1>
<p>База <b><?= e(DB_NAME) ?></b> создана, таблицы и демо-данные загружены.</p>
<table class="tbl"><thead><tr><th>Логин</th><th>Пароль</th><th>Роль</th></tr></thead><tbody>
<tr><td>admin</td><td>Admin7x2</td><td>администратор</td></tr>
<tr><td>operator</td><td>Operat0r</td><td>оператор</td></tr>
<tr><td>user</td><td>User2026q</td><td>пользователь</td></tr>
</tbody></table>
<p style="margin-top:16px"><a class="btn" href="index.php">Перейти к входу →</a></p>
<p class="muted">После установки удалите файл install.php.</p>
</div>
</main></body></html>
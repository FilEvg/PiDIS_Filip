<?php
// ============ build.php — «СБОРЩИК» САЙТА (конструктор по частям) ============
// Запуск:  php build.php
// Собирает/проверяет сайт из частей:
//   1. Проверяет PHP-расширения (pdo_mysql, gd, zip).
//   2. Компилирует C++-генератор PDF (cpp/pdfgen.cpp -> cpp/pdfgen) для Linux (РедОС 8.0+).
//   3. Проверяет подключение к MariaDB и применяет schema.sql.
//   4. Заполняет демо-пользователей и демо-данные (если таблицы пусты).
//   5. Создаёт таблицу notes (Прил_5) и тестовые записи (если их нет).
//   6. Проверяет итоговую структуру (таблицы, файлы, компоненты) и выводит сводку.

declare(strict_types=1);
error_reporting(E_ALL & ~E_DEPRECATED);

function build_log(string $msg): void { echo $msg, PHP_EOL; }
function build_fail(string $msg): void { echo '[ОШИБКА] ', $msg, PHP_EOL; exit(1); }

// Константы DB_* и db() из config.php подключаем до первого вывода,
// чтобы session_start() (в CLI) не выдавал предупреждение о выводе заголовков.
require __DIR__ . '/config.php';

build_log('=== Сборка АИС «Паспортный стол» ===');
build_log('');

// ---- 1) PHP-расширения ----
$requiredExt = [
    'pdo_mysql' => 'PDO MySQL (доступ к MariaDB)',
    'gd'        => 'GD (капча-пазл)',
    'zip'       => 'Zip (экспорт в Excel)',
];
foreach ($requiredExt as $ext => $label) {
    build_log((extension_loaded($ext) ? '[ OK ] ' : '[ X  ] ') . 'расширение ' . $ext . ' — ' . $label);
}

// ---- 2) Компиляция PDF-генератора ----
$src = __DIR__ . '/cpp/pdfgen.cpp';
$bin = __DIR__ . '/cpp/pdfgen';
if (is_file($bin)) {
    build_log('[ OK ] бинарник PDF уже собран: ' . $bin);
} else {
    build_log('[ .. ] компилирую pdfgen (g++ -std=c++17 -O2)...');
    $out = []; $code = 0;
    exec('g++ -std=c++17 -O2 ' . escapeshellarg($src) . ' -o ' . escapeshellarg($bin) . ' 2>&1', $out, $code);
    if ($code !== 0) build_fail('не удалось скомпилировать pdfgen:' . PHP_EOL . implode(PHP_EOL, $out));
    build_log('[ OK ] pdfgen собран: ' . $bin);
}
if (is_file($bin) && !is_executable($bin)) { @chmod($bin, 0755); }

// ---- 3) Подключение к MariaDB и схема ----
try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    build_log('[ OK ] подключение к MariaDB (' . DB_HOST . ')');
} catch (Throwable $ex) {
    build_fail('нет подключения к MariaDB: ' . $ex->getMessage());
}
$pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('USE `' . DB_NAME . '`');
build_log('[ OK ] база `' . DB_NAME . '` готова');

$schema = file_get_contents(__DIR__ . '/schema.sql');
foreach (preg_split('/;\s*[\r\n]+/', $schema) as $stmt) {
    $stmt = trim($stmt);
    if ($stmt !== '') $pdo->exec($stmt);
}
build_log('[ OK ] schema.sql применён (CREATE TABLE IF NOT EXISTS)');

// ---- 4) Демо-пользователи (INSERT IGNORE — идемпотентно) ----
function build_make_hash(string $p): string
{
    return defined('PASSWORD_ARGON2ID') ? password_hash($p, PASSWORD_ARGON2ID) : password_hash($p, PASSWORD_DEFAULT);
}
$demoUsers = [
    ['admin',    build_make_hash('Admin7x2'),  'администратор'],
    ['operator', build_make_hash('Operat0r'),  'оператор'],
    ['user',     build_make_hash('User2026q'), 'пользователь'],
];
$stUser = $pdo->prepare('INSERT IGNORE INTO users (login, password_hash, role, mfa_secret, last_password_change)
                         VALUES (?, ?, ?, ?, CURDATE())');
foreach ($demoUsers as $u) $stUser->execute([$u[0], $u[1], $u[2], crypto_encrypt(base64_encode(random_bytes(16)))]);
build_log('[ OK ] демо-пользователи готовы (admin/operator/user)');

// Демо-граждане/паспорт/заявление — только если таблица citizens пуста
if ((int)$pdo->query('SELECT COUNT(*) FROM citizens')->fetchColumn() === 0) {
    $pdo->exec("INSERT INTO citizens (citizen_id, last_name, first_name, middle_name, birth_date, sex, phone, email, reg_address, reg_status, reg_start_date) VALUES
        (1, 'Иванов',  'Иван',   'Иванович',  '1990-05-12', 'М', '+79170000001', 'ivanov@mail.ru',  'г. Казань, ул. Баумана, д. 12, кв. 34', 'активна', '2018-03-01'),
        (2, 'Петрова', 'Анна',   'Сергеевна', '1985-11-03', 'Ж', '+79170000002', 'petrova@mail.ru', 'г. Казань, ул. Гагарина, д. 8, кв. 12',  'активна', '2015-07-20'),
        (3, 'Сидоров', 'Пётр',   'Алексеевич','2000-02-29', 'М', '+79170000003', NULL,              'г. Казань, ул. Ленина, д. 45, кв. 7',    'активна', '2020-01-15')");
    $pdo->exec("INSERT INTO passports (passport_id, citizen_id, series, number, issue_date, issuer, status, reason) VALUES
        (1, 1, '1234', '456789', '2018-04-10', 'ГУ МВД по Республике Татарстан', 'действует', 'Достижение 14 лет')");
    $pdo->exec("INSERT INTO applications (application_id, citizen_id, operator_id, service_type, status, created_at) VALUES
        (1, 1, 2, 'Регистрация гражданина', 'завершено', '2018-03-01 10:00:00')");
    build_log('[ OK ] демо-данные (граждане/паспорт/заявление) загружены');
} else {
    build_log('[ -- ] демо-данные уже есть (citizens не пуста) — пропущено');
}

// ---- 5) Таблица notes (Прил_5) и тестовые записи ----
$pdo->exec('CREATE TABLE IF NOT EXISTS notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    id_user INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notes_user FOREIGN KEY (id_user) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
if ((int)$pdo->query('SELECT COUNT(*) FROM notes')->fetchColumn() === 0) {
    $uidOf = function (string $login) use ($pdo): int {
        return (int)$pdo->query('SELECT user_id FROM users WHERE login = ' . $pdo->quote($login))->fetchColumn();
    };
    $seed = [
        ['Конференция ИТ',          'Доклад об интеграции программных модулей и тестировании API.', $uidOf('admin'),    '2027-03-15 10:30:00'],
        ['Совещание по проекту',     'Обсуждение ER-диаграммы и схемы базы данных производственного учёта.', $uidOf('admin'), '2027-02-01 09:00:00'],
        ['Отчёт за квартал',         'Сводка по заказам покупателей и полной стоимости производства продукции.', $uidOf('operator'), '2026-12-28 17:45:00'],
        ['План внедрения',           'Этапы внедрения информационной системы и обучение персонала.', $uidOf('operator'), '2027-01-20 12:00:00'],
        ['Заметка о тестировании API', 'Перечень тестов коллекции: статус 200, статус 500, валидность JSON.', $uidOf('user'), '2027-04-10 08:15:00'],
    ];
    $stNote = $pdo->prepare('INSERT INTO notes (title, content, id_user, created_at) VALUES (?,?,?,?)');
    foreach ($seed as $n) $stNote->execute($n);
    build_log('[ OK ] таблица notes: добавлено ' . count($seed) . ' тестовых записей');
} else {
    build_log('[ -- ] таблица notes уже заполнена — пропущено');
}

// ---- 6) Проверка итоговой структуры ----
build_log('');
build_log('=== Проверка структуры ===');
$tables = array_column($pdo->query('SHOW TABLES')->fetchAll(), 0);
foreach (['citizens','passports','users','applications','action_log','notes'] as $t) {
    build_log((in_array($t, $tables, true) ? '[ OK ] ' : '[ X  ] ') . 'таблица `' . $t . '`');
}
$requiredFiles = [
    'index.php','verify.php','register.php','dashboard.php','api/notes.php','router.php',
    'config.php','config/modules.php','mfa_lib.php',
    'components/puzzle.php','components/messages.php','components/password_checklist.php',
    'captcha_lib.php','assets/app.js','cpp/pdfgen',
];
foreach ($requiredFiles as $f) {
    build_log((is_file(__DIR__ . '/' . $f) ? '[ OK ] ' : '[ X  ] ') . 'файл ' . $f);
}
build_log('');
build_log('=== Сборка завершена. Запуск сайта: php -S localhost:3000 router.php ===');
build_log('Рекомендация: после первичной установки удалите install.php.');

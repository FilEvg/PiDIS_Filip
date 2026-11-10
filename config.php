<?php
declare(strict_types=1);
session_start();
// ================= НАСТРОЙКИ =================
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'passport_db');
define('DB_USER', 'admin');
define('DB_PASS', 'admin');
// Путь к C++ генератору PDF (собирается из /cpp/pdfgen.cpp, см. build.php).
define('PDFGEN_BIN', __DIR__ . '/cpp/' . (PHP_OS_FAMILY === 'Windows' ? 'pdfgen.exe' : 'pdfgen'));
// Ключ AES-256-GCM (256 бит = 64 hex-символа). В учебном стенде хранится в конфиге;
// в реальной системе ключ выносят в файл/переменную окружения вне репозитория.
define('CRYPTO_KEY_HEX', '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// ================= ШИФРОВАНИЕ (AES-256-GCM, требование 4.2 ТЗ) =================
// Реализация чистым PHP (/crypto_aes_gcm.php): не требует расширений openssl/sodium,
// поэтому сайт переносится на любой ПК без настройки php.ini.
// Формат: "enc1:" + base64(iv[12] || tag[16] || ciphertext) — совместим с тем,
// что ранее писал openssl, поэтому старые данные в БД расшифровываются без миграции.
// Значения без префикса — открытый текст, введённый до включения шифрования.
require_once __DIR__ . '/crypto_aes_gcm.php';

function crypto_key(): string
{
    $bin = hex2bin(CRYPTO_KEY_HEX);
    if ($bin === false || strlen($bin) !== 32) {
        throw new RuntimeException('Некорректный ключ CRYPTO_KEY_HEX: ожидается 64 hex-символа (32 байта)');
    }
    return $bin;
}

function crypto_encrypt(?string $plain): ?string
{
    if ($plain === null || $plain === '' || strncmp($plain, 'enc1:', 5) === 0) return $plain;
    $iv = random_bytes(12);
    $r  = AesGcm::encrypt(crypto_key(), $iv, $plain);
    return 'enc1:' . base64_encode($iv . $r['tag'] . $r['ct']);
}

function crypto_decrypt(?string $value): ?string
{
    if ($value === null || $value === '' || strncmp($value, 'enc1:', 5) !== 0) return $value;
    $raw = base64_decode(substr($value, 5), true);
    if ($raw === false || strlen($raw) < 29) return $value;
    $iv  = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $ct  = substr($raw, 28);
    return AesGcm::decrypt(crypto_key(), $iv, $ct, $tag);
}

// Колонки с персональными/паспортными данными, шифруемые при хранении (этап 2).
const ENC_COLS = [
    'citizens'  => ['last_name', 'first_name', 'middle_name', 'phone', 'email', 'reg_address'],
    'passports' => ['series', 'number', 'issuer', 'reason'],
];
function enc_row(string $table, array $row): array
{
    foreach ((ENC_COLS[$table] ?? []) as $c) if (array_key_exists($c, $row)) $row[$c] = crypto_encrypt($row[$c]);
    return $row;
}
function dec_row(string $table, array $row): array
{
    foreach ((ENC_COLS[$table] ?? []) as $c) if (isset($row[$c]) && is_string($row[$c])) $row[$c] = crypto_decrypt($row[$c]);
    return $row;
}

// Состояния журнала (before_json/after_json): JSON шифруется целиком и кладётся
// в JSON-колонку как JSON-строка ("enc1:…"), поэтому json_valid() остаётся истинным.
function log_state_write(?array $state): ?string
{
    if ($state === null) return null;
    return json_encode(crypto_encrypt(json_encode($state, JSON_UNESCAPED_UNICODE)), JSON_UNESCAPED_UNICODE);
}
function log_state_read(?string $column): ?array
{
    if ($column === null) return null;
    $decoded = json_decode($column, true);
    if (is_string($decoded)) $decoded = json_decode(crypto_decrypt($decoded), true); // зашифрованное состояние
    return is_array($decoded) ? $decoded : null;                                    // legacy: открытый JSON
}

// ================= Обработка ошибок =================
function log_details(Throwable $ex): void
{
    error_log('[АИС Паспортный стол] ' . get_class($ex) . ': ' . $ex->getMessage()
        . ' | файл: ' . $ex->getFile() . ':' . $ex->getLine());
}

function today_str(): string { return date('Y-m-d'); }
function date_is_future(string $date): bool { return $date !== '' && $date > today_str(); }
function date_is_past(string $date): bool { return $date !== '' && $date < today_str(); }

function min_passport_issue_date(string $birthDate): string
{
    return date('Y-m-d', strtotime($birthDate . ' +14 years'));
}

function user_friendly_error_message(Throwable $ex): string
{
    if ($ex instanceof PDOException) {
        $sqlState = (string)($ex->errorInfo[0] ?? '');
        switch ($sqlState) {
            case '23000': case '23503': case '23505':
                return 'Не удалось сохранить изменения: нарушена связь между записями в базе данных '
                    . '(указана несуществующая запись либо значение, которое должно быть уникальным, уже занято). '
                    . 'Проверьте введённые данные и повторите попытку; если ошибка повторяется — обратитесь к администратору.';
            case '08004': case '08S01':
                return 'Сервер базы данных временно недоступен. Повторите попытку через минуту; '
                    . 'если ошибка повторяется — обратитесь к администратору.';
            case '42S02': case '42S22':
                return 'База данных настроена не полностью: отсутствуют необходимые таблицы или поля. '
                    . 'Обратитесь к администратору для завершения настройки системы.';
        }
    }
    return 'При обработке запроса произошла внутренняя ошибка системы. Повторите действие; '
        . 'если ошибка повторяется — обратитесь к администратору.';
}

set_exception_handler(function (Throwable $ex) {
    log_details($ex);
    $friendly  = user_friendly_error_message($ex);
    $isAuthed  = !empty($_SESSION['user_id']);
    $retryUrl  = htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'index.php', ENT_QUOTES, 'UTF-8');
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8">',
    '<title>Ошибка — АИС «Паспортный стол»</title>',
    '</head><body style="background:#F4F6F8;font:14px/1.6 Roboto,Arial,sans-serif;color:#333333">',
    '<div style="max-width:600px;margin:48px auto;padding:20px 24px;background:#ffffff;border:1px solid #E74C3C;border-radius:12px">',
    '<h3 style="margin:0 0 10px;color:#E74C3C;font-size:18px">Ошибка</h3>',
    '<p style="margin:0 0 16px">', htmlspecialchars($friendly, ENT_QUOTES, 'UTF-8'), '</p>',
    '<p style="margin:0;display:flex;gap:10px;flex-wrap:wrap">',
    '<a href="index.php" style="background:#3498DB;color:#ffffff;border-radius:8px;padding:10px 18px;text-decoration:none">Вернуться на страницу входа</a>',
    $isAuthed
        ? '<a href="dashboard.php" style="background:#3498DB;color:#ffffff;border-radius:8px;padding:10px 18px;text-decoration:none">На главную</a>'
        : '',
    '<a href="' . $retryUrl . '" style="background:#ffffff;color:#2C3E50;border:1px solid #BDC3C7;border-radius:8px;padding:10px 18px;text-decoration:none">Повторить попытку</a>',
    '</p></div></body></html>';
});

// ---- Авто-миграция служебных колонок users ----
function ensure_users_blocked_column(): void
{
    static $checked = false;
    if ($checked) return;
    $checked = true;
    try {
        $st = db()->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_blocked'");
        $st->execute();
        if ((int)$st->fetchColumn() === 0) {
            db()->exec("ALTER TABLE users ADD COLUMN is_blocked TINYINT(1) NOT NULL DEFAULT 0 AFTER failed_attempts");
        }
    } catch (Throwable $ex) {
        log_details($ex);
    }
}
ensure_users_blocked_column();

// ================= CSRF =================
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check(): void
{
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
        throw new RuntimeException('Проверка безопасности формы не пройдена: обновите страницу и отправьте форму ещё раз.');
    }
}

// ================= Сообщения =================
function flash(?string $type = null, ?string $text = null)
{
    if ($type !== null) { $_SESSION['flash'] = [$type, $text]; return; }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// ================= Доступ =================
function require_login(): void
{
    if (empty($_SESSION['user_id'])) { header('Location: index.php'); exit; }
}
function require_role(array $roles): void
{
    require_login();
    if (!in_array($_SESSION['role'] ?? '', $roles, true)) {
        throw new RuntimeException('Доступ к этому разделу запрещён для вашей роли. Обратитесь к администратору, если считаете это ошибкой.');
    }
}
function is_role(string $r): bool { return ($_SESSION['role'] ?? '') === $r; }

// ================= Журнал действий =================
// ТЗ: операции удаления и прямого обновления записей action_log ЗАПРЕЩЕНЫ,
// поэтому журнал больше НЕ обрезается (ранее здесь было удаление старых записей).
// Состояния before/after хранятся в зашифрованном виде (AES-256-GCM).
function log_action(PDO $pdo, string $entity, int $entityId, string $type,
                    ?array $before = null, ?array $after = null, ?int $userId = null): void
{
    $uid = $userId ?? (int)($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
        error_log('[АИС Паспортный стол] log_action пропущена: не определён пользователь (entity=' . $entity . ', type=' . $type . ')');
        return;
    }
    try {
        $check = $pdo->prepare('SELECT 1 FROM users WHERE user_id = ?');
        $check->execute([$uid]);
        if (!$check->fetchColumn()) {
            error_log('[АИС Паспортный стол] log_action пропущена: пользователь #' . $uid . ' не найден (entity=' . $entity . ', type=' . $type . ')');
            return;
        }
        $st = $pdo->prepare('INSERT INTO action_log (action_time, user_id, entity_name, entity_id, action_type, before_json, after_json)
                             VALUES (NOW(), ?, ?, ?, ?, ?, ?)');
        $st->execute([
            $uid, $entity, $entityId, $type,
            log_state_write($before),
            log_state_write($after),
        ]);
    } catch (Throwable $ex) {
        log_details($ex); // журнал недоступен — приложение продолжает работать
    }
}

// ================= Политика паролей (единая для всего сайта) =================
// Запрещены:
//  1) одинаковые символы подряд в любом классе: 11, aa, !! (проверка «Ivn111»);
//  2) последовательности из 2+ СОСЕДНИХ символов алфавита/цифр в любом направлении: 12, 89, ab, ba, 21.
function password_has_forbidden_sequence(string $password): bool
{
    $chars = preg_split('//u', mb_strtolower($password, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);
    $count = count($chars);
    for ($i = 0; $i + 1 < $count; $i++) {
        $a = $chars[$i]; $b = $chars[$i + 1];
        if ($a === $b) return true; // одинаковые символы подряд
        $bothDigits  = ctype_digit($a) && ctype_digit($b);
        $bothLetters = (bool)preg_match('/[a-zа-яё]/u', $a) && (bool)preg_match('/[a-zа-яё]/u', $b);
        if ($bothDigits || $bothLetters) {
            $diff = mb_ord($b, 'UTF-8') - mb_ord($a, 'UTF-8');
            if ($diff === 1 || $diff === -1) return true;
        }
    }
    return false;
}

function password_policy_errors(string $password): array
{
    $errors = [];
    if (mb_strlen($password) < 6)               $errors[] = 'не менее 6 символов';
    if (!preg_match('/\d/', $password))         $errors[] = 'минимум одна цифра';
    if (!preg_match('/[a-zа-яё]/u', $password)) $errors[] = 'минимум одна строчная буква';
    if (!preg_match('/[A-ZА-ЯЁ]/u', $password)) $errors[] = 'минимум одна заглавная буква';
    if (password_has_forbidden_sequence($password)) {
        $errors[] = 'без одинаковых символов подряд (11, aa) и без последовательностей из 2+ соседних символов (12, ab, 89, 21, ba…)';
    }
    return $errors;
}

function password_policy_rules(): array
{
    return [
        'len' => 'не менее 6 символов',
        'dig' => 'одна цифра',
        'low' => 'строчная буква',
        'up'  => 'заглавная буква',
        'seq' => 'без одинаковых символов подряд (11, aa) и без последовательностей из 2+ соседних символов (12, ab, 89, 21, ba…)',
    ];
}

function password_policy_hint(): string
{
    return 'Политика пароля: не менее 6 символов; минимум одна цифра, одна строчная и одна заглавная буква; '
        . 'запрещены одинаковые символы подряд (например, 11, aa) и последовательности из 2+ соседних символов (например, 12, ab, 89, 21, ba).';
}

// ================= Справочники колонок =================
const ENTITY_PK = [
    'citizens' => 'citizen_id', 'passports' => 'passport_id',
    'applications' => 'application_id', 'users' => 'user_id', 'action_log' => 'log_id',
];
const ENTITY_COLS = [
    'citizens'     => ['citizen_id','last_name','first_name','middle_name','birth_date','sex','phone','email','reg_address','reg_status','reg_start_date','reg_end_date'],
    'passports'    => ['passport_id','citizen_id','series','number','issue_date','expiry_date','issuer','status','reason'],
    'applications' => ['application_id','citizen_id','operator_id','service_type','status','created_at','updated_at'],
    'users'        => ['user_id','login','password_hash','role','mfa_secret','last_password_change','failed_attempts','is_blocked','locked_until'],
    'action_log'   => ['log_id','action_time','user_id','entity_name','entity_id','action_type','before_json','after_json'],
];
// Секретные колонки: в журнале хранятся маски, поэтому восстановление из before_json
// НЕ должно их переносить (иначе пароль превращается в литерал '***').
const RESTORE_SKIP_COLUMNS = ['password_hash', 'mfa_secret'];
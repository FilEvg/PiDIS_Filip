<?php
require __DIR__ . '/config.php';
require __DIR__ . '/captcha_lib.php';
require __DIR__ . '/mfa_lib.php';
require __DIR__ . '/components/puzzle.php';
require __DIR__ . '/components/messages.php';

if (!empty($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }

// Возврат на первый шаг входа (например, «Назад к вводу пароля» со второго шага 2FA) —
// сбрасываем незавершённое состояние второго шага, чтобы старый код не «завис» в сессии.
mfa_clear();

$messages = []; // очередь окон сообщений: [тип, текст]

// Заблокирован ли пользователь фактически:
//  - is_blocked != 1            -> нет
//  - is_blocked = 1, срок NULL  -> да (бессрочно, до разблокировки администратором — Приложение 1)
//  - is_blocked = 1, срок в будущем -> да
//  - is_blocked = 1, срок истёк -> авто-разблокировка и возврат «нет»
function user_effectively_blocked(?array $user): bool
{
    if ($user === null) return false;
    if ((int)($user['is_blocked'] ?? 0) !== 1) return false;
    if (empty($user['locked_until'])) return true;
    $ts = strtotime($user['locked_until']);
    if ($ts === false || $ts > time()) return true;
    db()->prepare('UPDATE users SET is_blocked = 0, failed_attempts = 0, locked_until = NULL WHERE user_id = ?')
        ->execute([$user['user_id']]);
    return false;
}

// Учёт НЕУДАЧНОЙ попытки входа. Одна и та же функция вызывается и для непройденной
// капчи, и для неверного пароля — поэтому обе ситуации считаются одинаково и ведут
// к блокировке после 3 подряд неудачных попыток (Приложение 1).
// Возвращает текущее число неудач подряд (0, если учётной записи нет — блокировать нечего).
function auth_register_failure(?array $user): int
{
    if ($user === null) return 0;
    $fails = (int)$user['failed_attempts'] + 1;
    if ($fails >= 3) {
        db()->prepare('UPDATE users SET failed_attempts = 0, is_blocked = 1,
                        locked_until = DATE_ADD(NOW(), INTERVAL 3600 SECOND) WHERE user_id = ?')
            ->execute([$user['user_id']]);
        return 3;
    }
    db()->prepare('UPDATE users SET failed_attempts = ? WHERE user_id = ?')->execute([$fails, $user['user_id']]);
    return $fails;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_check();
        $login = trim($_POST['login'] ?? '');
        $pass  = $_POST['password'] ?? '';
        $place = json_decode($_POST['cap_place'] ?? '', true); // slot -> показанный фрагмент

        $st = db()->prepare('SELECT * FROM users WHERE login = ? AND is_deleted = 0');
        $st->execute([$login]);
        $user = $st->fetch();
        if ($user === false) $user = null; // нормализация: «не найден» = null, а не false

        if (user_effectively_blocked($user)) {
            // заблокированная учётная запись — сообщение из Приложения 1.
            // Попытку входа фиксируем в журнале, но счётчик неудач НЕ инкрементируем
            // (учётная запись уже заблокирована). user_id передаём ЯВНО: сессии ещё нет,
            // иначе log_action отбросил бы запись как «не определён пользователь».
            $messages[] = ['error', 'Вы заблокированы. Обратитесь к администратору'];
            log_action(db(), 'users', (int)$user['user_id'], 'login', null,
                ['result' => 'blocked_attempt'], (int)$user['user_id']);
        } elseif (!cap_is_fresh()) {
            // данные капчи потеряны в сессии (страница открыта слишком долго) —
            // это НЕ «неправильно собранная» пользователем капча, попытку не засчитываем
            $messages[] = ['warning', 'Капча устарела или сессия была сброшена: ниже собран новый пазл. '
                . 'Расставьте все 4 фрагмента и нажмите «Войти» ещё раз.'];
        } else {
            // cap_verify_place() возвращает false и когда фрагменты расставлены не все,
            // и когда порядок неверный — обе ситуации теперь равны неверному паролю
            if (!cap_verify_place($place)) {
                $fails = auth_register_failure($user);
                if ($user !== null && $fails >= 3) {
                    $messages[] = ['error', 'Капча не пройдена: фрагменты не образуют исходное изображение '
                        . '(проверьте, что все 4 фрагмента расставлены на свои места). '
                        . 'Достигнуто 3 неудачных попытки подряд — учётная запись заблокирована на 3600 секунд. Дождитесь окончания блокировки или обратитесь к администратору для досрочной разблокировки. '
                        . 'Для разблокировки обратитесь к администратору.'];
                } else {
                    $messages[] = ['error', 'Капча не пройдена: фрагменты не образуют исходное изображение '
                        . '(проверьте, что все 4 фрагмента расставлены на свои места). Неудачная попытка входа засчитана.'
                        . ($user !== null ? ' Осталось попыток до блокировки: ' . (3 - $fails) . '.' : '')];
                }
                // Явная передача user_id: при неудаче сессия ещё не создана,
                // без 6-го аргумента запись терялась бы защитой внешнего ключа
                if ($user !== null) {
                    log_action(db(), 'users', (int)$user['user_id'], 'login', null,
                        ['result' => 'failed_captcha', 'attempts_in_row' => $fails], (int)$user['user_id']);
                }
            } elseif ($user === null || !password_verify($pass, $user['password_hash'])) {
                // точный текст сообщения из Приложения 1 (дословно)
                $messages[] = ['error', 'Вы ввели неверный логин или пароль. Пожалуйста проверьте ещё раз введенные данные'];
                $fails = auth_register_failure($user);
                if ($user !== null && $fails >= 3) {
                    $messages[] = ['warning', 'Достигнуто 3 неудачных попытки подряд — учётная запись заблокирована на 3600 секунд. Дождитесь окончания блокировки или обратитесь к администратору для досрочной разблокировки.'
                        . 'Для разблокировки обратитесь к администратору.'];
                } elseif ($user !== null) {
                    $messages[] = ['warning', 'Неудачная попытка входа засчитана. Осталось попыток до блокировки: ' . (3 - $fails) . '.'];
                }
                if ($user !== null) {
                    log_action(db(), 'users', (int)$user['user_id'], 'login', null,
                        ['result' => 'failed_password', 'attempts_in_row' => $fails], (int)$user['user_id']);
                }
            } else {
                // Шаг 1 (логин + пароль + капча) пройден: сбрасываем счётчик неудач подряд
                // и переходим ко второму шагу — подтверждению одноразовым кодом 2FA (verify.php).
                db()->prepare('UPDATE users SET failed_attempts = 0 WHERE user_id = ?')->execute([$user['user_id']]);
                mfa_issue_code((int)$user['user_id']); // код появится на экране verify.php
                header('Location: verify.php'); exit;
            }
        }
    } catch (Throwable $ex) {
        // Пользователю — понятный текст и порядок действий, технические детали — в error_log
        log_details($ex);
        $messages[] = ['error', user_friendly_error_message($ex)
            . ' Ниже собран новый пазл: проверьте логин и пароль, соберите пазл и нажмите «Войти» ещё раз.'];
    }
}
cap_generate(); // новая головоломка для каждой попытки входа
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Вход — АИС «Паспортный стол»</title>
<link rel="stylesheet" href="assets/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
  /* стили капчи-пазла — компонент components/puzzle.php */
  <?= component_puzzle_css() ?>
  .auth-card{overflow:hidden}
</style>
</head>
<body class="auth-body">
<main class="auth-wrap">
  <div class="auth-card card">
    <div class="auth-logo"><span class="logo-badge"><i class="fa-solid fa-passport"></i></span>
      <div><b>АИС «Паспортный стол»</b><br><span class="muted">авторизация зарегистрированных пользователей</span></div>
    </div>

    <div class="demo-note"><i class="fa-solid fa-circle-info"></i>
      Демо-доступ: <b>admin / Admin7x2</b> (администратор), <b>operator / Operat0r</b> (оператор),
      <b>user / User2026q</b> (пользователь)</div>

    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <label>Логин *</label>
      <input class="input" name="login" required value="<?= e($_POST['login'] ?? '') ?>">
      <label>Пароль *</label>
      <input class="input" type="password" name="password" required>

      <label>Капча: соберите исходное изображение из фрагментов *</label>
      <?= component_puzzle_html() ?>
      <?= component_puzzle_help() ?>

      <button class="btn btn-block" type="submit"><i class="fa-solid fa-right-to-bracket"></i> Войти</button>
      <p style="text-align:center;margin-top:12px">
        <a href="register.php" style="font-size:13px"><i class="fa-solid fa-user-plus"></i> Нет учётной записи? Зарегистрироваться</a>
      </p>
    </form>
  </div>
</main>
<?= component_auth_messages($messages) ?>
<script src="assets/app.js"></script>
</body>
</html>
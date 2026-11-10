<?php
require __DIR__ . '/config.php';
require __DIR__ . '/captcha_lib.php';
require __DIR__ . '/components/puzzle.php';
require __DIR__ . '/components/messages.php';
require __DIR__ . '/components/password_checklist.php';

if (!empty($_SESSION['user_id'])) { header('Location: dashboard.php'); exit; }

$messages  = []; // очередь окон сообщений: [тип, текст]
$formLogin = trim($_POST['login'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrf_check();
        $login    = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        $repeat   = $_POST['password2'] ?? '';
        $place    = json_decode($_POST['cap_place'] ?? '', true);

        $complete = is_array($place) && count($place) === 4 && !in_array(-1, $place, true);

        if (!cap_is_fresh()) {
            // данные капчи потеряны в сессии — это не ошибка пользователя
            $messages[] = ['warning', 'Капча устарела или сессия была сброшена: ниже собран новый пазл. '
                . 'Расставьте все 4 фрагмента и отправьте форму ещё раз.'];
        } elseif (!$complete) {
            $messages[] = ['warning', 'Пазл собран не полностью: распределите все 4 фрагмента по ячейкам поля '
                . '(перетаскиванием или кликом: фрагмент → ячейка) и отправьте форму ещё раз.'];
        } elseif (!cap_verify_place($place)) {
            $messages[] = ['error', 'Пазл собран неверно: фрагменты не образуют исходное изображение. '
                . 'Ниже собран новый пазл: расставьте фрагменты так, чтобы получилось цельное изображение, и повторите регистрацию.'];
        } elseif ($login === '') {
            $messages[] = ['error', 'Поле «Логин» обязательно для заполнения. Укажите логин (например, ivanov) и повторите регистрацию.'];
        } elseif (mb_strlen($login) > 100) {
            $messages[] = ['error', 'Логин не должен превышать 100 символов: такое значение не поместится в поле таблицы пользователей. '
                . 'Сократите логин и повторите регистрацию.'];
        } elseif ($policyErrors = password_policy_errors($password)) {
            $messages[] = ['error', 'Пароль не соответствует политике: ' . implode('; ', $policyErrors) . '. '
                . 'Исправьте пароль согласно списку правил под полем «Пароль» и повторите регистрацию.'];
        } elseif ($password !== $repeat) {
            $messages[] = ['error', 'Пароли в полях «Пароль» и «Повтор пароля» не совпадают. '
                . 'Введите одинаковый пароль в оба поля и повторите регистрацию.'];
        } else {
            // проверка уникальности логина (та же логика, что у администратора)
        $st = db()->prepare('SELECT user_id, is_deleted FROM users WHERE login = ?');
        $st->execute([$login]);
        $existingUser = $st->fetch();
        if ($existingUser && (int)$existingUser['is_deleted'] === 1) {
                $messages[] = ['error', 'Логин «' . e($login) . '» занят удалённой (архивной) учётной записью и не может быть использован снова. '
                    . 'Выберите другой логин и повторите регистрацию.'];
            } elseif ($existingUser) {
                $messages[] = ['error', 'Пользователь с логином «' . e($login) . '» уже существует в базе данных. '
                    . 'Выберите другой логин и повторите регистрацию.'];
            } else {
                $hash = defined('PASSWORD_ARGON2ID') ? password_hash($password, PASSWORD_ARGON2ID) : password_hash($password, PASSWORD_DEFAULT);
                db()->prepare('INSERT INTO users (login, password_hash, role, mfa_secret, last_password_change, failed_attempts, is_blocked)
                               VALUES (?,?,?,?,CURDATE(),0,0)')
                    ->execute([$login, $hash, 'пользователь', crypto_encrypt(base64_encode(random_bytes(16)))]);
                $newUserId = (int)db()->lastInsertId();
                log_action(db(), 'users', $newUserId, 'create', null, ['login' => $login, 'role' => 'пользователь'], $newUserId);
                flash('success', 'Вы успешно зарегистрировались. Выполните вход с созданными логином и паролем.');
                header('Location: index.php'); exit;
            }
        }
    } catch (Throwable $ex) {
        // пользователю — понятный текст, технические детали — в error_log
        log_details($ex);
        $messages[] = ['error', user_friendly_error_message($ex)
            . ' Ниже собран новый пазл: заполните форму и повторите регистрацию.'];
    }
}
cap_generate(); // новая головоломка для каждой попытки регистрации
$policyRules = password_policy_rules();
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Регистрация — АИС «Паспортный стол»</title>
<link rel="stylesheet" href="assets/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
  /* стили капчи-пазла — компонент components/puzzle.php */
  <?= component_puzzle_css() ?>
  .auth-card{overflow:hidden}
  /* стили живого чек-листа правил пароля — компонент components/password_checklist.php */
  <?= component_password_checklist_css() ?>
</style>
</head>
<body class="auth-body">
<main class="auth-wrap">
  <div class="auth-card card">
    <div class="auth-logo"><span class="logo-badge"><i class="fa-solid fa-user-plus"></i></span>
      <div><b>АИС «Паспортный стол»</b><br><span class="muted">регистрация нового пользователя</span></div>
    </div>

    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <label>Логин *</label>
      <input class="input" name="login" required maxlength="100" value="<?= e($formLogin) ?>"
             placeholder="например, ivanov">
      <label>Пароль *</label>
      <input class="input" type="password" name="password" id="registerPassword" required>
      <?= component_password_checklist($policyRules) ?>
      <label>Повтор пароля *</label>
      <input class="input" type="password" name="password2" required>

      <label>Капча: соберите исходное изображение из фрагментов *</label>
      <?= component_puzzle_html() ?>
      <?= component_puzzle_help() ?>

      <button class="btn btn-block" type="submit"><i class="fa-solid fa-user-plus"></i> Зарегистрироваться</button>
      <p style="text-align:center;margin-top:12px">
        <a href="index.php" style="font-size:13px"><i class="fa-solid fa-right-to-bracket"></i> Уже есть учётная запись? Войти</a>
      </p>
    </form>
  </div>
</main>
<?= component_auth_messages($messages) ?>
<script src="assets/app.js"></script>
</body>
</html>
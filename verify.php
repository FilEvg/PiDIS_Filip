<?php
require __DIR__ . '/config.php';
require __DIR__ . '/mfa_lib.php';

// Страница доступна только на втором шаге входа (после логина + пароля + капчи).
if (!mfa_has_pending()) { header('Location: index.php'); exit; }

$uid = (int)$_SESSION['mfa_user_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (isset($_POST['resend'])) {
        // Запросить новый код: предыдущий становится недействительным.
        mfa_issue_code($uid);
    } else {
        $code = trim($_POST['code'] ?? '');
        if (time() > ($_SESSION['mfa_exp'] ?? 0)) {
            $error = 'Срок действия одноразового кода (300 с) истёк. Запросите новый.';
        } elseif (!mfa_verify_code($code)) {
            $_SESSION['mfa_fails'] = ($_SESSION['mfa_fails'] ?? 0) + 1;
            if ($_SESSION['mfa_fails'] >= 3) {
                // 3 неверных кода — блокируем учётную запись на 3600 секунд
                // (снять блокировку может администратор в панели администратора).
                db()->prepare('UPDATE users SET failed_attempts = 0, is_blocked = 1, locked_until = DATE_ADD(NOW(), INTERVAL 3600 SECOND) WHERE user_id = ?')
                    ->execute([$uid]);
                log_action(db(), 'users', $uid, 'login', null, ['result' => 'mfa_blocked', 'fails' => 3], $uid);
                mfa_clear();
                flash('err', '3 неверных одноразовых кода. Учётная запись заблокирована на 3600 секунд. Обратитесь к администратору.');
                header('Location: index.php'); exit;
            }
            $error = 'Неверный одноразовый код. Осталось попыток: ' . (3 - $_SESSION['mfa_fails']);
        } else {
            // Второй шаг пройден — заводим полноценную сессию.
            $st = db()->prepare('SELECT * FROM users WHERE user_id = ?'); $st->execute([$uid]);
            $u = $st->fetch();
            if (!$u) { mfa_clear(); header('Location: index.php'); exit; }
            db()->prepare('UPDATE users SET failed_attempts = 0 WHERE user_id = ?')->execute([$uid]);
            $_SESSION['user_id'] = (int)$uid;
            $_SESSION['login']   = $u['login'];
            $_SESSION['role']    = $u['role'];
            $_SESSION['pw_expired'] = strtotime($u['last_password_change']) < strtotime('-90 days');
            log_action(db(), 'users', $uid, 'login', null, ['result' => 'ok', 'factor' => '2fa'], $uid);
            mfa_clear();
            flash('ok', 'Вы успешно авторизовались');
            header('Location: dashboard.php'); exit;
        }
    }
}
$left = max(0, ($_SESSION['mfa_exp'] ?? 0) - time());
// УЧЕБНАЯ СИСТЕМА: код показывается на экране как «демо-код».
$demoCode = (string)($_SESSION['mfa_code'] ?? '');
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Код подтверждения — АИС «Паспортный стол»</title>
<link rel="stylesheet" href="assets/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="auth-body">
<main class="auth-wrap">
  <div class="auth-card card">
    <div class="auth-logo"><span class="logo-badge"><i class="fa-solid fa-mobile-screen"></i></span>
      <div><b>Одноразовый код</b><br><span class="muted">двухфакторная аутентификация · шаг 2 из 2</span></div>
    </div>

    <div class="demo-note"><i class="fa-solid fa-circle-info"></i>
      Демо-код: <b><?= e($demoCode) ?></b></div>

    <?php if ($error): ?><div class="flash err"><i class="fa-solid fa-triangle-exclamation"></i> <?= e($error) ?></div><?php endif; ?>

    <form method="post">
      <?= csrf_field() ?>
      <label>6-значный код</label>
      <input class="input code-input" name="code" inputmode="numeric" maxlength="6" required autofocus>
      <p class="muted">Срок действия кода: <span id="mfaCountdown" data-left="<?= (int)$left ?>"><?= (int)$left ?> с</span></p>
      <button class="btn btn-block" type="submit"><i class="fa-solid fa-shield"></i> Войти</button>
      <button class="btn btn-outline btn-block" type="submit" name="resend" value="1"><i class="fa-solid fa-rotate"></i> Запросить код заново</button>
      <a class="muted" href="index.php" style="font-size:12px">← Назад к вводу пароля</a>
    </form>
  </div>
</main>
<script src="assets/app.js"></script>
</body>
</html>

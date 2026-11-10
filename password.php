<?php
require __DIR__ . '/config.php';
require_login();
require __DIR__ . '/components/password_checklist.php';
$pdo = db();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $uid = (int)$_SESSION['user_id'];
    $st = $pdo->prepare('SELECT * FROM users WHERE user_id = ?'); $st->execute([$uid]);
    $u = $st->fetch();
    if (!password_verify($_POST['old'] ?? '', $u['password_hash'])) {
        $error = 'Текущий пароль указан неверно.';
    } elseif ($err = password_policy_errors($_POST['new'] ?? '')) {
        $error = 'Новый пароль не соответствует политике: ' . implode('; ', $err) . '.';
    } elseif (($_POST['new'] ?? '') !== ($_POST['new2'] ?? '')) {
        $error = 'Пароли не совпадают.';
    } else {
        $hash = defined('PASSWORD_ARGON2ID') ? password_hash($_POST['new'], PASSWORD_ARGON2ID) : password_hash($_POST['new'], PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET password_hash = ?, last_password_change = CURDATE() WHERE user_id = ?')->execute([$hash, $uid]);
        log_action($pdo, 'users', $uid, 'update', ['password_hash' => '***'], ['password_hash' => '***', 'last_password_change' => date('Y-m-d')]);
        $_SESSION['pw_expired'] = false;
        flash('ok', 'Пароль изменён (алгоритм хеширования — Argon2id).');
        header('Location: dashboard.php'); exit;
    }
}
require __DIR__ . '/layout/header.php';
?>
<h1>Смена пароля</h1>
<div class="card" style="max-width:480px">
<?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
<form method="post">
<?= csrf_field() ?>
<label>Текущий пароль</label><input class="input" type="password" name="old" required>
<label>Новый пароль</label><input class="input" type="password" name="new" id="changePassword" required>
<?= component_password_checklist(password_policy_rules(), 'changePassword') ?>
<label>Повтор нового пароля</label><input class="input" type="password" name="new2" required>
<div class="form-actions"><button class="btn"><i class="fa-solid fa-key"></i> Сменить пароль</button></div>
</form>
</div>
<?php require __DIR__ . '/layout/footer.php'; ?>
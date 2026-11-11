<?php
require_once __DIR__ . '/../config.php';
require_login();
// Принудительная смена пароля каждые 90 дней (ТЗ 4.2): пока пароль не сменён,
// доступны только страница смены пароля и выход.
$currentScript = basename($_SERVER['SCRIPT_NAME']);
if (!empty($_SESSION['pw_expired']) && !in_array($currentScript, ['password.php', 'logout.php'], true)) {
    flash('warn', 'Прошло более 90 дней с последней смены пароля: политика безопасности требует обязательной смены. '
        . 'Задайте новый пароль — после этого доступ ко всем разделам откроется автоматически.');
    header('Location: password.php');
    exit;
}
$fl = flash();
$script = $currentScript;
// Меню сайта собирается из реестра модулей (config/modules.php) — «конструктор».
require_once __DIR__ . '/../config/modules.php';
$nav = site_modules();
// тип flash -> тип окна сообщения
$flashType = ['ok' => 'success', 'err' => 'error', 'warn' => 'warning', 'info' => 'info'][$fl[0] ?? 'info'] ?? 'info';
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>АИС «Паспортный стол»</title>
<link rel="stylesheet" href="assets/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
<header class="topbar">
<div class="wrap topbar-in">
<a class="logo" href="dashboard.php">
<span class="logo-badge"><i class="fa-solid fa-passport"></i></span>
<span><b>АИС «Паспортный стол»</b><br><small>учёт граждан · паспорта · справки</small></span>
</a>
<nav class="mainnav">
<?php foreach ($nav as $n): if ($n[3] !== null && !in_array($_SESSION['role'], $n[3], true)) continue; ?>
<a href="<?= $n[0] ?>" class="<?= $script === $n[0] ? 'active' : '' ?>"><i class="fa-solid <?= $n[2] ?>"></i> <?= $n[1] ?></a>
<?php endforeach; ?>
</nav>
<div class="topbar-actions">
<?php if (is_role('администратор')): ?>
<a class="chip" href="admin_panel.php" title="Перейти в панель администратора">
<i class="fa-solid fa-user-shield"></i> Панель администратора</a>
<?php endif; ?>
<a class="chip" href="password.php" title="Смена пароля"><i class="fa-solid fa-key"></i></a>
<span class="chip"><i class="fa-solid fa-user"></i> <?= e($_SESSION['login']) ?> · <?= e($_SESSION['role']) ?></span>
<a class="chip" href="logout.php" title="Выход"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
</div>
</div>
</header>
<main class="wrap page">
<?php if ($fl): ?>
<script>document.addEventListener('DOMContentLoaded', function () {
showMessageWindow(<?= json_encode($flashType, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($fl[1], JSON_UNESCAPED_UNICODE) ?>);
});</script>
<?php endif; ?>
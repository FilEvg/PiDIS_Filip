<?php
// Шапка отдельного пространства администратора:
// своя навигация и оформление, вход только для роли «администратор»,
// переход обратно на обычный сайт — кнопка «К сайту».
require_once __DIR__ . '/../config.php';
require_login();
if (!is_role('администратор')) {
    flash('warn', 'Панель администратора доступна только пользователям с ролью «администратор». Обратитесь к администратору, если вам нужен доступ к этому разделу.');
    header('Location: dashboard.php');
    exit;
}
$flashMessage = flash();
$currentScript = basename($_SERVER['SCRIPT_NAME']);
// Меню панели администратора собирается из реестра модулей (config/modules.php) — «конструктор».
require_once __DIR__ . '/../config/modules.php';
$adminNavigation = admin_modules();
?>
<!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Панель администратора — АИС «Паспортный стол»</title>
<link rel="stylesheet" href="assets/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
  /* визуальное отличие пространства администратора от обычного сайта */
  .admin-topbar{background:#1F2A36;border-bottom:3px solid #F39C12}
  .admin-badge{background:#F39C12;color:#1F2A36;border-radius:6px;padding:2px 8px;font-size:11px;font-weight:700;margin-left:6px}
</style>
</head>
<body>
<header class="topbar admin-topbar">
  <div class="wrap topbar-in">
    <a class="logo" href="admin_panel.php">
      <span class="logo-badge" style="background:#F39C12;color:#1F2A36"><i class="fa-solid fa-user-shield"></i></span>
      <span><b>Панель администратора</b><span class="admin-badge">АИС «Паспортный стол»</span><br>
      <small>пользователи · журнал · структура БД</small></span>
    </a>
    <nav class="mainnav">
      <?php foreach ($adminNavigation as $item): ?>
        <a href="<?= $item[0] ?>" class="<?= $currentScript === $item[0] ? 'active' : '' ?>">
          <i class="fa-solid <?= $item[2] ?>"></i> <?= $item[1] ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="topbar-actions">
      <a class="chip" href="dashboard.php" title="Вернуться на обычный сайт"><i class="fa-solid fa-globe"></i> К сайту</a>
      <span class="chip"><i class="fa-solid fa-user"></i> <?= e($_SESSION['login']) ?></span>
      <a class="chip" href="logout.php" title="Выход"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
    </div>
  </div>
</header>
<main class="wrap page">
<?php if ($flashMessage): ?>
  <script>document.addEventListener('DOMContentLoaded', function () {
    showMessageWindow(<?= json_encode(['ok' => 'success', 'err' => 'error', 'warn' => 'warning', 'info' => 'info'][$flashMessage[0]] ?? 'info', JSON_UNESCAPED_UNICODE) ?>,
                      <?= json_encode($flashMessage[1], JSON_UNESCAPED_UNICODE) ?>);
  });</script>
<?php endif; ?>
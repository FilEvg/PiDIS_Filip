<?php
require __DIR__ . '/config.php';
require_login();
$pdo = db();
$isUser = is_role('пользователь');
$DEMO_CITIZEN = 1; // ДЕМО: привязка аккаунта "user" к гражданину

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'status' && !$isUser) {
        $id = (int)$_POST['id']; $new = $_POST['status'] ?? '';
        $allowed = ['подано', 'в работе', 'завершено', 'отклонено'];
        if (in_array($new, $allowed, true)) {
            $st = $pdo->prepare('SELECT * FROM applications WHERE application_id = ?'); $st->execute([$id]);
            $old = $st->fetch();
            if ($old) {
                $pdo->prepare('UPDATE applications SET status = ?, updated_at = NOW() WHERE application_id = ?')->execute([$new, $id]);
                log_action($pdo, 'applications', $id, 'update', $old, ['status' => $new]);
                flash('ok', 'Статус заявления обновлён.');
            }
        }
        header('Location: applications.php'); exit;
    }
    if (($_POST['action'] ?? '') === 'create') {
        $svc = $_POST['service_type'] ?? '';
        $allowedSvc = ['Регистрация гражданина','Выдача паспорта','Замена паспорта','Снятие с регистрационного учёта','Справка о регистрации','Отчёт'];
        if (in_array($svc, $allowedSvc, true)) {
            // оператор, который возьмёт заявление в работу (демо: первый оператор)
            $operatorId = (int)($pdo->query("SELECT user_id FROM users WHERE role='оператор' AND is_deleted = 0 LIMIT 1")->fetchColumn() ?: $_SESSION['user_id']);
            $pdo->prepare('INSERT INTO applications (citizen_id, operator_id, service_type, status, created_at) VALUES (?,?,?,\'подано\',NOW())')
                ->execute([$DEMO_CITIZEN, $operatorId, $svc]);
            $id = (int)$pdo->lastInsertId();
            log_action($pdo, 'applications', $id, 'create', null, ['service_type' => $svc, 'status' => 'подано']);
            flash('ok', 'Заявление подано, статус «подано».');
        }
        header('Location: applications.php'); exit;
    }
}

$q = trim($_GET['q'] ?? ''); $fs = $_GET['status'] ?? ''; $ft = $_GET['service'] ?? '';
$where = []; $p = [];
if ($isUser) { $where[] = 'a.citizen_id = ?'; $p[] = $DEMO_CITIZEN; }
if ($q !== '') { $where[] = "CONCAT(c.last_name,' ',c.first_name,' ',a.service_type,' ',a.status) LIKE ?"; $p[] = "%$q%"; }
if ($fs !== '') { $where[] = 'a.status = ?'; $p[] = $fs; }
if ($ft !== '') { $where[] = 'a.service_type = ?'; $p[] = $ft; }
$sql = 'SELECT a.*, fn_citizen_full_name(a.citizen_id) AS fio, u.login AS operator_login
        FROM applications a JOIN citizens c ON c.citizen_id=a.citizen_id LEFT JOIN users u ON u.user_id=a.operator_id'
     . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY a.application_id DESC LIMIT 200';
$st = $pdo->prepare($sql); $st->execute($p);
$rows = $st->fetchAll();

require __DIR__ . '/layout/header.php';
?>
<h1>Заявления</h1>

<form class="toolbar" method="get">
  <input class="input" style="flex:1" type="text" name="q" value="<?= e($q) ?>" placeholder="Поиск: ФИО, услуга, статус…">
  <select class="input" name="status"><option value="">Статус: все</option>
    <?php foreach (['подано','в работе','завершено','отклонено'] as $s): ?><option <?= $fs===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
  </select>
  <button class="btn"><i class="fa-solid fa-filter"></i> Фильтр</button>
</form>

<?php if ($isUser): ?>
<div class="card">
  <h2>Подать заявление</h2>
  <form method="post" class="form-inline">
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <select class="input" name="service_type">
      <?php foreach (['Регистрация гражданина','Выдача паспорта','Замена паспорта','Снятие с регистрационного учёта','Справка о регистрации','Отчёт'] as $s): ?>
        <option><?= $s ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn"><i class="fa-solid fa-paper-plane"></i> Подать</button>
  </form>
</div>
<?php endif; ?>

<div class="tbl-wrap">
<table class="tbl">
<thead><tr><th>№</th><th>Гражданин</th><th>Услуга</th><th>Статус</th><th>Оператор</th><th>Создано</th><th>Обновлено</th><?= $isUser ? '' : '<th>Сменить статус</th>' ?></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
  <td><?= (int)$r['application_id'] ?></td>
  <td><?= e($r['fio']) ?></td>
  <td><?= e($r['service_type']) ?></td>
  <td><span class="pill <?= $r['status'] === 'завершено' ? 'ok' : ($r['status'] === 'отклонено' ? 'err' : '') ?>"><?= e($r['status']) ?></span></td>
  <td><?= e($r['operator_login']) ?></td>
  <td><?= e($r['created_at']) ?></td>
  <td><?= e($r['updated_at'] ?: '—') ?></td>
  <?php if (!$isUser): ?>
  <td>
    <form method="post" class="form-inline">
      <?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="id" value="<?= (int)$r['application_id'] ?>">
      <select class="input" name="status">
        <?php foreach (['подано','в работе','завершено','отклонено'] as $s): ?><option <?= $r['status']===$s?'selected':'' ?>><?= $s ?></option><?php endforeach; ?>
      </select>
      <button class="btn btn-sm">OK</button>
    </form>
  </td>
  <?php endif; ?>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php require __DIR__ . '/layout/footer.php'; ?>
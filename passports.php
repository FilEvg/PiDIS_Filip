<?php
require __DIR__ . '/config.php';
require_role(['администратор', 'оператор']);
$pdo = db();
$formError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $st = $pdo->prepare('SELECT * FROM passports WHERE passport_id = ?'); $st->execute([$id]);
        if ($old = $st->fetch()) {
            $pdo->prepare('DELETE FROM passports WHERE passport_id = ?')->execute([$id]);
            log_action($pdo, 'passports', $id, 'delete', $old);
            flash('ok', 'Паспорт удалён.');
        }
        header('Location: passports.php'); exit;
    }
    if (in_array($action, ['create', 'update'], true)) {
        $f = [
            'citizen_id'  => (int)($_POST['citizen_id'] ?? 0),
            'series'      => trim($_POST['series'] ?? ''),
            'number'      => trim($_POST['number'] ?? ''),
            'issue_date'  => $_POST['issue_date'] ?? '',
            'expiry_date' => $_POST['expiry_date'] ?? '' ?: null,
            'issuer'      => trim($_POST['issuer'] ?? ''),
            'status'      => $_POST['status'] ?? 'черновик',
            'reason'      => trim($_POST['reason'] ?? '') ?: null,
        ];
                if (!preg_match('/^\d{4}$/', $f['series']))      $formError = 'Серия должна содержать ровно 4 цифры.';
        elseif (!preg_match('/^\d{6}$/', $f['number']))  $formError = 'Номер должен содержать ровно 6 цифр.';
        elseif ($f['citizen_id'] <= 0 || $f['issue_date'] === '' || $f['issuer'] === '') $formError = 'Заполните обязательные поля.';
        else {
            // контроль дат и возраста: выдача не в будущем, не раньше рождения и не раньше 14-летия
            $stB = $pdo->prepare('SELECT birth_date FROM citizens WHERE citizen_id = ?');
            $stB->execute([$f['citizen_id']]);
            $birth = $stB->fetchColumn();
            if (date_is_future($f['issue_date'])) {
                $formError = 'Дата выдачи паспорта не может быть в будущем (указано ' . e($f['issue_date']) . ', сегодня ' . e(today_str()) . '). Укажите фактическую дату выдачи и сохраните снова.';
            } elseif ($birth !== false && $f['issue_date'] < $birth) {
                $formError = 'Дата выдачи паспорта не может быть раньше даты рождения гражданина: проверьте даты и сохраните снова.';
            } elseif ($birth !== false && $f['issue_date'] < min_passport_issue_date((string)$birth)) {
                $formError = 'Паспорт не может быть выдан гражданину младше 14 лет: дата выдачи должна быть не ранее даты 14-летия ('
                    . e(min_passport_issue_date((string)$birth)) . '). Исправьте дату выдачи или выберите другого гражданина и сохраните снова.';
            } elseif ($f['expiry_date'] !== null && date_is_past($f['expiry_date'])) {
                $formError = 'Дата окончания паспорта не может быть в прошлом (указано ' . e($f['expiry_date']) . ', сегодня ' . e(today_str()) . '). Укажите актуальную дату или оставьте поле пустым.';
            } elseif ($f['expiry_date'] !== null && $f['expiry_date'] < $f['issue_date']) {
                $formError = 'Дата окончания паспорта не может быть раньше даты его выдачи: проверьте даты и сохраните снова.';
            } else {
                try {
                    if ($action === 'create') {
                        $pdo->prepare('INSERT INTO passports (citizen_id,series,number,issue_date,expiry_date,issuer,status,reason) VALUES (?,?,?,?,?,?,?,?)')
                            ->execute([$f['citizen_id'],$f['series'],$f['number'],$f['issue_date'],$f['expiry_date'],$f['issuer'],$f['status'],$f['reason']]);
                        $id = (int)$pdo->lastInsertId();
                        log_action($pdo, 'passports', $id, 'create', null, $f);
                        flash('ok', 'Паспорт создан.');
                    } else {
                        $id = (int)$_POST['id'];
                        $st = $pdo->prepare('SELECT * FROM passports WHERE passport_id = ?'); $st->execute([$id]);
                        $old = $st->fetch();
                        $pdo->prepare('UPDATE passports SET citizen_id=?,series=?,number=?,issue_date=?,expiry_date=?,issuer=?,status=?,reason=? WHERE passport_id=?')
                            ->execute([$f['citizen_id'],$f['series'],$f['number'],$f['issue_date'],$f['expiry_date'],$f['issuer'],$f['status'],$f['reason'],$id]);
                        log_action($pdo, 'passports', $id, 'update', $old, $f);
                        flash('ok', 'Паспорт обновлён.');
                    }
                    header('Location: passports.php'); exit;
                } catch (PDOException $ex) {
                    $formError = $ex->getCode() === '23000'
                        ? 'Комбинация серия + номер должна быть уникальна.'
                        : 'Ошибка БД: ' . $ex->getMessage();
                }
            }
        }
    }
}
$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM passports WHERE passport_id = ?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}
$showForm = $edit !== null || isset($_GET['new']);
$citizens = $pdo->query('SELECT citizen_id, last_name, first_name, middle_name FROM citizens ORDER BY last_name')->fetchAll();
$q = trim($_GET['q'] ?? ''); $ps = $_GET['status'] ?? '';
$df = $_GET['issue_from'] ?? ''; $dt = $_GET['issue_to'] ?? '';
$where = []; $p = [];
if ($q !== '') { $where[] = "CONCAT(series,' ',number,' ',issuer,IFNULL(reason,'')) LIKE ?"; $p[] = "%$q%"; }
if ($ps !== '') { $where[] = 'status = ?'; $p[] = $ps; }
if ($df !== '') { $where[] = 'issue_date >= ?'; $p[] = $df; }
if ($dt !== '') { $where[] = 'issue_date <= ?'; $p[] = $dt; }
$sql = 'SELECT p.*, CONCAT(c.last_name," ",c.first_name," ",IFNULL(c.middle_name,"")) AS fio
        FROM passports p JOIN citizens c ON c.citizen_id = p.citizen_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY p.passport_id DESC LIMIT 200';
$st = $pdo->prepare($sql); $st->execute($p);
$rows = $st->fetchAll();
require __DIR__ . '/layout/header.php';
?>
<h1>Паспорта</h1>
<?php if ($formError): ?><div class="flash err"><?= e($formError) ?></div><?php endif; ?>
<form class="toolbar" method="get">
<input class="input" style="flex:1" type="text" name="q" value="<?= e($q) ?>" placeholder="Поиск: серия, номер, орган выдачи…">
<select class="input" name="status">
<option value="">Статус: все</option>
<?php foreach (['черновик','действует','заменён','аннулирован'] as $s): ?>
<option <?= $ps === $s ? 'selected' : '' ?>><?= $s ?></option>
<?php endforeach; ?>
</select>
<input class="input" type="date" name="issue_from" value="<?= e($df) ?>">
<input class="input" type="date" name="issue_to" value="<?= e($dt) ?>">
<button class="btn"><i class="fa-solid fa-filter"></i> Фильтр</button>
<a class="btn btn-outline" href="passports.php?new=1"><i class="fa-solid fa-plus"></i> Добавить</a>
</form>
<?php if ($showForm): $v = $edit ?: ['citizen_id'=>'','series'=>'','number'=>'','issue_date'=>'','expiry_date'=>'','issuer'=>'','status'=>'черновик','reason'=>'']; ?>
<div class="card">
<h2><?= $edit ? 'Изменить паспорт #' . (int)$edit['passport_id'] : 'Новый паспорт' ?></h2>
<form method="post" class="form-grid">
<?= csrf_field() ?>
<input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
<?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['passport_id'] ?>"><?php endif; ?>
<div><label>Гражданин *</label>
<select class="input" name="citizen_id" required><option value="">—</option>
<?php foreach ($citizens as $c): ?>
<option value="<?= (int)$c['citizen_id'] ?>" <?= (int)$v['citizen_id'] === (int)$c['citizen_id'] ? 'selected' : '' ?>>
<?= e($c['last_name'] . ' ' . $c['first_name'] . ' ' . $c['middle_name']) ?> (#<?= (int)$c['citizen_id'] ?>)</option>
<?php endforeach; ?>
</select></div>
<div><label>Серия (4 цифры) *</label><input class="input" name="series" required maxlength="4" placeholder="0000" value="<?= e($v['series']) ?>"></div>
<div><label>Номер (6 цифр) *</label><input class="input" name="number" required maxlength="6" placeholder="000000" value="<?= e($v['number']) ?>"></div>
<div><label>Дата выдачи *</label><input class="input" type="date" name="issue_date" required value="<?= e($v['issue_date']) ?>"></div>
<div><label>Дата окончания</label><input class="input" type="date" name="expiry_date" value="<?= e($v['expiry_date']) ?>"></div>
<div><label>Орган выдачи *</label><input class="input" name="issuer" required value="<?= e($v['issuer']) ?>"></div>
<div><label>Статус</label><select class="input" name="status">
<?php foreach (['черновик','действует','заменён','аннулирован'] as $s): ?>
<option <?= $v['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
<?php endforeach; ?></select></div>
<div><label>Основание</label><input class="input" name="reason" value="<?= e($v['reason']) ?>"></div>
<div class="form-actions"><button class="btn"><i class="fa-solid fa-floppy-disk"></i> Сохранить</button>
<a class="btn btn-outline" href="passports.php">Отмена</a></div>
</form>
</div>
<?php endif; ?>
<div class="tbl-wrap">
<table class="tbl">
<thead><tr><th>№</th><th>Гражданин</th><th>Серия/номер</th><th>Выдан</th><th>Орган</th><th>Статус</th><th>Действия</th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
<td><?= (int)$r['passport_id'] ?></td>
<td><?= e($r['fio']) ?></td>
<td><b><?= e($r['series'] . ' ' . $r['number']) ?></b></td>
<td><?= e($r['issue_date']) ?></td>
<td><?= e($r['issuer']) ?><br><span class="muted"><?= e($r['reason']) ?></span></td>
<td><span class="pill <?= $r['status'] === 'действует' ? 'ok' : '' ?>"><?= e($r['status']) ?></span></td>
<td class="rowbtns">
<a class="btn btn-sm btn-outline" href="document.php?type=passport&id=<?= (int)$r['passport_id'] ?>" title="Печатная форма"><i class="fa-solid fa-file-pdf"></i></a>
<a class="btn btn-sm btn-outline" href="passports.php?edit=<?= (int)$r['passport_id'] ?>"><i class="fa-solid fa-pen"></i></a>
<form class="confirm" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['passport_id'] ?>">
<button class="btn btn-sm btn-danger"><i class="fa-solid fa-trash"></i></button></form>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php require __DIR__ . '/layout/footer.php'; ?>
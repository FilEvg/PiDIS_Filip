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
        $st = $pdo->prepare('SELECT * FROM citizens WHERE citizen_id = ?');
        $st->execute([$id]);
        $old = $st->fetch();
        if ($old) {
            $pdo->prepare('DELETE FROM citizens WHERE citizen_id = ?')->execute([$id]);
            log_action($pdo, 'citizens', $id, 'delete', $old);
            flash('ok', 'Гражданин удалён.');
        }
        header('Location: citizens.php'); exit;
    }
    if (in_array($action, ['create', 'update'], true)) {
        $f = [
            'last_name'      => trim($_POST['last_name'] ?? ''),
            'first_name'     => trim($_POST['first_name'] ?? ''),
            'middle_name'    => trim($_POST['middle_name'] ?? '') ?: null,
            'birth_date'     => $_POST['birth_date'] ?? '',
            'sex'            => ($_POST['sex'] ?? 'М') === 'Ж' ? 'Ж' : 'М',
            'phone'          => trim($_POST['phone'] ?? '') ?: null,
            'email'          => trim($_POST['email'] ?? '') ?: null,
            'reg_address'    => trim($_POST['reg_address'] ?? ''),
            'reg_status'     => ($_POST['reg_status'] ?? 'активна') === 'прекращена' ? 'прекращена' : 'активна',
            'reg_start_date' => $_POST['reg_start_date'] ?? '',
            'reg_end_date'   => $_POST['reg_end_date'] ?? '' ?: null,
        ];
        if ($f['last_name'] === '' || $f['first_name'] === '' || $f['reg_address'] === ''
            || $f['birth_date'] === '' || $f['reg_start_date'] === '') {
            $formError = 'Заполните обязательные поля: фамилия, имя, дата рождения, адрес, дата начала регистрации.';
        } elseif (date_is_future($f['birth_date'])) {
            $formError = 'Дата рождения не может быть в будущем (указано ' . e($f['birth_date']) . ', сегодня ' . e(today_str()) . '). Укажите фактическую дату рождения и сохраните снова.';
        } elseif (date_is_future($f['reg_start_date'])) {
            $formError = 'Дата начала регистрации не может быть в будущем (указано ' . e($f['reg_start_date']) . '). Регистрация оформляется не ранее текущей даты — исправьте дату и сохраните снова.';
        } elseif ($f['reg_start_date'] < $f['birth_date']) {
            $formError = 'Дата начала регистрации не может быть раньше даты рождения гражданина: проверьте обе даты и сохраните снова.';
        } elseif ($f['reg_end_date'] !== null && date_is_future($f['reg_end_date'])) {
            $formError = 'Дата окончания регистрации не может быть в будущем: укажите фактическую дату или оставьте поле пустым.';
        } elseif ($f['reg_end_date'] !== null && $f['reg_end_date'] < $f['reg_start_date']) {
            $formError = 'Дата окончания регистрации не может быть раньше даты её начала: проверьте период и сохраните снова.';
        } else {
            if ($action === 'create') {
                $st = $pdo->prepare('INSERT INTO citizens (last_name,first_name,middle_name,birth_date,sex,phone,email,reg_address,reg_status,reg_start_date,reg_end_date)
                                     VALUES (?,?,?,?,?,?,?,?,?,?,?)');
                $st->execute([$f['last_name'],$f['first_name'],$f['middle_name'],$f['birth_date'],$f['sex'],$f['phone'],$f['email'],$f['reg_address'],$f['reg_status'],$f['reg_start_date'],$f['reg_end_date']]);
                $id = (int)$pdo->lastInsertId();
                log_action($pdo, 'citizens', $id, 'create', null, $f);
                flash('ok', 'Гражданин добавлен.');
            } else {
                $id = (int)$_POST['id'];
                $st = $pdo->prepare('SELECT * FROM citizens WHERE citizen_id = ?');
                $st->execute([$id]);
                $old = $st->fetch();
                $pdo->prepare('UPDATE citizens SET last_name=?,first_name=?,middle_name=?,birth_date=?,sex=?,phone=?,email=?,reg_address=?,reg_status=?,reg_start_date=?,reg_end_date=? WHERE citizen_id=?')
                    ->execute([$f['last_name'],$f['first_name'],$f['middle_name'],$f['birth_date'],$f['sex'],$f['phone'],$f['email'],$f['reg_address'],$f['reg_status'],$f['reg_start_date'],$f['reg_end_date'],$id]);
                log_action($pdo, 'citizens', $id, 'update', $old, $f);
                flash('ok', 'Данные гражданина обновлены.');
            }
            header('Location: citizens.php'); exit;
        }
    }
}
$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM citizens WHERE citizen_id = ?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}
$showForm = $edit !== null || isset($_GET['new']);
// фильтры: частичное совпадение + равенство + диапазоны дат
$q  = trim($_GET['q'] ?? '');
$rs = $_GET['reg_status'] ?? '';
$bf = $_GET['birth_from'] ?? ''; $bt = $_GET['birth_to'] ?? '';
$where = []; $p = [];
if ($q !== '') {
    $where[] = "CONCAT(last_name,' ',first_name,' ',IFNULL(middle_name,''),' ',IFNULL(phone,''),' ',IFNULL(email,''),' ',reg_address) LIKE ?";
    $p[] = "%$q%";
}
if ($rs !== '') { $where[] = 'reg_status = ?'; $p[] = $rs; }
if ($bf !== '') { $where[] = 'birth_date >= ?'; $p[] = $bf; }
if ($bt !== '') { $where[] = 'birth_date <= ?'; $p[] = $bt; }
$sql = 'SELECT * FROM citizens' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY citizen_id DESC LIMIT 200';
$st = $pdo->prepare($sql); $st->execute($p);
$rows = $st->fetchAll();
require __DIR__ . '/layout/header.php';
?>
<h1>Граждане</h1>
<?php if ($formError): ?><div class="flash err"><?= e($formError) ?></div><?php endif; ?>
<form class="toolbar" method="get">
<input class="input" style="flex:1" type="text" name="q" value="<?= e($q) ?>" placeholder="Поиск по всем полям…">
<select class="input" name="reg_status">
<option value="">Статус: все</option>
<option value="активна"     <?= $rs === 'активна' ? 'selected' : '' ?>>активна</option>
<option value="прекращена"  <?= $rs === 'прекращена' ? 'selected' : '' ?>>прекращена</option>
</select>
<input class="input" type="date" name="birth_from" value="<?= e($bf) ?>" title="Дата рождения с">
<input class="input" type="date" name="birth_to" value="<?= e($bt) ?>" title="Дата рождения по">
<button class="btn"><i class="fa-solid fa-filter"></i> Фильтр</button>
<a class="btn btn-outline" href="citizens.php?new=1"><i class="fa-solid fa-plus"></i> Добавить</a>
</form>
<?php if ($showForm): $v = $edit ?: array_fill_keys(['last_name','first_name','middle_name','birth_date','sex','phone','email','reg_address','reg_status','reg_start_date','reg_end_date'], ''); ?>
<div class="card">
<h2><?= $edit ? 'Изменить гражданина #' . (int)$edit['citizen_id'] : 'Новый гражданин' ?></h2>
<form method="post" class="form-grid">
<?= csrf_field() ?>
<input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
<?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['citizen_id'] ?>"><?php endif; ?>
<div><label>Фамилия *</label><input class="input" name="last_name" required value="<?= e($v['last_name']) ?>"></div>
<div><label>Имя *</label><input class="input" name="first_name" required value="<?= e($v['first_name']) ?>"></div>
<div><label>Отчество</label><input class="input" name="middle_name" value="<?= e($v['middle_name']) ?>"></div>
<div><label>Дата рождения *</label><input class="input" type="date" name="birth_date" required value="<?= e($v['birth_date']) ?>"></div>
<div><label>Пол</label><select class="input" name="sex"><option <?= $v['sex']==='М'?'selected':'' ?>>М</option><option <?= $v['sex']==='Ж'?'selected':'' ?>>Ж</option></select></div>
<div><label>Телефон</label><input class="input" name="phone" value="<?= e($v['phone']) ?>"></div>
<div><label>E-mail</label><input class="input" name="email" value="<?= e($v['email']) ?>"></div>
<div><label>Адрес регистрации *</label><input class="input" name="reg_address" required value="<?= e($v['reg_address']) ?>"></div>
<div><label>Статус регистрации</label><select class="input" name="reg_status"><option <?= $v['reg_status']==='активна'?'selected':'' ?>>активна</option><option <?= $v['reg_status']==='прекращена'?'selected':'' ?>>прекращена</option></select></div>
<div><label>Дата начала регистрации *</label><input class="input" type="date" name="reg_start_date" required value="<?= e($v['reg_start_date']) ?>"></div>
<div><label>Дата окончания</label><input class="input" type="date" name="reg_end_date" value="<?= e($v['reg_end_date']) ?>"></div>
<div class="form-actions"><button class="btn"><i class="fa-solid fa-floppy-disk"></i> Сохранить</button>
<a class="btn btn-outline" href="citizens.php">Отмена</a></div>
</form>
</div>
<?php endif; ?>
<div class="tbl-wrap">
<table class="tbl">
<thead><tr><th>№</th><th>ФИО</th><th>Дата рождения</th><th>Адрес</th><th>Статус</th><th>Период</th><th>Действия</th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
<td><?= (int)$r['citizen_id'] ?></td>
<td><b><?= e($r['last_name'] . ' ' . $r['first_name'] . ' ' . $r['middle_name']) ?></b><br>
<span class="muted"><?= e($r['phone']) ?> <?= $r['email'] ? '· ' . e($r['email']) : '' ?></span></td>
<td><?= e($r['birth_date']) ?> (<?= e($r['sex']) ?>)</td>
<td><?= e($r['reg_address']) ?></td>
<td><span class="pill <?= $r['reg_status'] === 'активна' ? 'ok' : 'err' ?>"><?= e($r['reg_status']) ?></span></td>
<td><?= e($r['reg_start_date']) ?> — <?= e($r['reg_end_date'] ?: '…') ?></td>
<td class="rowbtns">
<a class="btn btn-sm btn-outline" href="document.php?type=form9&id=<?= (int)$r['citizen_id'] ?>" title="Справка №9"><i class="fa-solid fa-file-pdf"></i></a>
<a class="btn btn-sm btn-outline" href="citizens.php?edit=<?= (int)$r['citizen_id'] ?>"><i class="fa-solid fa-pen"></i></a>
<form class="confirm" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['citizen_id'] ?>">
<button class="btn btn-sm btn-danger"><i class="fa-solid fa-trash"></i></button></form>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php require __DIR__ . '/layout/footer.php'; ?>
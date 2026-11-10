<?php
require __DIR__ . '/config.php';
require_role(['администратор']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore') {
    csrf_check();
    $st = $pdo->prepare('SELECT * FROM action_log WHERE log_id = ?');
    $st->execute([(int)$_POST['log_id']]);
    $r = $st->fetch();
    if (!$r || $r['before_json'] === null) {
        flash('err', 'У выбранной записи нет данных до изменения (before_json) — восстановление невозможно.');
    } elseif ($r['entity_name'] === 'action_log') {
        flash('err', 'Операции удаления и прямого обновления записей action_log запрещены. Восстановление записей журнала не выполняется.');
    } else {
        $before = log_state_read($r['before_json']); // расшифровка состояния (AES-256-GCM)
        $table  = $r['entity_name'];
        $pk     = ENTITY_PK[$table];
        // Поля восстановления: реальные колонки таблицы, без PK, без секретов и без заглушек '***'
        $fields = array_values(array_diff(
            array_values(array_intersect(array_keys($before ?? []), ENTITY_COLS[$table])),
            array_merge([$pk], RESTORE_SKIP_COLUMNS)
        ));
        $fields = array_values(array_filter($fields, fn($c) => (string)$before[$c] !== '***'));

        // значения для записи: шифруемые колонки шифруются обратно
        $valueFor = function (string $col) use ($table, $before) {
            $v = $before[$col];
            return in_array($col, ENC_COLS[$table] ?? [], true) ? crypto_encrypt((string)$v) : $v;
        };

        $ok = false; $note = ''; $restoreError = null;
        if ($fields) {
            $set = implode(', ', array_map(fn($c) => "$c = :$c", $fields));
            $params = [":$pk" => $r['entity_id']];
            foreach ($fields as $c) $params[":$c"] = $valueFor($c);
            try {
                $upd = $pdo->prepare("UPDATE `$table` SET $set WHERE `$pk` = :$pk");
                $upd->execute($params);
                $existsNow = $pdo->prepare("SELECT 1 FROM `$table` WHERE `$pk` = ?");
                $existsNow->execute([$r['entity_id']]);
                if ($existsNow->fetchColumn()) $ok = true;
            } catch (PDOException $ex) { $restoreError = $ex; }
        }
        if (!$ok && $restoreError === null) {
            // строка физически удалена — восстанавливаем вставкой; секреты создаём заново
            $cols   = array_merge([$pk], $fields);
            $params = [":$pk" => $r['entity_id']];
            foreach ($fields as $c) $params[":$c"] = $valueFor($c);
            if ($table === 'users') {
                if (!in_array('password_hash', $cols, true)) {
                    $cols[] = 'password_hash';
                    $params[':password_hash'] = '!restored:' . bin2hex(random_bytes(8)); // невалидный хеш
                    $note = ' Восстановленному пользователю необходимо задать новый пароль (хеш пароля не хранится в журнале).';
                }
                if (!in_array('mfa_secret', $cols, true)) {
                    $cols[] = 'mfa_secret';
                    $params[':mfa_secret'] = crypto_encrypt(base64_encode(random_bytes(16)));
                }
            }
            $ins = $pdo->prepare("INSERT INTO `$table` (" . implode(',', $cols) . ') VALUES ('
                . implode(',', array_map(fn($c) => ":$c", $cols)) . ')');
            try { $ins->execute($params); $ok = true; }
            catch (PDOException $ex) { $restoreError = $ex; }
        }
        if ($restoreError !== null) {
            flash('err', 'Ошибка восстановления: ' . e(user_friendly_error_message($restoreError)));
        } elseif ($ok) {
            log_action($pdo, $table, (int)$r['entity_id'], 'restore', null, $before);
            flash('ok', "Состояние $table #{$r['entity_id']} восстановлено из before_json (в журнал добавлена запись с типом restore)." . $note);
        }
    }
    header('Location: admin_journal.php?' . http_build_query($_GET)); exit;
}

// Фильтры: равенство по типу и диапазон дат — в SQL; частичное совпадение по всем
// полям (включая РАСШИФРОВАННЫЕ before/after) — в PHP после расшифровки.
$q = trim($_GET['q'] ?? ''); $tp = $_GET['type'] ?? '';
$df = $_GET['from'] ?? ''; $dt = $_GET['to'] ?? '';
$where = []; $p = [];
if ($tp !== '') { $where[] = 'l.action_type = ?'; $p[] = $tp; }
if ($df !== '') { $where[] = 'DATE(l.action_time) >= ?'; $p[] = $df; }
if ($dt !== '') { $where[] = 'DATE(l.action_time) <= ?'; $p[] = $dt; }
$sql = 'SELECT l.*, u.login FROM action_log l LEFT JOIN users u ON u.user_id = l.user_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY l.log_id DESC';
$st = $pdo->prepare($sql); $st->execute($p);
$rows = $st->fetchAll();
foreach ($rows as &$row) {
    $row['_before'] = log_state_read($row['before_json']);
    $row['_after']  = log_state_read($row['after_json']);
}
unset($row);
if ($q !== '') {
    $rows = array_values(array_filter($rows, function ($r) use ($q) {
        $hay = implode(' ', [
            $r['action_time'], (string)$r['login'], $r['entity_name'], (string)$r['entity_id'], $r['action_type'],
            $r['_before'] !== null ? json_encode($r['_before'], JSON_UNESCAPED_UNICODE) : '',
            $r['_after']  !== null ? json_encode($r['_after'],  JSON_UNESCAPED_UNICODE) : '',
        ]);
        return mb_stripos($hay, $q, 0, 'UTF-8') !== false;
    }));
}
$rows = array_slice($rows, 0, 300);

require __DIR__ . '/layout/admin_header.php';
?>
<h1>Журнал действий (action_log)</h1>
<p class="muted">Журнал хранится полностью: удаление и прямое обновление его записей запрещены ТЗ и заблокированы
на уровне приложения. Состояния before/after хранятся в зашифрованном виде (AES-256-GCM) и расшифровываются при показе.
Доступно восстановление состояния сущности из before_json; секреты (хеш пароля, mfa_secret) не восстанавливаются.</p>
<form class="toolbar" method="get">
<input class="input" style="flex:1" type="text" name="q" value="<?= e($q) ?>" placeholder="Поиск по всем полям (частичное совпадение)…">
<select class="input" name="type"><option value="">Все действия (равенство)</option>
<?php foreach (['create','read','update','delete','login','logout','export','import','restore'] as $t): ?>
<option <?= $tp === $t ? 'selected' : '' ?>><?= $t ?></option>
<?php endforeach; ?>
</select>
<input class="input" type="date" name="from" value="<?= e($df) ?>">
<input class="input" type="date" name="to" value="<?= e($dt) ?>">
<button class="btn"><i class="fa-solid fa-filter"></i> Фильтр</button>
</form>
<div class="tbl-wrap">
<table class="tbl">
<thead><tr><th>№</th><th>Дата и время</th><th>Пользователь</th><th>Сущность</th><th>ID</th><th>Действие</th><th>before_json</th><th>after_json</th><th>Восстановление</th></tr></thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
<td><?= (int)$r['log_id'] ?></td>
<td><?= e($r['action_time']) ?></td>
<td><?= e($r['login']) ?></td>
<td><code><?= e($r['entity_name']) ?></code></td>
<td><?= (int)$r['entity_id'] ?></td>
<td><span class="pill t-<?= e($r['action_type']) ?>"><?= e($r['action_type']) ?></span></td>
<td class="jsoncell"><?= $r['_before'] !== null ? '<details><summary>JSON</summary><pre>' . e(json_encode($r['_before'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . '</pre></details>' : '—' ?></td>
<td class="jsoncell"><?= $r['_after'] !== null ? '<details><summary>JSON</summary><pre>' . e(json_encode($r['_after'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . '</pre></details>' : '—' ?></td>
<td>
<?php if (in_array($r['action_type'], ['update', 'delete'], true) && $r['_before'] !== null && $r['entity_name'] !== 'action_log'): ?>
<form method="post" class="confirm">
<?= csrf_field() ?><input type="hidden" name="action" value="restore"><input type="hidden" name="log_id" value="<?= (int)$r['log_id'] ?>">
<button class="btn btn-sm"><i class="fa-solid fa-clock-rotate-left"></i> Восстановить</button>
</form>
<?php else: ?><span class="muted">—</span><?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php require __DIR__ . '/layout/footer.php'; ?>
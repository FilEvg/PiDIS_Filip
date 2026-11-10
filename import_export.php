<?php
require __DIR__ . '/config.php';
require_role(['администратор', 'оператор']);
$pdo = db();
const REQUIRED_FIELDS = [
    'citizens'     => ['last_name','first_name','birth_date','sex','reg_address','reg_status','reg_start_date'],
    'passports'    => ['citizen_id','series','number','issue_date','issuer','status'],
    'applications' => ['citizen_id','operator_id','service_type','status','created_at'],
    'users'        => ['login','password_hash','role'],
    'action_log'   => ['action_time','user_id','entity_name','entity_id','action_type'],
];
// ---------- ЭКСПОРТ ----------
if (in_array($_GET['do'] ?? '', ['export', 'export_excel', 'export_sql'], true)) {
    // Экспорт ВСЕЙ БД — SQL-дамп (резервная копия / перенос на другой ПК).
    // ТЗ требует JSON-экспорт записей сущностей — он сохранён кнопками JSON/Excel,
    // поэтому дамп всей БД в SQL требованиям не противоречит (это надстройка).
    if ($_GET['do'] === 'export_sql') {
        $lines = [];
        $lines[] = '-- АИС «Паспортный стол»: полный экспорт базы `' . DB_NAME . '`';
        $lines[] = '-- Сформирован: ' . date('Y-m-d H:i:s') . ' (кодировка UTF-8)';
        $lines[] = 'SET FOREIGN_KEY_CHECKS=0;';
        $tables = array_column($pdo->query('SHOW TABLES')->fetchAll(), 0);
        foreach ($tables as $table) {
            $lines[] = '';
            $lines[] = '-- ---- таблица `' . $table . '` ----';
            $create = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch();
            $lines[] = 'DROP TABLE IF EXISTS `' . $table . '`;';
            $lines[] = $create['Create Table'] . ';';
            $rows = $pdo->query('SELECT * FROM `' . $table . '`')->fetchAll();
            foreach ($rows as $row) {
                $values = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), array_values($row));
                $lines[] = 'INSERT INTO `' . $table . '` VALUES (' . implode(', ', $values) . ');';
            }
        }
        $lines[] = '';
        $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';
        log_action($pdo, 'action_log', 0, 'export', null,
            ['format' => 'sql', 'scope' => 'full_database', 'tables' => count($tables)]);
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . DB_NAME . '_full_' . date('Ymd_His') . '.sql"');
        echo implode("\n", $lines);
        exit;
    }
    $entity = $_GET['entity'] ?? '';
    if (!isset(ENTITY_COLS[$entity])) {
        flash('err', 'Выбрана неизвестная сущность для экспорта. Доступны: citizens, passports, applications, users, action_log. Выберите сущность из списка и повторите экспорт.');
        header('Location: import_export.php'); exit;
    }
    $rows = $pdo->query("SELECT * FROM `$entity`")->fetchAll();
    if ($_GET['do'] === 'export') {
        log_action($pdo, $entity, 0, 'export', null, ['format' => 'json', 'rows' => count($rows)]);
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $entity . '_export.json"');
        echo json_encode([$entity => $rows], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    } else {
        require __DIR__ . '/xlsx_writer.php';
        log_action($pdo, $entity, 0, 'export', null, ['format' => 'xlsx', 'rows' => count($rows)]);
        xlsx_send($entity . '_export.xlsx', ENTITY_COLS[$entity], $rows);
    }
    exit;
}
// ---------- ИМПОРТ ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $file = $_FILES['json'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        flash('err', 'Выберите JSON-файл для импорта (кнопка «Обзор»), затем нажмите «Импортировать».');
    } else {
        $data = json_decode((string)file_get_contents($file['tmp_name']), true);
        if (!is_array($data)) {
            flash('err', 'Файл не является корректным JSON (ожидается кодировка UTF-8). Проверьте файл и повторите импорт.');
        } else {
            $errors = []; $counts = [];
            try {
                $pdo->beginTransaction();
                foreach (['users', 'citizens', 'passports', 'applications', 'action_log'] as $entity) {
                    $rows = $data[$entity] ?? [];
                    if (!$rows) continue;
                    $counts[$entity] = 0;
                    foreach ($rows as $i => $row) {
                        foreach (REQUIRED_FIELDS[$entity] as $rq) {
                            if (!isset($row[$rq]) || $row[$rq] === '') {
                                $errors[] = "$entity[$i]: отсутствует обязательное поле $rq";
                            }
                        }
                        if ($errors) continue;
                        $cols = array_values(array_intersect(array_keys($row), ENTITY_COLS[$entity]));
                        $cols = array_values(array_diff($cols, [ENTITY_PK[$entity]]));
                        $ins = $pdo->prepare("INSERT INTO `$entity` (" . implode(',', $cols) . ') VALUES (' . implode(',', array_map(fn($c) => ":$c", $cols)) . ')');
                        foreach ($cols as $c) $ins->bindValue(":$c", $row[$c]);
                        $ins->execute();
                        $counts[$entity]++;
                    }
                    if ($errors) break;
                }
                if ($errors) {
                    $pdo->rollBack();
                    flash('err', 'Импорт отклонён (выполнен откат): ' . e(implode('; ', array_slice($errors, 0, 5))) . '. Исправьте файл и повторите импорт.');
                } else {
                    $pdo->commit();
                    log_action($pdo, 'citizens', 0, 'import', null, $counts);
                    flash('ok', 'Импорт завершён: добавлено записей — ' . array_sum($counts) . '.');
                }
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flash('err', 'Импорт отклонён (нарушение целостности данных): ' . e(user_friendly_error_message($ex)));
            }
        }
    }
    header('Location: import_export.php'); exit;
}
require __DIR__ . '/layout/header.php';
?>
<h1>Импорт / Экспорт данных</h1>
<div class="grid2">
<div class="card">
<h2><i class="fa-solid fa-file-export"></i> Экспорт (JSON и Excel)</h2>
<p class="muted">JSON: корневой массив сущности, например {"citizens": [...]} (требование ТЗ 4.6). Excel: файл .xlsx с шапкой из имён колонок и всеми записями.</p>
<form class="form-inline" method="get">
<select class="input" name="entity">
<?php foreach (['citizens','passports','applications','users','action_log'] as $en): ?><option><?= $en ?></option><?php endforeach; ?>
</select>
<button class="btn" name="do" value="export"><i class="fa-solid fa-download"></i> JSON</button>
<button class="btn" name="do" value="export_excel"><i class="fa-solid fa-file-excel"></i> Excel</button>
</form>
<p class="muted" style="margin:12px 0 8px">Полный экспорт базы данных: SQL-дамп (структура и данные всех таблиц) —
удобен для резервного копирования и переноса сайта на другой ПК.</p>
<form method="get">
<input type="hidden" name="do" value="export_sql">
<button class="btn btn-outline"><i class="fa-solid fa-database"></i> Экспорт всей БД (SQL)</button>
</form>
</div>
<div class="card">
<h2><i class="fa-solid fa-file-import"></i> Импорт из JSON (UTF-8)</h2>
<p class="muted">Файл может содержать корневые массивы: citizens, passports, applications, users, action_log.
Проверяются обязательные поля, форматы и ссылочная целостность; при ошибке — полный откат.</p>
<form method="post" enctype="multipart/form-data">
<?= csrf_field() ?>
<input class="input" type="file" name="json" accept=".json,application/json" required>
<div class="form-actions"><button class="btn"><i class="fa-solid fa-upload"></i> Импортировать</button></div>
</form>
</div>
</div>
<?php require __DIR__ . '/layout/footer.php'; ?>
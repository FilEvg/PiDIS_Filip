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
const ALL_ENTITIES = ['citizens', 'passports', 'applications', 'users', 'action_log'];

// ================= Папка резервных копий =================
define('BACKUP_DIR', __DIR__ . '/BackUp');

function backup_ensure_dir(): void
{
    if (is_dir(BACKUP_DIR)) return;
    if (!@mkdir(BACKUP_DIR, 0775, true)) {
        throw new RuntimeException('Не удалось создать папку BackUp в каталоге проекта: проверьте права на запись.');
    }
    @file_put_contents(BACKUP_DIR . '/.htaccess', "Require all denied\nDeny from all\n");
}
function backup_save(string $fileName, string $content): string
{
    backup_ensure_dir();
    $safe = preg_replace('/[^A-Za-z0-9_.-]/', '_', $fileName) ?: ('backup_' . date('Ymd_His'));
    if (file_put_contents(BACKUP_DIR . '/' . $safe, $content) === false) {
        throw new RuntimeException('Не удалось записать файл в папку BackUp: проверьте права на запись.');
    }
    return 'BackUp/' . $safe;
}
function backup_human_size(int $bytes): string
{
    $units = ['Б', 'КБ', 'МБ', 'ГБ'];
    $i = 0; $v = (float)$bytes;
    while ($v >= 1024 && $i < count($units) - 1) { $v /= 1024; $i++; }
    return ($i === 0 ? (int)$v : round($v, 1)) . ' ' . $units[$i];
}

// ================= Разбор SQL-дампа (понимает DELIMITER) =================
function split_sql_dump(string $sql): array
{
    $out = []; $buf = ''; $delim = ';';
    $quote = null; $escaped = false; $blockComment = false;
    foreach (preg_split('/\r\n|\r|\n/', $sql) as $line) {
        $trim = trim($line);
        if ($quote === null && !$blockComment && preg_match('/^DELIMITER\s+(\S+)$/i', $trim, $m)) {
            $delim = $m[1];
            continue;
        }
        $buf .= $line . "\n";
        $len = strlen($line);
        $lineComment = false;
        $ends = false;
        for ($i = 0; $i < $len; $i++) {
            $ch = $line[$i];
            if ($lineComment) continue;
            if ($blockComment) {
                if ($ch === '*' && ($line[$i + 1] ?? '') === '/') { $blockComment = false; $i++; }
                continue;
            }
            if ($quote) {
                if ($escaped) { $escaped = false; continue; }
                if ($ch === '\\') { $escaped = true; continue; }
                if ($ch === $quote) $quote = null;
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === '`') { $quote = $ch; continue; }
            if ($ch === '-' && ($line[$i + 1] ?? '') === '-') { $lineComment = true; continue; }
            if ($ch === '#') { $lineComment = true; continue; }
            if ($ch === '/' && ($line[$i + 1] ?? '') === '*') { $blockComment = true; $i++; continue; }
            if ($ch === $delim[0] && substr($line, $i, strlen($delim)) === $delim
                && trim(substr($line, $i + strlen($delim))) === '') {
                $ends = true;
                break;
            }
        }
        if ($ends && $quote === null && !$blockComment) {
            $stmt = trim($buf);
            $stmt = trim(substr($stmt, 0, -strlen($delim)));
            if ($stmt !== '') $out[] = $stmt;
            $buf = '';
        }
    }
    $tail = trim($buf);
    if ($tail !== '') $out[] = $tail;
    return $out;
}

// ================= Скачивание копии из BackUp =================
if (($_GET['do'] ?? '') === 'backup_get') {
    $file = basename((string)($_GET['file'] ?? ''));
    $path = BACKUP_DIR . '/' . $file;
    if ($file === '' || $file === '.htaccess' || !is_file($path)) {
        flash('err', 'Файл резервной копии не найден в папке BackUp: обновите список копий.');
        header('Location: import_export.php'); exit;
    }
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mime = ['sql' => 'application/sql', 'json' => 'application/json',
             'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
             'csv' => 'text/csv'][$ext] ?? 'application/octet-stream';
    log_action($pdo, 'action_log', 0, 'export', null, ['format' => $ext, 'scope' => 'backup_copy', 'backup' => $file]);
    header('Content-Type: ' . $mime . '; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . (string)filesize($path));
    readfile($path);
    exit;
}

// ================= ЭКСПОРТ =================
if (in_array($_GET['do'] ?? '', ['export', 'export_excel', 'export_sql', 'export_all_json'], true)) {
    $fileName = ''; $content = ''; $format = ''; $logEntity = 'action_log'; $scope = '';

    if ($_GET['do'] === 'export_sql' || $_GET['do'] === 'export_all_json') {
        $data = [];
        foreach (ALL_ENTITIES as $entity) {
            $data[$entity] = $pdo->query("SELECT * FROM `$entity`")->fetchAll();
        }
        if ($_GET['do'] === 'export_all_json') {
            $content  = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $format   = 'json';
            $scope    = 'full_database';
            $fileName = DB_NAME . '_full_' . date('Ymd_His') . '.json';
        } else {
            $lines = [];
            $lines[] = '-- АИС «Паспортный стол»: полный экспорт базы `' . DB_NAME . '`';
            $lines[] = '-- Сформирован: ' . date('Y-m-d H:i:s') . ' (UTF-8)';
            $lines[] = '-- Состав: таблицы + данные + функции, процедуры, триггеры; DEFINER удалён.';
            $lines[] = 'SET FOREIGN_KEY_CHECKS=0;';
            $stTables = $pdo->query('SHOW TABLES');
            $stTables->setFetchMode(PDO::FETCH_NUM);
            $tables = array_column($stTables->fetchAll(), 0);
            foreach ($tables as $table) {
                $lines[] = '';
                $lines[] = '-- ---- таблица `' . $table . '` ----';
                $create = $pdo->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_ASSOC);
                $lines[] = 'DROP TABLE IF EXISTS `' . $table . '`;';
                $lines[] = ($create['Create Table'] ?? '') . ';';
                foreach ($data[$table] ?? $pdo->query("SELECT * FROM `$table`")->fetchAll() as $row) {
                    $values = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), array_values($row));
                    $lines[] = 'INSERT INTO `' . $table . '` VALUES (' . implode(', ', $values) . ');';
                }
            }
            $lines[] = '';
            $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';
            $stripDefiner = function (string $sql): string {
                $r = preg_replace('/\bDEFINER\s*=\s*(?:`[^`]*`|\'[^\']*\'|"[^"]*"|[A-Za-z0-9_$]+)\s*@\s*(?:`[^`]*`|\'[^\']*\'|"[^"]*"|[A-Za-z0-9_$:.%-]+)\s*/u', '', $sql, 1);
                return $r === null ? $sql : $r;
            };
            $body = fn(string $c): string => rtrim($c, "; \r\n") . '$$';
            foreach ([
                ['FUNCTION',  'SHOW FUNCTION STATUS WHERE Db = ?',  'Create Function',  'функции'],
                ['PROCEDURE', 'SHOW PROCEDURE STATUS WHERE Db = ?', 'Create Procedure', 'процедуры'],
            ] as $spec) {
                [$kind, $statusSql, $createColumn, $label] = $spec;
                $st = $pdo->prepare($statusSql);
                $st->execute([DB_NAME]);
                $names = array_column($st->fetchAll(), 'Name');
                if (!$names) continue;
                $lines[] = '';
                $lines[] = '-- ---- ' . $label . ' ----';
                foreach ($names as $name) {
                    $row = $pdo->query('SHOW CREATE ' . $kind . ' `' . str_replace('`', '``', $name) . '`')->fetch(PDO::FETCH_ASSOC);
                    $create = $row[$createColumn] ?? null;
                    if ($create === null || $create === '') { $lines[] = '-- `' . $name . '`: нет прав на чтение определения — пропущено'; continue; }
                    $lines[] = 'DROP ' . $kind . ' IF EXISTS `' . $name . '`;';
                    $lines[] = 'DELIMITER $$';
                    $lines[] = $body($stripDefiner($create));
                    $lines[] = 'DELIMITER ;';
                }
            }
            $stT = $pdo->prepare('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ? ORDER BY TRIGGER_NAME');
            $stT->execute([DB_NAME]);
            $triggers = array_column($stT->fetchAll(), 'TRIGGER_NAME');
            if ($triggers) {
                $lines[] = '';
                $lines[] = '-- ---- триггеры ----';
                foreach ($triggers as $name) {
                    $row = $pdo->query('SHOW CREATE TRIGGER `' . str_replace('`', '``', $name) . '`')->fetch(PDO::FETCH_ASSOC);
                    $create = $row['SQL Original Statement'] ?? null;
                    if ($create === null || $create === '') { $lines[] = '-- `' . $name . '`: нет прав на чтение определения — пропущено'; continue; }
                    $lines[] = 'DROP TRIGGER IF EXISTS `' . $name . '`;';
                    $lines[] = 'DELIMITER $$';
                    $lines[] = $body($stripDefiner($create));
                    $lines[] = 'DELIMITER ;';
                }
            }
            $content  = implode("\n", $lines);
            $format   = 'sql';
            $scope    = 'full_database';
            $fileName = DB_NAME . '_full_' . date('Ymd_His') . '.sql';
        }
    } else {
        $entity = $_GET['entity'] ?? '';
        if (!isset(ENTITY_COLS[$entity])) {
            flash('err', 'Выбрана неизвестная сущность для экспорта. Доступны: ' . implode(', ', ALL_ENTITIES) . '.');
            header('Location: import_export.php'); exit;
        }
        $rows = $pdo->query("SELECT * FROM `$entity`")->fetchAll();
        $logEntity = $entity;
        $scope = $entity;
        if ($_GET['do'] === 'export') {
            $content  = json_encode([$entity => $rows], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $format   = 'json';
            $fileName = $entity . '_export_' . date('Ymd_His') . '.json';
        } else {
            require __DIR__ . '/xlsx_writer.php';
            $dataX = xlsx_build(ENTITY_COLS[$entity], $rows);
            if ($dataX === null) {
                $content  = xlsx_build_csv_string(ENTITY_COLS[$entity], $rows);
                $format   = 'csv';
                $fileName = $entity . '_export_' . date('Ymd_His') . '.csv';
            } else {
                $content  = $dataX;
                $format   = 'xlsx';
                $fileName = $entity . '_export_' . date('Ymd_His') . '.xlsx';
            }
        }
    }

    try {
        $rel = backup_save($fileName, $content);
    } catch (Throwable $ex) {
        log_details($ex);
        flash('err', user_friendly_error_message($ex) . ' Техническая причина: ' . e($ex->getMessage()));
        header('Location: import_export.php'); exit;
    }
    log_action($pdo, $logEntity, 0, 'export', null,
        ['format' => $format, 'scope' => $scope, 'backup' => $rel, 'bytes' => strlen($content)]);
    flash('ok', 'Экспорт сохранён: ' . e($rel) . ' (' . backup_human_size(strlen($content)) . '). Скачать — в списке «Резервные копии».');
    header('Location: import_export.php'); exit;
}

// ================= ИМПОРТ =================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $kind = $_POST['kind'] ?? 'json';

    if ($kind === 'sql') {
        if (!is_role('администратор')) {
            flash('err', 'Импорт SQL-дампа доступен только администратору: файл содержит произвольные операторы БД.');
            header('Location: import_export.php'); exit;
        }
        $file = $_FILES['sql'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            flash('err', 'Выберите SQL-файл дампа (кнопка «Обзор»), затем нажмите «Импортировать SQL».');
        } else {
            $statements = split_sql_dump((string)file_get_contents($file['tmp_name']));
            if (!$statements) {
                flash('err', 'В файле не найдено ни одного SQL-оператора.');
            } else {
                $okCount = 0; $error = null;
                foreach ($statements as $statement) {
                    try {
                        $pdo->exec($statement);
                        $okCount++;
                    } catch (Throwable $ex) {
                        log_details($ex);
                        $error = user_friendly_error_message($ex) . ' Оператор: ' . mb_substr($statement, 0, 200);
                        break;
                    }
                }
                log_action($pdo, 'action_log', 0, 'import', null,
                    ['format' => 'sql', 'statements_ok' => $okCount, 'failed' => $error !== null]);
                if ($error !== null) {
                    flash('err', 'Импорт SQL прерван после ' . $okCount . ' выполненных операторов: ' . e($error));
                } else {
                    flash('ok', 'Импорт SQL завершён: выполнено операторов — ' . $okCount . '.');
                }
            }
        }
        header('Location: import_export.php'); exit;
    }

    // импорт JSON
    $file = $_FILES['json'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        flash('err', 'Выберите JSON-файл для импорта (кнопка «Обзор»), затем нажмите «Импортировать JSON».');
    } else {
        $data = json_decode((string)file_get_contents($file['tmp_name']), true);
        if (!is_array($data)) {
            flash('err', 'Файл не является корректным JSON (ожидается UTF-8). Проверьте файл и повторите импорт.');
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
                    flash('err', 'Импорт отклонён (откат): ' . e(implode('; ', array_slice($errors, 0, 5))) . '.');
                } else {
                    $pdo->commit();
                    log_action($pdo, 'citizens', 0, 'import', null, $counts);
                    flash('ok', 'Импорт JSON завершён: добавлено записей — ' . array_sum($counts) . '.');
                }
            } catch (PDOException $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                flash('err', 'Импорт отклонён (нарушение целостности): ' . e(user_friendly_error_message($ex)));
            }
        }
    }
    header('Location: import_export.php'); exit;
}

// ================= Список резервных копий =================
$backups = [];
if (is_dir(BACKUP_DIR)) {
    foreach (scandir(BACKUP_DIR) ?: [] as $f) {
        if ($f === '.' || $f === '..' || $f === '.htaccess') continue;
        $p = BACKUP_DIR . '/' . $f;
        if (!is_file($p)) continue;
        $backups[] = ['name' => $f, 'size' => (int)filesize($p), 'mtime' => (int)filemtime($p)];
    }
    usort($backups, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
}

require __DIR__ . '/layout/header.php';
?>
<h1>Импорт / Экспорт</h1>
<div class="grid2">
<div class="card">
<h2><i class="fa-solid fa-file-export"></i> Экспорт</h2>
<form class="form-inline" method="get">
<select class="input" name="entity">
<?php foreach (ALL_ENTITIES as $en): ?><option><?= $en ?></option><?php endforeach; ?>
</select>
<button class="btn" name="do" value="export"><i class="fa-solid fa-download"></i> JSON</button>
<button class="btn" name="do" value="export_excel"><i class="fa-solid fa-file-excel"></i> Excel</button>
</form>
<form class="form-inline" method="get" style="margin-top:10px">
<button class="btn btn-outline" name="do" value="export_sql"><i class="fa-solid fa-database"></i> Вся БД (SQL)</button>
<button class="btn btn-outline" name="do" value="export_all_json"><i class="fa-solid fa-database"></i> Вся БД (JSON)</button>
</form>
<p class="muted" style="margin-top:10px">Файлы сохраняются в папку <code>BackUp/</code>; скачивание — из списка ниже.</p>
</div>
<div class="card">
<h2><i class="fa-solid fa-file-import"></i> Импорт</h2>
<form method="post" enctype="multipart/form-data">
<?= csrf_field() ?><input type="hidden" name="kind" value="json">
<input class="input" type="file" name="json" accept=".json,application/json" required>
<div class="form-actions"><button class="btn"><i class="fa-solid fa-upload"></i> Импорт JSON</button></div>
</form>
<?php if (is_role('администратор')): ?>
<form method="post" enctype="multipart/form-data" style="margin-top:12px">
<?= csrf_field() ?><input type="hidden" name="kind" value="sql">
<input class="input" type="file" name="sql" accept=".sql,application/sql" required>
<div class="form-actions"><button class="btn btn-outline"><i class="fa-solid fa-upload"></i> Импорт SQL</button></div>
</form>
<p class="muted">SQL: операторы дампа выполняются последовательно.</p>
<?php endif; ?>
</div>
</div>
<div class="card">
<h2><i class="fa-solid fa-box-archive"></i> Резервные копии (BackUp)</h2>
<?php if (!$backups): ?>
<p class="muted">Папка <code>BackUp/</code> пуста: выполните любой экспорт.</p>
<?php else: ?>
<div class="tbl-wrap">
<table class="tbl">
<thead><tr><th>Файл</th><th>Размер</th><th>Дата</th><th>Скачать</th></tr></thead>
<tbody>
<?php foreach ($backups as $b): ?>
<tr>
<td><code><?= e($b['name']) ?></code></td>
<td><?= backup_human_size($b['size']) ?></td>
<td><?= date('d.m.Y H:i:s', $b['mtime']) ?></td>
<td><a class="btn btn-sm" href="import_export.php?do=backup_get&file=<?= urlencode($b['name']) ?>"><i class="fa-solid fa-download"></i> Скачать</a></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
</div>
<?php require __DIR__ . '/layout/footer.php'; ?>
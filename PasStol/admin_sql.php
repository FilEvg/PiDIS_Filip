<?php
require __DIR__ . '/config.php';
require_role(['администратор']);

// Разбор SQL-текста на отдельные операторы с учётом строковых литералов,
// кавычек идентификаторов и комментариев (--, #, /* */)
function split_sql_statements(string $sql): array
{
    $statements = []; $current = ''; $quote = null;
    $lineComment = false; $blockComment = false;
    $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $ch = $sql[$i];
        if ($lineComment) { $current .= $ch; if ($ch === "\n") $lineComment = false; continue; }
        if ($blockComment) {
            $current .= $ch;
            if ($ch === '*' && ($sql[$i + 1] ?? '') === '/') { $current .= '/'; $i++; $blockComment = false; }
            continue;
        }
        if ($quote) {
            $current .= $ch;
            if ($ch === '\\') { $current .= $sql[$i + 1] ?? ''; $i++; }
            elseif ($ch === $quote) $quote = null;
            continue;
        }
        if ($ch === "'" || $ch === '"' || $ch === '`') { $quote = $ch; $current .= $ch; continue; }
        if ($ch === '-' && ($sql[$i + 1] ?? '') === '-') { $lineComment = true; $current .= $ch; continue; }
        if ($ch === '#') { $lineComment = true; $current .= $ch; continue; }
        if ($ch === '/' && ($sql[$i + 1] ?? '') === '*') { $blockComment = true; $current .= $ch; continue; }
        if ($ch === ';') { $statements[] = trim($current); $current = ''; continue; }
        $current .= $ch;
    }
    if (trim($current) !== '') $statements[] = trim($current);
    return array_values(array_filter($statements, fn($s) => $s !== ''));
}

$results = []; // по каждому оператору: sql, ok, message, rows
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $sqlText = trim($_POST['sql'] ?? '');
    if ($sqlText === '') {
        flash('warn', 'Поле SQL-запроса пустое. Введите один или несколько операторов (например, CREATE TABLE …) и нажмите «Выполнить».');
    } else {
        $statements = split_sql_statements($sqlText);
        $pdo = db();
        foreach ($statements as $statement) {
        // ТЗ 4.1: операции удаления и прямого обновления записей action_log запрещены.
        // Консоль не должна быть обходным путём вокруг запрета.
        if (preg_match('/\b(update|delete|truncate|drop|alter)\s+`?action_log`?\b/i', $statement)) {
            $results[] = ['sql' => $statement, 'ok' => false,
                'message' => 'Оператор отклонён: операции удаления и прямого обновления записей action_log запрещены. '
                    . 'Для исправления данных используйте восстановление из журнала (раздел «Журнал действий»).',
                'rows' => null];
            break;
        }
            // операторы чтения возвращают таблицу результата, остальные выполняются как DDL/DML
            $isReadonly = (bool)preg_match('/^\s*(select|show|describe|desc|explain|with)\b/i', $statement);
            try {
                if ($isReadonly) {
                    $st = $pdo->query($statement);
                    $rows = $st ? $st->fetchAll() : [];
                    $results[] = ['sql' => $statement, 'ok' => true,
                                  'message' => 'Выполнено. Возвращено стр: ' . count($rows), 'rows' => $rows];
                } else {
                    $affected = $pdo->exec($statement);
                    $results[] = ['sql' => $statement, 'ok' => true,
                                  'message' => 'Выполнено успешно. Затронуто строк: ' . ($affected === false ? 0 : (int)$affected),
                                  'rows' => null];
                }
            } catch (Throwable $ex) {
                log_details($ex);
                $results[] = ['sql' => $statement, 'ok' => false,
                              'message' => 'Оператор не выполнен: ' . user_friendly_error_message($ex)
                                         . ' Техническая причина: ' . $ex->getMessage(),
                              'rows' => null];
                break; // при ошибке последующие операторы не выполняются
            }
        }
        // аудит работы консоли (текст запроса сохраняем в after_json)
        log_action($pdo, 'users', (int)$_SESSION['user_id'], 'update', null, [
            'tool' => 'admin_sql_console',
            'statements' => count($statements),
            'sql' => mb_substr($sqlText, 0, 2000),
        ]);
    }
}

require __DIR__ . '/layout/admin_header.php';
?>
<h1>SQL-запросы</h1>
<p class="muted">Консоль администратора для работы со структурой БД <?= e(DB_NAME) ?>: создание и изменение таблиц,
   связей и ограничений, проверочные запросы. Несколько операторов разделяйте точкой с запятой.
   Перед разрушительными операциями (DROP, DELETE) делайте резервную копию.</p>

<div class="card">
  <form method="post">
    <?= csrf_field() ?>
    <label>Текст SQL-запроса(ов) *</label>
    <textarea class="input" name="sql" rows="10" required
              style="font-family:Consolas,monospace;font-size:13px"
              placeholder="CREATE TABLE example (&#10;    example_id INT PRIMARY KEY AUTO_INCREMENT,&#10;    title VARCHAR(100) NOT NULL&#10;);"><?= e($_POST['sql'] ?? '') ?></textarea>
    <div class="form-actions"><button class="btn"><i class="fa-solid fa-bolt"></i> Выполнить</button></div>
  </form>
</div>

<?php foreach ($results as $result): ?>
<div class="card" style="border-left:4px solid <?= $result['ok'] ? '#2ECC71' : '#E74C3C' ?>">
  <pre style="white-space:pre-wrap;background:#22303F;color:#D5E3EE;padding:10px;border-radius:8px;font-size:12px"><?= e($result['sql']) ?></pre>
  <p style="margin:8px 0;color:<?= $result['ok'] ? '#1E9E57' : '#C0392B' ?>">
    <i class="fa-solid <?= $result['ok'] ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i> <?= e($result['message']) ?>
  </p>
  <?php if ($result['rows']): ?>
  <div class="tbl-wrap"><table class="tbl">
    <thead><tr><?php foreach (array_keys($result['rows'][0]) as $columnName): ?><th><?= e($columnName) ?></th><?php endforeach; ?></tr></thead>
    <tbody>
    <?php foreach (array_slice($result['rows'], 0, 200) as $row): ?>
      <tr><?php foreach ($row as $cell): ?><td><?= e($cell ?? 'NULL') ?></td><?php endforeach; ?></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php if (count($result['rows']) > 200): ?><p class="muted">Показаны первые 200 строк результата.</p><?php endif; ?>
  <?php endif; ?>
</div>
<?php endforeach; ?>
<?php require __DIR__ . '/layout/footer.php'; ?>
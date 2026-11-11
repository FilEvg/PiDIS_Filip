<?php
require __DIR__ . '/config.php';
require_role(['администратор', 'оператор']);
require __DIR__ . '/doc_data.php';

// Запуск C++ генератора PDF: текст документа (UTF-8) передаётся в stdin
function make_pdf(array $lines): string
{
    if (!is_file(PDFGEN_BIN)) {
        http_response_code(500);
        exit('Не найден исполняемый файл генератора PDF: ' . e(PDFGEN_BIN)
           . '<br>Соберите проект (скомпилируется pdfgen): <code>php build.php</code> или вручную: '
           . '<code>g++ -std=c++17 -O2 cpp/pdfgen.cpp -o cpp/pdfgen</code>.');
    }
    $tmp = tempnam(sys_get_temp_dir(), 'psk_pdf_');

    $proc = proc_open([PDFGEN_BIN, $tmp], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) exit('Не удалось запустить ' . e(PDFGEN_BIN));
    fwrite($pipes[0], implode("\n", $lines)); fclose($pipes[0]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $code = proc_close($proc);
    if ($code !== 0 || !is_file($tmp) || filesize($tmp) === 0) exit('Ошибка pdfgen: ' . e($err));
    $out = file_get_contents($tmp);
    unlink($tmp);
    return $out;
}

$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if ($type === 'report') {
    $pdo = db();
    $lines = ['ОТЧЁТ АИС «Паспортный стол»', 'Сформирован: ' . date('d.m.Y H:i'), ''];
    $lines[] = 'Граждан всего: ' . $pdo->query('SELECT COUNT(*) FROM citizens')->fetchColumn();
    $lines[] = 'Средний возраст граждан: ' . ($pdo->query('SELECT ROUND(AVG(fn_age(birth_date)), 1) FROM citizens')->fetchColumn() ?: '—');
    $st = $pdo->query('SELECT reg_status, COUNT(*) c FROM citizens GROUP BY reg_status');
    foreach ($st as $r) $lines[] = '  — регистрация «' . $r['reg_status'] . '»: ' . $r['c'];
    $lines[] = '';
    $lines[] = 'Паспортов всего: ' . $pdo->query('SELECT COUNT(*) FROM passports')->fetchColumn();
    $st = $pdo->query('SELECT status, COUNT(*) c FROM passports GROUP BY status');
    foreach ($st as $r) $lines[] = '  — статус «' . $r['status'] . '»: ' . $r['c'];
    $lines[] = '';
    $lines[] = 'Заявлений всего: ' . $pdo->query('SELECT COUNT(*) FROM applications')->fetchColumn();
    $st = $pdo->query('SELECT service_type, COUNT(*) c FROM applications GROUP BY service_type');
    foreach ($st as $r) $lines[] = '  — ' . $r['service_type'] . ': ' . $r['c'];
    log_action($pdo, 'applications', 0, 'export', null, ['format' => 'pdf', 'kind' => 'report']);
    $filename = 'report_' . date('Ymd_His') . '.pdf';
} else {
    try { $d = doc_data($type, $id); } catch (Throwable $ex) { exit(e($ex->getMessage())); }
    $lines = array_merge([$d['title'], str_repeat('-', 40)], doc_lines($d));
    log_action(db(), $d['entity'], $d['entity_id'], 'export', null, ['format' => 'pdf']);
    $filename = $type . '_' . $id . '.pdf';
}

$pdf = make_pdf($lines);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
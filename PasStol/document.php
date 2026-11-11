<?php
require __DIR__ . '/config.php';
require_login();
require __DIR__ . '/doc_data.php';

$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);
try {
    $d = doc_data($type, $id);
} catch (Throwable $ex) {
    flash('err', e($ex->getMessage()));
    header('Location: dashboard.php'); exit;
}
log_action(db(), $d['entity'], $d['entity_id'], 'read');

require __DIR__ . '/layout/header.php';
?>
<h1><?= e($d['title']) ?></h1>
<p class="muted">Шаблон заполнен данными из БД без ручного дублирования ввода. Экспорт печатных форм — в PDF (генератор на C++17).</p>
<div class="doc-paper"><pre class="doc-pre"><?= e(doc_render($d)) ?></pre></div>
<p style="margin-top:16px">
  <a class="btn" href="pdf.php?type=<?= e($type) ?>&id=<?= $id ?>"><i class="fa-solid fa-file-pdf"></i> Экспорт в PDF</a>
  <a class="btn btn-outline" href="javascript:history.back()">← Назад</a>
</p>
<?php require __DIR__ . '/layout/footer.php'; ?>
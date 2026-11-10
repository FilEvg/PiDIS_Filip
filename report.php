<?php
require __DIR__ . '/config.php';
require_role(['администратор', 'оператор']);
$pdo = db();
$citizens  = (int)$pdo->query('SELECT COUNT(*) FROM citizens')->fetchColumn();
$passports = (int)$pdo->query('SELECT COUNT(*) FROM passports')->fetchColumn();
$apps      = (int)$pdo->query('SELECT COUNT(*) FROM applications')->fetchColumn();
$avgAge    = $pdo->query('SELECT ROUND(AVG(fn_age(birth_date)), 1) FROM citizens')->fetchColumn();
$byReg     = $pdo->query('SELECT reg_status, COUNT(*) c FROM citizens GROUP BY reg_status')->fetchAll();
$byStatus  = $pdo->query('SELECT status, COUNT(*) c FROM passports GROUP BY status')->fetchAll();
$byService = $pdo->query('SELECT service_type, status, COUNT(*) c FROM applications GROUP BY service_type, status')->fetchAll();
// демонстрация и реальное использование функций БД в отчёте
$recent    = $pdo->query('SELECT citizen_id,
                                 fn_citizen_full_name(citizen_id) AS fio,
                                 fn_age(birth_date)               AS age,
                                 fn_open_applications(citizen_id) AS open_apps,
                                 fn_active_passport_id(citizen_id) AS active_passport
                          FROM citizens ORDER BY citizen_id DESC LIMIT 5')->fetchAll();
require __DIR__ . '/layout/header.php';
?>
<h1>Отчёт (транзакция T-06)</h1>
<p><a class="btn" href="pdf.php?type=report"><i class="fa-solid fa-file-pdf"></i> Сформировать отчёт и экспортировать в PDF</a></p>
<div class="stats">
<div class="stat"><i class="fa-solid fa-users"></i><b><?= $citizens ?></b><span>граждан</span></div>
<div class="stat"><i class="fa-solid fa-passport"></i><b><?= $passports ?></b><span>паспортов</span></div>
<div class="stat"><i class="fa-solid fa-inbox"></i><b><?= $apps ?></b><span>заявлений</span></div>
<div class="stat"><i class="fa-solid fa-cake-candles"></i><b><?= e($avgAge ?: '—') ?></b><span>средний возраст (fn_age)</span></div>
</div>
<div class="grid2">
<div class="card"><h2>Регистрации</h2><table class="tbl">
<?php foreach ($byReg as $r): ?><tr><td><?= e($r['reg_status']) ?></td><td><?= (int)$r['c'] ?></td></tr><?php endforeach; ?>
</table></div>
<div class="card"><h2>Паспорта по статусам</h2><table class="tbl">
<?php foreach ($byStatus as $r): ?><tr><td><?= e($r['status']) ?></td><td><?= (int)$r['c'] ?></td></tr><?php endforeach; ?>
</table></div>
</div>
<div class="card"><h2>Последние граждане (функции БД)</h2>
<table class="tbl">
<thead><tr><th>№</th><th>ФИО (fn_citizen_full_name)</th><th>Возраст (fn_age)</th><th>Открытых заявлений (fn_open_applications)</th><th>Действующий паспорт (fn_active_passport_id)</th></tr></thead>
<tbody>
<?php foreach ($recent as $r): ?>
<tr><td><?= (int)$r['citizen_id'] ?></td><td><?= e($r['fio']) ?></td><td><?= (int)$r['age'] ?></td>
    <td><?= (int)$r['open_apps'] ?></td><td><?= $r['active_passport'] ? '#' . (int)$r['active_passport'] : '—' ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
<div class="card"><h2>Заявления по услугам и статусам</h2>
<table class="tbl"><thead><tr><th>Услуга</th><th>Статус</th><th>Кол-во</th></tr></thead><tbody>
<?php foreach ($byService as $r): ?><tr><td><?= e($r['service_type']) ?></td><td><?= e($r['status']) ?></td><td><?= (int)$r['c'] ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php require __DIR__ . '/layout/footer.php'; ?>
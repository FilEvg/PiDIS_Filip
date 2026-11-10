<?php
require __DIR__ . '/config.php';
require_login();
require __DIR__ . '/layout/header.php';

$pdo = db();
if (is_role('пользователь')) {
    // ДЕМО: аккаунт "user" привязан к гражданину с citizen_id = 1
    $me = $pdo->query('SELECT * FROM citizens WHERE citizen_id = 1')->fetch();
    $myApps = $pdo->query('SELECT * FROM applications WHERE citizen_id = 1 ORDER BY application_id DESC')->fetchAll();
} else {
    $cnt = [
        'citizens'     => $pdo->query('SELECT COUNT(*) FROM citizens')->fetchColumn(),
        'passports'    => $pdo->query('SELECT COUNT(*) FROM passports')->fetchColumn(),
        'applications' => $pdo->query('SELECT COUNT(*) FROM applications')->fetchColumn(),
        'active'       => $pdo->query("SELECT COUNT(*) FROM citizens WHERE reg_status='активна'")->fetchColumn(),
    ];
    $txs = [
        ['reg',     'fa-user-plus',         'T-01 Регистрация нового гражданина'],
        ['issue',   'fa-passport',          'T-02 Выдача паспорта'],
        ['replace', 'fa-arrows-rotate',     'T-03 Замена паспорта'],
        ['dereg',   'fa-user-minus',        'T-04 Снятие с регистрационного учёта'],
        ['form9',   'fa-file-certificate',  'T-05 Справка о регистрации (форма №9)'],
        ['report',  'fa-chart-column',      'T-06 Формирование отчёта'],
    ];
}
?>
<h1>Главная</h1>

<?php if (is_role('пользователь')): ?>
  <div class="grid2">
    <div class="card">
      <h2>Мои данные</h2>
      <?php if ($me): ?>
        <p><b><?= e($me['last_name'] . ' ' . $me['first_name'] . ' ' . $me['middle_name']) ?></b></p>
        <p class="muted">Дата рождения: <?= e($me['birth_date']) ?></p>
        <p class="muted">Адрес: <?= e($me['reg_address']) ?></p>
        <p>Статус регистрации:
          <span class="pill <?= $me['reg_status'] === 'активна' ? 'ok' : 'err' ?>"><?= e($me['reg_status']) ?></span>
          с <?= e($me['reg_start_date']) ?></p>
        <a class="btn" href="document.php?type=form9&id=1"><i class="fa-solid fa-file-pdf"></i> Справка формы №9</a>
      <?php endif; ?>
    </div>
    <div class="card">
      <h2>Мои заявления</h2>
      <table class="tbl"><thead><tr><th>№</th><th>Услуга</th><th>Статус</th><th>Дата</th></tr></thead><tbody>
      <?php foreach ($myApps as $a): ?>
        <tr><td><?= (int)$a['application_id'] ?></td><td><?= e($a['service_type']) ?></td>
            <td><span class="pill"><?= e($a['status']) ?></span></td><td><?= e($a['created_at']) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
      <p style="margin-top:12px"><a class="btn" href="applications.php"><i class="fa-solid fa-file-pen"></i> Подать заявление</a></p>
    </div>
  </div>
<?php else: ?>
  <div class="stats">
    <div class="stat"><i class="fa-solid fa-users"></i><b><?= (int)$cnt['citizens'] ?></b><span>граждан в реестре</span></div>
    <div class="stat"><i class="fa-solid fa-passport"></i><b><?= (int)$cnt['passports'] ?></b><span>паспортов</span></div>
    <div class="stat"><i class="fa-solid fa-inbox"></i><b><?= (int)$cnt['applications'] ?></b><span>заявлений</span></div>
    <div class="stat"><i class="fa-solid fa-circle-check"></i><b><?= (int)$cnt['active'] ?></b><span>активных регистраций</span></div>
  </div>
  <h2>Законченные транзакции</h2>
  <p class="muted">Каждая транзакция атомарна: при ошибке выполняется откат, действия пишутся в action_log.</p>
  <div class="grid3">
    <?php foreach ($txs as $t): ?>
      <a class="card svc" href="<?= $t[0] === 'form9' ? 'document.php?type=form9&id=1' : ($t[0] === 'report' ? 'report.php' : 'transactions.php?t=' . $t[0]) ?>">
        <i class="fa-solid <?= $t[1] ?>"></i>
        <span><?= e($t[2]) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require __DIR__ . '/layout/footer.php'; ?>
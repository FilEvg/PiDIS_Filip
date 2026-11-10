<?php
require __DIR__ . '/config.php';
require_role(['администратор', 'оператор']);
$pdo = db();
$t = $_GET['t'] ?? 'reg';
if (!in_array($t, ['reg', 'issue', 'replace', 'dereg'], true)) $t = 'reg';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $t = $_POST['t'] ?? 'reg';
    try {
        $pdo->beginTransaction();

        if ($t === 'reg') { // T-01 Регистрация нового гражданина
            $last = trim($_POST['last_name'] ?? ''); $first = trim($_POST['first_name'] ?? '');
            $addr = trim($_POST['reg_address'] ?? ''); $birth = $_POST['birth_date'] ?? ''; $start = $_POST['reg_start_date'] ?? '';
            if ($last === '' || $first === '' || $addr === '' || $birth === '' || $start === '') throw new Exception('Заполните обязательные поля.');
            if (date_is_future($birth)) throw new Exception('Дата рождения не может быть в будущем (сегодня ' . today_str() . '): укажите фактическую дату рождения.');
            if (date_is_future($start)) throw new Exception('Дата начала регистрации не может быть в будущем: регистрация оформляется не ранее текущей даты.');
            if ($start < $birth) throw new Exception('Дата начала регистрации не может быть раньше даты рождения гражданина.');
            $pdo->prepare('INSERT INTO citizens (last_name,first_name,middle_name,birth_date,sex,reg_address,reg_status,reg_start_date) VALUES (?,?,?,?,?,?,"активна",?)')
                ->execute([$last, $first, trim($_POST['middle_name'] ?? '') ?: null, $birth, ($_POST['sex'] ?? 'М') === 'Ж' ? 'Ж' : 'М', $addr, $start]);
            $cid = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO applications (citizen_id,operator_id,service_type,status,created_at) VALUES (?,?,"Регистрация гражданина","завершено",NOW())')
                ->execute([$cid, (int)$_SESSION['user_id']]);
            log_action($pdo, 'citizens', $cid, 'create', null, ['last_name' => $last, 'first_name' => $first, 'reg_status' => 'активна']);
            log_action($pdo, 'applications', (int)$pdo->lastInsertId(), 'create', null, ['service_type' => 'Регистрация гражданина', 'status' => 'завершено']);
            $pdo->commit(); flash('ok', "Гражданин #$cid зарегистрирован.");
        }

        if ($t === 'issue' || $t === 'replace') { // T-02 Выдача / T-03 Замена паспорта
            $cid = (int)($_POST['citizen_id'] ?? 0); $series = trim($_POST['series'] ?? ''); $number = trim($_POST['number'] ?? '');
            $issue = $_POST['issue_date'] ?? ''; $issuer = trim($_POST['issuer'] ?? ''); $reason = trim($_POST['reason'] ?? '');
            if (!preg_match('/^\d{4}$/', $series)) throw new Exception('Серия должна содержать ровно 4 цифры.');
            if (!preg_match('/^\d{6}$/', $number)) throw new Exception('Номер должен содержать ровно 6 цифр.');
            if ($cid <= 0 || $issue === '' || $issuer === '') throw new Exception('Заполните обязательные поля.');
            if (date_is_future($issue)) throw new Exception('Дата выдачи паспорта не может быть в будущем (сегодня ' . today_str() . '): укажите фактическую дату выдачи.');
            $stB = $pdo->prepare('SELECT birth_date FROM citizens WHERE citizen_id = ?'); $stB->execute([$cid]);
            $birth = $stB->fetchColumn();
            if ($birth === false) throw new Exception('Гражданин не найден: обновите страницу и выберите гражданина из списка.');
            if ($issue < $birth) throw new Exception('Дата выдачи паспорта не может быть раньше даты рождения гражданина.');
            // возрастное ограничение: паспорт возможен только с 14 лет
            $minIssue = min_passport_issue_date((string)$birth);
            if ($issue < $minIssue) throw new Exception('Паспорт не может быть выдан гражданину младше 14 лет: '
                . 'дата выдачи должна быть не ранее даты 14-летия (' . $minIssue . '). '
                . 'Исправьте дату выдачи или выберите другого гражданина.');
            if ($t === 'issue') {
                $st = $pdo->prepare('SELECT COUNT(*) FROM passports WHERE series = ? AND number = ?');
                $st->execute([$series, $number]);
                if ((int)$st->fetchColumn() > 0) throw new Exception('Комбинация серия + номер уже используется.');
                $st = $pdo->prepare('SELECT 1 FROM passports WHERE citizen_id=? AND status="действует"'); $st->execute([$cid]);
                if ($st->fetchColumn()) throw new Exception('У гражданина уже есть паспорт со статусом «действует». Используйте «Замена паспорта».');
                $st = $pdo->prepare('SELECT MAX(created_at) FROM applications WHERE citizen_id=? AND service_type="Выдача паспорта"'); $st->execute([$cid]);
                $appDate = $st->fetchColumn();
                if ($appDate && $issue < substr($appDate, 0, 10)) throw new Exception('Дата выдачи не может быть ранее даты подачи заявления.');
                $pdo->prepare('INSERT INTO applications (citizen_id,operator_id,service_type,status,created_at) VALUES (?,?,"Выдача паспорта","завершено",NOW())')
                    ->execute([$cid, (int)$_SESSION['user_id']]);
                log_action($pdo, 'applications', (int)$pdo->lastInsertId(), 'create', null, ['service_type' => 'Выдача паспорта', 'status' => 'завершено']);
                $pdo->prepare('INSERT INTO passports (citizen_id,series,number,issue_date,issuer,status,reason) VALUES (?,?,?,?,?,"действует",?)')
                    ->execute([$cid, $series, $number, $issue, $issuer, $reason ?: null]);
                $pid = (int)$pdo->lastInsertId();
                log_action($pdo, 'passports', $pid, 'create', null, ['series' => $series, 'number' => $number, 'status' => 'действует']);
                $pdo->commit();
                flash('ok', "Паспорт $series $number выдан. <a href=\"document.php?type=passport&id=$pid\">Печатная форма / PDF →</a>");
            } else {
                $st = $pdo->prepare('SELECT * FROM passports WHERE citizen_id=? AND status="действует"'); $st->execute([$cid]);
                $oldP = $st->fetch();
                if (!$oldP) throw new Exception('У гражданина нет действующего паспорта для замены.');
                $pdo->prepare('UPDATE passports SET status="заменён", reason=? WHERE passport_id=?')->execute([$reason ?: 'Замена', $oldP['passport_id']]);
                log_action($pdo, 'passports', (int)$oldP['passport_id'], 'update', $oldP, ['status' => 'заменён']);
                $pdo->prepare('INSERT INTO passports (citizen_id,series,number,issue_date,issuer,status,reason) VALUES (?,?,?,?,?,"действует",?)')
                    ->execute([$cid, $series, $number, $issue, $issuer, $reason ?: null]);
                $pid = (int)$pdo->lastInsertId();
                log_action($pdo, 'passports', $pid, 'create', null, ['series' => $series, 'number' => $number]);
                $pdo->prepare('INSERT INTO applications (citizen_id,operator_id,service_type,status,created_at) VALUES (?,?,"Замена паспорта","завершено",NOW())')
                    ->execute([$cid, (int)$_SESSION['user_id']]);
                $pdo->commit();
                flash('ok', "Паспорт заменён. <a href=\"document.php?type=passport&id=$pid\">Печатная форма / PDF →</a>");
            }
        }

        if ($t === 'dereg') { // T-04 Снятие с регистрационного учёта
            $cid = (int)($_POST['citizen_id'] ?? 0); $date = $_POST['dereg_date'] ?? '';
            $reason = trim($_POST['reason'] ?? '');
            if ($cid <= 0 || $date === '' || $reason === '') throw new Exception('Заполните обязательные поля.');
            if (date_is_future($date)) throw new Exception('Дата снятия с регистрационного учёта не может быть в будущем (сегодня ' . today_str() . '): укажите фактическую дату.');
            $st = $pdo->prepare('SELECT COUNT(*) FROM applications WHERE citizen_id=? AND status IN ("подано","в работе")'); $st->execute([$cid]);
            if ($st->fetchColumn() > 0) throw new Exception('У гражданина есть заявления со статусом «подано» или «в работе».');
            $st = $pdo->prepare('SELECT * FROM citizens WHERE citizen_id=?'); $st->execute([$cid]);
            $c = $st->fetch();
            if (!$c) throw new Exception('Гражданин не найден.');
            if ($date < $c['reg_start_date']) throw new Exception('Дата снятия не может быть ранее даты начала регистрации.');
            $pdo->prepare('UPDATE citizens SET reg_status="прекращена", reg_end_date=? WHERE citizen_id=?')->execute([$date, $cid]);
            log_action($pdo, 'citizens', $cid, 'update',
                ['reg_status' => $c['reg_status'], 'reg_end_date' => $c['reg_end_date']],
                ['reg_status' => 'прекращена', 'reg_end_date' => $date]);
            $pdo->commit();
            flash('ok', "Гражданин снят с регистрационного учёта. <a href=\"document.php?type=dereg&id=$cid\">Уведомление / PDF →</a>");
        }

        header('Location: transactions.php?t=' . $t); exit;
    } catch (Throwable $ex) {
        // ЕДИНАЯ точка вывода ошибок всех четырёх транзакций: откат + окно сообщения
        if ($pdo->inTransaction()) $pdo->rollBack();
        flash('err', 'Откат транзакции: ' . e($ex->getMessage()));
        header('Location: transactions.php?t=' . $t); exit;
    }
}
$citizens = $pdo->query('SELECT citizen_id, last_name, first_name, middle_name FROM citizens ORDER BY last_name')->fetchAll();
require __DIR__ . '/layout/header.php';
?>
<h1>Транзакции</h1>
<div class="tabs">
<a class="tab <?= $t==='reg'?'active':'' ?>" href="transactions.php?t=reg">Регистрация гражданина</a>
<a class="tab <?= $t==='issue'?'active':'' ?>" href="transactions.php?t=issue">Выдача паспорта</a>
<a class="tab <?= $t==='replace'?'active':'' ?>" href="transactions.php?t=replace">Замена паспорта</a>
<a class="tab <?= $t==='dereg'?'active':'' ?>" href="transactions.php?t=dereg">Снятие с учёта</a>
</div>
<div class="card">
<form method="post" class="form-grid">
<?= csrf_field() ?><input type="hidden" name="t" value="<?= e($t) ?>">
<?php if ($t === 'reg'): ?>
<div><label>Фамилия *</label><input class="input" name="last_name" required></div>
<div><label>Имя *</label><input class="input" name="first_name" required></div>
<div><label>Отчество</label><input class="input" name="middle_name"></div>
<div><label>Дата рождения *</label><input class="input" type="date" name="birth_date" required></div>
<div><label>Пол</label><select class="input" name="sex"><option>М</option><option>Ж</option></select></div>
<div><label>Адрес регистрации *</label><input class="input" name="reg_address" required></div>
<div><label>Дата начала регистрации *</label><input class="input" type="date" name="reg_start_date" required></div>
<?php elseif ($t === 'issue' || $t === 'replace'): ?>
<div><label>Гражданин *</label><select class="input" name="citizen_id" required><option value="">—</option>
<?php foreach ($citizens as $c): ?><option value="<?= (int)$c['citizen_id'] ?>"><?= e($c['last_name'].' '.$c['first_name'].' '.$c['middle_name']) ?></option><?php endforeach; ?>
</select></div>
<div><label>Серия (4 цифры) *</label><input class="input" name="series" maxlength="4" required placeholder="0000"></div>
<div><label>Номер (6 цифр) *</label><input class="input" name="number" maxlength="6" required placeholder="000000"></div>
<div><label>Дата выдачи *</label><input class="input" type="date" name="issue_date" required></div>
<div><label>Орган выдачи *</label><input class="input" name="issuer" required></div>
<div><label>Основание</label><input class="input" name="reason" placeholder="<?= $t==='replace' ? 'утрата, достижение 20/45 лет…' : 'по достижении 14 лет' ?>"></div>
<?php else: ?>
<div><label>Гражданин *</label><select class="input" name="citizen_id" required><option value="">—</option>
<?php foreach ($citizens as $c): ?><option value="<?= (int)$c['citizen_id'] ?>"><?= e($c['last_name'].' '.$c['first_name'].' '.$c['middle_name']) ?></option><?php endforeach; ?>
</select></div>
<div><label>Дата снятия *</label><input class="input" type="date" name="dereg_date" required></div>
<div><label>Причина *</label><input class="input" name="reason" required placeholder="переезд, продажа жилья…"></div>
<div><label>Новое место жительства (необязательно)</label><input class="input" name="new_address"></div>
<?php endif; ?>
<div class="form-actions"><button class="btn"><i class="fa-solid fa-bolt"></i> Выполнить транзакцию</button></div>
</form>
<p class="muted">Транзакция атомарна: при любой ошибке все изменения откатываются, успех фиксируется в action_log.
Ошибки во всех четырёх транзакциях выводятся одинаково — окном сообщения с пояснением и порядком действий.
Паспорт выдаётся только гражданам с 14 лет: дата выдачи не может быть раньше даты 14-летия.</p>
</div>
<?php require __DIR__ . '/layout/footer.php'; ?>
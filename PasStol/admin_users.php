<?php
require __DIR__ . '/config.php';
require __DIR__ . '/components/password_checklist.php';
require_role(['администратор']);
$pdo = db();
$selfUserId = (int)($_SESSION['user_id'] ?? 0);
// показ архивных (удалённых) учётных записей — только по явному переключателю
$showArchived = isset($_GET['show_archived']);
$redirectUrl  = 'admin_users.php' . ($showArchived ? '?show_archived=1' : '');

function make_hash(string $p): string
{
    return defined('PASSWORD_ARGON2ID') ? password_hash($p, PASSWORD_ARGON2ID) : password_hash($p, PASSWORD_DEFAULT);
}
function mask(array $row): array { $row['password_hash'] = '***'; $row['mfa_secret'] = '***'; return $row; }

// Текст значения datetime-local ("2027-01-01T12:30") -> формат MySQL ("2027-01-01 12:30:00");
// пусто => null (не менять блокировку), невалидно => false
function parse_block_until(string $raw)
{
    $raw = trim($raw);
    if ($raw === '') return null;
    $ts = strtotime(str_replace('T', ' ', $raw));
    return $ts === false ? false : date('Y-m-d H:i:s', $ts);
}

// Подпись статуса блокировки для таблицы
function blocked_label(array $row): string
{
    if ((int)$row['is_deleted'] === 1) return '<span class="muted">—</span>';
    if ((int)$row['is_blocked'] !== 1) return '<span class="pill ok">активен</span>';
    if (empty($row['locked_until']))   return '<span class="pill err">до разблокировки администратором</span>';
    $ts = strtotime($row['locked_until']);
    if ($ts === false || $ts <= time()) return '<span class="pill err">срок истёк — разблокируйте</span>';
    return '<span class="pill err">до ' . date('d.m.Y H:i', $ts) . '</span>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    // ---- снятие блокировки (Приложение 1) -> процедура sp_unlock_user ----
    if ($action === 'unlock') {
        $id = (int)$_POST['id'];
        try {
            $pdo->prepare('CALL sp_unlock_user(?, ?)')->execute([$id, $selfUserId]);
            flash('ok', 'Блокировка снята: пользователь снова может авторизоваться.');
        } catch (PDOException $ex) {
            flash('warn', e($ex->getMessage()));
        }
        header('Location: admin_users.php'); exit;
    }

    // ---- БЕЗОПАСНОЕ удаление (вариант 1): архив + «надгробный» логин ----
    // Строка остаётся в таблице (записи журнала и FK-связи сохранены),
    // но логин переименовывается в deleted_<id>_<дата> и освобождается для новых регистраций.
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id === $selfUserId) {
            flash('err', 'Удаление собственной учётной записи запрещено. Для прекращения доступа используйте другую учётную запись администратора.');
        } else {
            $st = $pdo->prepare('SELECT * FROM users WHERE user_id = ? AND is_deleted = 0'); $st->execute([$id]);
            $old = $st->fetch();
            if ($old && $old['role'] === 'администратор') {
                flash('err', 'Удаление учётной записи администратора запрещено. Измените роль пользователя, если доступ нужно прекратить.');
            } elseif ($old) {
                $tombstoneLogin = 'deleted_' . $id . '_' . date('Ymd');
                $pdo->prepare('UPDATE users SET is_deleted = 1, is_blocked = 1, login = ? WHERE user_id = ?')
                    ->execute([$tombstoneLogin, $id]);
                log_action($pdo, 'users', $id, 'delete', mask($old),
                    ['is_deleted' => 1, 'is_blocked' => 1, 'login' => $tombstoneLogin]);
                flash('ok', 'Учётная запись «' . e($old['login']) . '» удалена (перемещена в архив): '
                    . 'записи журнала и связи сохранены, логин освобождён для новых регистраций. '
                    . 'Восстановить учётную запись можно из журнала действий кнопкой «Восстановить».');
            } else {
                flash('warn', 'Пользователь не найден или уже удалён: обновите список и повторите действие.');
            }
        }
        header('Location: ' . $redirectUrl); exit;
    }

    if (in_array($action, ['create', 'update'], true)) {
        $login        = trim($_POST['login'] ?? '');
        $postedRole   = $_POST['role'] ?? 'пользователь';
        $pass         = $_POST['password'] ?? '';
        $blockUntil   = parse_block_until($_POST['block_until'] ?? '');
        $editingId    = (int)($_POST['id'] ?? 0);
        $isSelf       = ($action === 'update' && $editingId === $selfUserId);

        if ($blockUntil === false) {
            flash('err', 'Некорректная дата блокировки в поле «Заблокировать до». Укажите значение в формате ДД.ММ.ГГГГ ЧЧ:ММ или оставьте поле пустым.');
        } elseif ($login === '') {
            flash('err', 'Поле «Логин» обязательно для заполнения. Укажите уникальный логин и повторите сохранение.');
        } elseif ($action === 'create' && ($err = password_policy_errors($pass))) {
            flash('err', 'Пароль не соответствует политике: ' . e(implode('; ', $err)) . '. Задайте другой пароль.');
        } elseif ($action === 'update' && $pass !== '' && ($err = password_policy_errors($pass))) {
            flash('err', 'Новый пароль не соответствует политике: ' . e(implode('; ', $err)) . '. Задайте другой пароль.');
        } else {
            // проверка уникальности логина (Приложение 1); архивные строки носят
            // «надгробные» логины, поэтому освобождённым логином конфликт не создаётся
            $st = $pdo->prepare('SELECT user_id FROM users WHERE login = ? AND user_id <> ?');
            $st->execute([$login, $editingId]);
            if ($st->fetch()) {
                flash('err', 'Пользователь с логином «' . e($login) . '» уже существует в базе данных. Выберите другой логин и повторите добавление.');
            } else {
                try {
                    if ($action === 'create') {
                        $pdo->prepare('INSERT INTO users (login, password_hash, role, mfa_secret, last_password_change, failed_attempts, is_blocked, locked_until)
                                       VALUES (?,?,?,?,CURDATE(),0,?,?)')
                            ->execute([$login, make_hash($pass), $postedRole, crypto_encrypt(base64_encode(random_bytes(16))),
                                       $blockUntil !== null ? 1 : 0, $blockUntil]);
                        $id = (int)$pdo->lastInsertId();
                        log_action($pdo, 'users', $id, 'create', null, ['login' => $login, 'role' => $postedRole, 'locked_until' => $blockUntil]);
                        flash('ok', 'Пользователь «' . e($login) . '» добавлен.');
                    } else {
                        $st = $pdo->prepare('SELECT * FROM users WHERE user_id = ? AND is_deleted = 0'); $st->execute([$editingId]);
                        $old = $st->fetch();
                        if (!$old) {
                            flash('err', 'Пользователь не найден или удалён: обновите страницу и повторите действие.');
                        } else {
                            // Смена СОБСТВЕННОЙ роли запрещена: роль всегда берётся из БД,
                            // а не из формы (защита и от подменённого POST)
                            $roleToSave = $isSelf ? $old['role'] : $postedRole;
                            $roleChanged = !$isSelf && $roleToSave !== $old['role'];
                            // Блокировка: если срок задан — ставим is_blocked=1 и locked_until;
                            // если поле пустое — блокировку не трогаем (остаётся как в БД)
                            if ($blockUntil !== null) {
                                $isBlockedToSave = 1;
                                $lockedUntilToSave = $blockUntil;
                            } else {
                                $isBlockedToSave = (int)$old['is_blocked'];
                                $lockedUntilToSave = $old['locked_until'];
                            }
                            $pdo->prepare('UPDATE users SET login = ?, role = ?, is_blocked = ?, locked_until = ?'
                                . ($pass !== '' ? ', password_hash = ?, last_password_change = CURDATE()' : '')
                                . ' WHERE user_id = ?')
                                ->execute($pass !== ''
                                    ? [$login, $roleToSave, $isBlockedToSave, $lockedUntilToSave, make_hash($pass), $editingId]
                                    : [$login, $roleToSave, $isBlockedToSave, $lockedUntilToSave, $editingId]);
                            log_action($pdo, 'users', $editingId, 'update', mask($old),
                                ['login' => $login, 'role' => $roleToSave, 'is_blocked' => $isBlockedToSave, 'locked_until' => $lockedUntilToSave]);
                            $msg = 'Данные пользователя «' . e($login) . '» изменены.';
                            if ($isSelf && $postedRole !== $old['role']) {
                                $msg .= ' Смена собственной роли запрещена — роль оставлена без изменений.';
                            }
                            if ($roleChanged) {
                                $msg .= ' Роль изменена; новый набор прав станет доступен пользователю после повторного входа в учётную запись.';
                            }
                            flash('ok', $msg);
                        }
                    }
                } catch (PDOException $ex) {
                    flash('err', 'Не удалось сохранить пользователя: ' . e(user_friendly_error_message($ex)));
                }
            }
        }
        header('Location: ' . $redirectUrl); exit;
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM users WHERE user_id = ? AND is_deleted = 0'); $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}
$isEditingSelf = ($edit !== null && (int)$edit['user_id'] === $selfUserId);
$rows = $pdo->query('SELECT * FROM users ' . ($showArchived ? '' : 'WHERE is_deleted = 0 ') . 'ORDER BY user_id')->fetchAll();

require __DIR__ . '/layout/admin_header.php';
?>
<h1>Пользователи</h1>
<p class="muted">Удаление переводит учётную запись в архив (is_deleted) с «надгробным» логином deleted_&lt;id&gt;_&lt;дата&gt;:
   журнал и связи сохраняются, логин освобождается. Архив скрыт по умолчанию.</p>
<p style="display:flex;gap:10px;flex-wrap:wrap">
  <a class="btn" href="admin_users.php?new=1<?= $showArchived ? '&show_archived=1' : '' ?>"><i class="fa-solid fa-user-plus"></i> Добавить пользователя</a>
  <a class="btn btn-outline" href="<?= $showArchived ? 'admin_users.php' : 'admin_users.php?show_archived=1' ?>">
    <i class="fa-solid <?= $showArchived ? 'fa-eye-slash' : 'fa-box-archive' ?>"></i>
    <?= $showArchived ? 'Скрыть архивные' : 'Показать архивные' ?></a>
</p>
<?php if ($edit !== null || isset($_GET['new'])): $v = $edit ?: ['login' => '', 'role' => 'пользователь', 'locked_until' => '']; ?>
<div class="card">
  <h2><?= $edit ? 'Изменить пользователя #' . (int)$edit['user_id'] : 'Новый пользователь' ?></h2>
  <?php if ($isEditingSelf): ?>
  <div class="flash warn" style="margin-bottom:10px"><i class="fa-solid fa-circle-info"></i>
  Вы редактируете собственную учётную запись: смена роли и установка блокировки для себя недоступны.</div>
  <?php endif; ?>
  <form method="post" class="form-grid">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
    <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$edit['user_id'] ?>"><?php endif; ?>
    <div><label>Логин *</label><input class="input" name="login" required value="<?= e($v['login']) ?>"></div>
    <div><label>Роль<?= $isEditingSelf ? ' (нельзя менять свою роль)' : '' ?></label>
      <select class="input" name="role" <?= $isEditingSelf ? 'disabled' : '' ?>>
        <?php foreach (['администратор', 'оператор', 'пользователь'] as $r): ?>
        <option <?= ($isEditingSelf ? $edit['role'] : $v['role']) === $r ? 'selected' : '' ?>><?= $r ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div><label>Пароль <?= $edit ? '(пусто — не менять)' : '*' ?></label>
<input class="input" type="password" name="password" id="adminUserPassword" <?= $edit ? '' : 'required' ?>>
<?= component_password_checklist(password_policy_rules(), 'adminUserPassword') ?>
<?php if ($edit): ?>
<p class="muted">Оставьте поле пустым, чтобы сохранить текущий пароль: чек-лист выше проверяется только при вводе нового пароля.</p>
<?php endif; ?>
</div>
    <?php if (!$isEditingSelf): ?>
    <div><label>Заблокировать до (пусто — не менять блокировку)</label>
      <input class="input" type="datetime-local" name="block_until"
             value="<?= !empty($v['locked_until']) ? e(date('Y-m-d\TH:i', strtotime($v['locked_until']))) : '' ?>"></div>
    <?php endif; ?>
    <div class="form-actions"><button class="btn"><i class="fa-solid fa-floppy-disk"></i> Сохранить</button>
    <a class="btn btn-outline" href="<?= $redirectUrl ?>">Отмена</a></div>
  </form>
</div>
<?php endif; ?>
<div class="tbl-wrap">
<table class="tbl">
<thead><tr><th>№</th><th>Логин</th><th>Роль</th><th>Пароль изменён</th><th>Неудачных попыток</th><th>Блокировка</th><th>Действия</th></tr></thead>
<tbody>
<?php foreach ($rows as $r): $rIsSelf = ((int)$r['user_id'] === $selfUserId); $rArchived = ((int)$r['is_deleted'] === 1); ?>
<tr>
  <td><?= (int)$r['user_id'] ?><?= $rIsSelf ? ' <span class="muted">(вы)</span>' : '' ?></td>
  <td><b><?= e($r['login']) ?></b><?= $rArchived ? ' <span class="pill err">архив</span>' : '' ?></td>
  <td><span class="pill"><?= e($r['role']) ?></span></td>
  <td><?= e($r['last_password_change']) ?><?= strtotime($r['last_password_change']) < strtotime('-90 days') ? ' <span class="pill err">&gt;90 дней</span>' : '' ?></td>
  <td><?= (int)$r['failed_attempts'] ?></td>
  <td><?= blocked_label($r) ?></td>
  <td class="rowbtns">
    <?php if ($rArchived): ?>
      <span class="muted">восстановление — в журнале действий</span>
    <?php else: ?>
      <a class="btn btn-sm btn-outline" href="admin_users.php?edit=<?= (int)$r['user_id'] ?><?= $showArchived ? '&show_archived=1' : '' ?>" title="Изменить данные"><i class="fa-solid fa-pen"></i></a>
      <?php if ((int)$r['is_blocked'] === 1): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="unlock"><input type="hidden" name="id" value="<?= (int)$r['user_id'] ?>">
        <button class="btn btn-sm" title="Снять блокировку"><i class="fa-solid fa-unlock"></i> Разблокировать</button></form>
      <?php endif; ?>
      <?php if (!$rIsSelf && $r['role'] !== 'администратор'): ?>
      <form class="confirm" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['user_id'] ?>">
        <button class="btn btn-sm btn-danger" title="Удалить (в архив)"><i class="fa-solid fa-trash"></i></button></form>
      <?php endif; ?>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php require __DIR__ . '/layout/footer.php'; ?>
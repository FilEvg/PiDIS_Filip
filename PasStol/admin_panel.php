<?php
require __DIR__ . '/config.php';
require_role(['администратор']);
require __DIR__ . '/layout/admin_header.php';

$pdo = db();
$usersTotal    = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$usersBlocked  = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE is_blocked = 1')->fetchColumn();
$logTotal      = (int)$pdo->query('SELECT COUNT(*) FROM action_log')->fetchColumn();
$citizensTotal = (int)$pdo->query('SELECT COUNT(*) FROM citizens')->fetchColumn();
$notesTotal    = (int)$pdo->query('SELECT COUNT(*) FROM notes')->fetchColumn();
?>
<h1>Обзор панели администратора</h1>
<p class="muted">Функции администратора вынесены в отдельное пространство. Возврат на обычный сайт — кнопка «К сайту» в верхней панели.</p>

<div class="stats">
  <div class="stat"><i class="fa-solid fa-users"></i><b><?= $usersTotal ?></b><span>учётных записей</span></div>
  <div class="stat"><i class="fa-solid fa-user-lock"></i><b><?= $usersBlocked ?></b><span>заблокировано</span></div>
  <div class="stat"><i class="fa-solid fa-clock-rotate-left"></i><b><?= $logTotal ?></b><span>записей журнала</span></div>
  <div class="stat"><i class="fa-solid fa-note-sticky"></i><b><?= $notesTotal ?></b><span>заметок в API</span></div>
</div>

<div class="grid2">
  <div class="card">
    <h2><i class="fa-solid fa-users-gear"></i> Пользователи</h2>
    <p class="muted">Добавление и изменение учётных записей, проверка уникальности логина, снятие блокировки, удаление (кроме администраторов).</p>
    <a class="btn" href="admin_users.php"><i class="fa-solid fa-arrow-right"></i> Открыть раздел</a>
  </div>
  <div class="card">
    <h2><i class="fa-solid fa-clock-rotate-left"></i> Журнал действий</h2>
    <p class="muted">Просмотр action_log (не более 100 записей), фильтры и поиск, просмотр before/after JSON, восстановление состояний сущностей.</p>
    <a class="btn" href="admin_journal.php"><i class="fa-solid fa-arrow-right"></i> Открыть раздел</a>
  </div>
  <div class="card">
    <h2><i class="fa-solid fa-database"></i> SQL-запросы</h2>
    <p class="muted">Консоль администратора: создание и изменение таблиц, связей и ограничений, проверочные запросы SELECT.</p>
    <a class="btn" href="admin_sql.php"><i class="fa-solid fa-arrow-right"></i> Открыть консоль</a>
  </div>
  <div class="card">
    <h2><i class="fa-solid fa-file-import"></i> Импорт / Экспорт</h2>
    <p class="muted">Экспорт сущностей в JSON и Excel, импорт из JSON — находится в общем разделе сайта (доступен администратору и оператору).</p>
    <a class="btn btn-outline" href="import_export.php"><i class="fa-solid fa-arrow-right"></i> Перейти в раздел</a>
  </div>

  <div class="card" style="grid-column:1/-1;border-left:4px solid #3498DB">
    <h2><i class="fa-solid fa-plug"></i> Включённые API-функции</h2>
    <p class="muted">Программные интерфейсы системы (JSON-ответы, без веб-интерфейса). Кнопка открывает эндпоинт в новой вкладке.</p>

    <table class="tbl">
      <thead><tr><th>Название</th><th>Метод</th><th>Описание</th><th>Прямая ссылка</th></tr></thead>
      <tbody>
        <tr>
          <td><b>Список заметок</b></td>
          <td><code>GET</code></td>
          <td>Возвращает заметки из таблицы <code>notes</code>. Данные обрабатываются: <code>title_user</code> = «{title} - {login}»,
              <code>formatted_date</code> в формате ДД.ММ.ГГГГ. При отсутствии записей — статус 200 и пустой массив <code>[]</code>.</td>
          <td>
            <a class="btn btn-sm" href="api/notes.php" target="_blank" rel="noopener">
              <i class="fa-solid fa-up-right-from-square"></i> /api/notes.php</a>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
<?php require __DIR__ . '/layout/footer.php'; ?>
<?php
// ============ РЕЕСТР МОДУЛЕЙ САЙТА (конструктор) ============
// Меню и разделы собираются из этих списков «как из конструктора»:
//   * чтобы добавить раздел — допишите элемент массива;
//   * чтобы «отключить» — закомментируйте или удалите элемент.
// Формат элемента (основной сайт): [url, заголовок, иконка FontAwesome, роли|null]
//   null — раздел виден всем авторизованным; иначе массив разрешённых ролей.

function site_modules(): array
{
    return [
        ['dashboard.php',     'Главная',          'fa-house',          null],
        ['citizens.php',      'Граждане',         'fa-users',          ['администратор', 'оператор']],
        ['passports.php',     'Паспорта',         'fa-passport',       ['администратор', 'оператор']],
        ['applications.php',  'Заявления',        'fa-inbox',          null],
        ['transactions.php',  'Транзакции',       'fa-diagram-project',['администратор', 'оператор']],
        ['report.php',        'Отчёты',           'fa-chart-column',   ['администратор', 'оператор']],
        ['import_export.php', 'Импорт/Экспорт',   'fa-file-import',    ['администратор', 'оператор']],
    ];
}

// Модули панели администратора: [url, заголовок, иконка FontAwesome]
function admin_modules(): array
{
    return [
        ['admin_panel.php',   'Обзор',           'fa-gauge-high'],
        ['admin_users.php',   'Пользователи',    'fa-users-gear'],
        ['admin_journal.php', 'Журнал действий', 'fa-clock-rotate-left'],
        ['admin_sql.php',     'SQL-запросы',     'fa-database'],
    ];
}

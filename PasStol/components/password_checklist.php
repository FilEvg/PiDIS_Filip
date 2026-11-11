<?php
// Компонент «живой чек-лист правил пароля» — переиспользуемая часть форм:
// регистрация (register.php), смена пароля (password.php),
// добавление/изменение пользователя (admin_users.php).
// JS (assets/app.js, initPasswordChecklist) подсвечивает выполненные правила
// для того поля ввода, id которого указан в атрибуте data-for.
function component_password_checklist(array $rules, string $inputId = 'registerPassword'): string
{
    $html = '<div class="pw-rules" id="pwRules-' . e($inputId) . '" data-for="' . e($inputId) . '">';
    foreach ($rules as $key => $label) {
        $fullWidth = ($key === 'seq') ? ' style="grid-column:1/-1"' : '';
        $html .= '<div data-rule="' . e($key) . '"' . $fullWidth . '>'
            . '<i class="fa-regular fa-circle"></i> ' . e($label) . '</div>';
    }
    return $html . '</div>';
}
// Стили чек-листа (для страниц со своим <head>, например register.php).
// Те же правила продублированы в assets/style.css — чтобы чек-лист выглядел
// одинаково на страницах с общей шапкой (password.php, admin_users.php).
function component_password_checklist_css(): string
{
    return '.pw-rules{display:grid;grid-template-columns:1fr 1fr;gap:4px 14px;margin:8px 0 4px;font-size:12px;color:#8A99A8}'
        . '.pw-rules div{display:flex;gap:7px;align-items:flex-start}'
        . '.pw-rules i{margin-top:2px;font-size:12px}'
        . '.pw-rules div.ok{color:#1E9E57}';
}
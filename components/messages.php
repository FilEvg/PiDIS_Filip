<?php
// Компонент «окна сообщений» — передаёт очередь сообщений (тип, текст) со страниц
// входа/регистрации в JS. JS (assets/app.js) рисует окна с заголовком и пиктограммой.

function component_auth_messages(array $messages): string
{
    return '<script>window.authMessages = '
        . json_encode($messages, JSON_UNESCAPED_UNICODE) . ';</script>';
}

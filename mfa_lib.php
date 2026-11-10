<?php
// ============ Библиотека двухфакторной аутентификации (2FA) ============
// Поток входа:
//   шаг 1 — index.php: логин + пароль + капча;
//   шаг 2 — verify.php: одноразовый 6-значный код (срок действия 5 минут).
//
// УЧЕБНАЯ СИСТЕМА: код хранится в сессии и выводится на экран как «демо-код».
// В реальной системе здесь следовало бы хранить только хеш кода (password_hash).

// Выпустить одноразовый код для пользователя. Возвращает сам код (для доставки/демо).
function mfa_issue_code(int $userId): string
{
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    $_SESSION['mfa_user_id'] = $userId;          // какая учётная запись проходит шаг 2
    $_SESSION['mfa_code']    = $code;            // код (в реальной системе — только хеш)
    $_SESSION['mfa_exp']     = time() + 300;     // срок действия кода — 5 минут
    $_SESSION['mfa_fails']   = 0;                // счётчик неверных вводов кода

    return $code;
}

// Сравнение введённого кода с выданным (постоянное по времени сравнение).
function mfa_verify_code(string $code): bool
{
    return isset($_SESSION['mfa_code'])
        && hash_equals((string)$_SESSION['mfa_code'], $code);
}

// Есть ли незавершённый шаг 2 (пользователь ждёт ввода кода)?
function mfa_has_pending(): bool
{
    return !empty($_SESSION['mfa_user_id']);
}

// Очистить состояние шага 2 (завершён вход, отмена или блокировка).
function mfa_clear(): void
{
    unset($_SESSION['mfa_user_id'], $_SESSION['mfa_code'],
          $_SESSION['mfa_exp'], $_SESSION['mfa_fails']);
}

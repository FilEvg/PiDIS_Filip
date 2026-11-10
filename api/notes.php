<?php
// API по Прил_5_ОЗ_КИМ_09.02.07-5-2027: GET /api/notes — список заметок.
// Данные НЕ отдаются в исходном виде: перед отправкой каждое поле обрабатывается.
// Все ответы (включая ошибки) — только в формате JSON.
declare(strict_types=1);

// Подключаем конфиг в try/catch: если БД недоступна уже на этом шаге,
// возвращаем корректный JSON со статусом 500, а не HTML-страницу ошибки
try {
    require __DIR__ . '/../config.php';
} catch (Throwable $ex) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Ошибка подключения к базе данных: ' . $ex->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Единая точка выхода API: статус + JSON-тело
function api_response(int $status, $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // Эндпоинт поддерживает только метод GET
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        api_response(405, ['error' => 'Метод не поддерживается: для получения заметок используйте GET /api/notes.']);
    }

    // Тестовый крючок для коллекции Postman (тест на статус 500):
    // имитирует внутреннюю ошибку сервера, в реальной работе не используется
    if (isset($_GET['simulate_error'])) {
        throw new RuntimeException('Ошибка подключения к базе данных (имитация для теста статуса 500).');
    }

    // Разрешённые параметры запроса; всё остальное — ошибка клиента 400
    $allowedParameters = ['id'];
    foreach (array_keys($_GET) as $parameterName) {
        if (!in_array($parameterName, $allowedParameters, true)) {
            api_response(400, ['error' => 'Некорректный запрос: неизвестный параметр "' . $parameterName
                . '". Допустимый параметр: id (целое число).']);
        }
    }

    // Необязательный фильтр по коду заметки; неверный тип данных — 400
    $noteId = null;
    if (isset($_GET['id'])) {
        if (!preg_match('/^\d+$/', (string)$_GET['id'])) {
            api_response(400, ['error' => 'Некорректный запрос: параметр id должен быть целым положительным числом, '
                . 'получено значение "' . $_GET['id'] . '".']);
        }
        $noteId = (int)$_GET['id'];
    }

    $pdo = db();
    $sql = 'SELECT n.id, n.title, n.content, n.created_at, u.login
            FROM notes n JOIN users u ON u.user_id = n.id_user';
    $params = [];
    if ($noteId !== null) {
        $sql .= ' WHERE n.id = ?';
        $params[] = $noteId;
    }
    $sql .= ' ORDER BY n.id';
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $notes = $statement->fetchAll();

    // Преобразование каждой заметки по правилам Прил_5:
    // id — из notes.id; title_user — "{title} - {login}"; content — без изменений;
    // formatted_date — created_at в формате ДД.ММ.ГГГГ
    $result = array_map(function (array $note): array {
        $createdAt = strtotime((string)$note['created_at']);
        return [
            'id'             => (int)$note['id'],
            'title_user'     => $note['title'] . ' - ' . $note['login'],
            'content'        => $note['content'],
            'formatted_date' => $createdAt ? date('d.m.Y', $createdAt) : null,
        ];
    }, $notes);

    // Пустой массив [] — корректный ответ 200, когда данных нет (таблица пуста
    // или нет заметок, подходящих под условия); это не ошибка
    api_response(200, $result);
} catch (Throwable $ex) {
    // Внутренняя ошибка сервера: явно сообщаем кодом 500 и JSON-телом с ошибкой
    log_details($ex);
    api_response(500, ['error' => 'Внутренняя ошибка сервера: ' . $ex->getMessage()]);
}
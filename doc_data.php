<?php
// Шаблоны документов по ТЗ: подстановки вида {{таблица.поле}}
function doc_data(string $type, int $id): array
{
    $pdo = db();

    if ($type === 'form9') {
        $st = $pdo->prepare('SELECT * FROM citizens WHERE citizen_id = ?'); $st->execute([$id]);
        $c = $st->fetch();
        if (!$c) throw new RuntimeException('Гражданин не найден.');
        return [
            'title' => 'Справка формы №9', 'entity' => 'citizens', 'entity_id' => $id,
            'map' => ['citizens' => $c],
            'tpl' => "СПРАВКА (форма №9)\n"
                . "\nЗарегистрирован(а): {{citizens.last_name}} {{citizens.first_name}} {{citizens.middle_name}}"
                . "\nДата рождения: {{citizens.birth_date}}"
                . "\nАдрес регистрации: {{citizens.reg_address}}"
                . "\nДата регистрации: {{citizens.reg_start_date}}"
                . "\n\nДокумент сформирован автоматически АИС «Паспортный стол». Данные взяты из БД без ручного ввода.",
        ];
    }

    if ($type === 'passport') {
        $st = $pdo->prepare('SELECT p.*, CONCAT(c.last_name," ",c.first_name," ",IFNULL(c.middle_name,"")) AS fio, c.birth_date
                             FROM passports p JOIN citizens c ON c.citizen_id = p.citizen_id WHERE p.passport_id = ?');
        $st->execute([$id]);
        $p = $st->fetch();
        if (!$p) throw new RuntimeException('Паспорт не найден.');
        return [
            'title' => 'Печатная форма: выдача паспорта', 'entity' => 'passports', 'entity_id' => $id,
            'map' => ['passports' => $p],
            'tpl' => "ПЕЧАТНАЯ ФОРМА — ВЫДАЧА ПАСПОРТА\n"
                . "\nФИО: {{passports.fio}}"
                . "\nДата рождения: {{passports.birth_date}}"
                . "\nСерия: {{passports.series}}   Номер: {{passports.number}}"
                . "\nДата выдачи: {{passports.issue_date}}"
                . "\nОрган выдачи: {{passports.issuer}}"
                . "\nОснование: {{passports.reason}}"
                . "\nСтатус: {{passports.status}}"
                . "\n\nДокумент сформирован АИС «Паспортный стол».",
        ];
    }

    if ($type === 'dereg') {
        $st = $pdo->prepare('SELECT * FROM citizens WHERE citizen_id = ?'); $st->execute([$id]);
        $c = $st->fetch();
        if (!$c) throw new RuntimeException('Гражданин не найден.');
        return [
            'title' => 'Уведомление о снятии с регистрационного учёта', 'entity' => 'citizens', 'entity_id' => $id,
            'map' => ['citizens' => $c],
            'tpl' => "УВЕДОМЛЕНИЕ О СНЯТИИ С РЕГИСТРАЦИОННОГО УЧЁТА\n"
                . "\n{{citizens.last_name}} {{citizens.first_name}} {{citizens.middle_name}} ({{citizens.birth_date}})"
                . "\nснят(а) с регистрационного учёта по адресу: {{citizens.reg_address}}"
                . "\nДата прекращения регистрации: {{citizens.reg_end_date}}"
                . "\n\nДокумент сформирован АИС «Паспортный стол».",
        ];
    }

    throw new RuntimeException('Неизвестный тип документа.');
}

function doc_render(array $d): string
{
    return preg_replace_callback('/\{\{(\w+)\.(\w+)\}\}/', function ($m) use ($d) {
        $v = $d['map'][$m[1]][$m[2]] ?? '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$v)) $v = date('d.m.Y', strtotime($v));
        return $v;
    }, $d['tpl']);
}

function doc_lines(array $d): array
{
    return explode("\n", doc_render($d));
}
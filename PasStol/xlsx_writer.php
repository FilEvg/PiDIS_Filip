<?php
// Генератор XLSX (Office Open XML) без внешних библиотек.
function xlsx_column_letter(int $zeroBasedIndex): string
{
    $letter = '';
    $n = $zeroBasedIndex + 1;
    while ($n > 0) {
        $mod = ($n - 1) % 26;
        $letter = chr(65 + $mod) . $letter;
        $n = intdiv($n - $mod, 26);
    }
    return $letter;
}
function xlsx_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}
function xlsx_is_number($value): bool
{
    return is_int($value) || is_float($value)
        || (is_string($value) && strlen($value) <= 15 && preg_match('/^-?(0|[1-9]\d*)(\.\d+)?$/', $value) === 1);
}
function xlsx_build_sheet_xml(array $header, array $rows): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    $rowIndex = 1;
    $writeRow = function (array $cells) use (&$rowIndex, &$xml) {
        $xml .= '<row r="' . $rowIndex . '">';
        $col = 0;
        foreach ($cells as $value) {
            $ref = xlsx_column_letter($col) . $rowIndex;
            if ($value === null || $value === '') { $col++; continue; }
            if (xlsx_is_number($value)) {
                $xml .= '<c r="' . $ref . '"><v>' . (string)$value . '</v></c>';
            } else {
                $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . xlsx_escape((string)$value) . '</t></is></c>';
            }
            $col++;
        }
        $xml .= '</row>';
        $rowIndex++;
    };
    $writeRow($header);
    foreach ($rows as $row) $writeRow(array_values($row));
    return $xml . '</sheetData></worksheet>';
}
// Сборка XLSX в память; null — если нет ZipArchive (тогда используйте CSV-вариант)
function xlsx_build(array $header, array $rows): ?string
{
    if (!class_exists('ZipArchive')) return null;
    $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Не удалось создать временный архив XLSX. Повторите попытку.');
    }
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '</Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Выгрузка" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '</Relationships>');
    $zip->addFromString('xl/worksheets/sheet1.xml', xlsx_build_sheet_xml($header, $rows));
    $zip->close();
    $data = file_get_contents($tmp);
    unlink($tmp);
    return $data === false ? null : $data;
}
// CSV с BOM и разделителем ";" — Excel открывает без потери кириллицы
function xlsx_build_csv_string(array $header, array $rows): string
{
    $out = "\xEF\xBB\xBF";
    $writeLine = function (array $cells) use (&$out) {
        $out .= implode(';', array_map(function ($v) {
            $v = (string)($v ?? '');
            return '"' . str_replace('"', '""', $v) . '"';
        }, $cells)) . "\r\n";
    };
    $writeLine($header);
    foreach ($rows as $row) $writeLine(array_values($row));
    return $out;
}
// Отдача файла браузеру (для совместимости)
function xlsx_send(string $fileName, array $header, array $rows): void
{
    $data = xlsx_build($header, $rows);
    if ($data === null) {
        $data = xlsx_build_csv_string($header, $rows);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . str_replace('.xlsx', '.csv', $fileName) . '"');
    } else {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
    }
    header('Content-Length: ' . (string)strlen($data));
    echo $data;
}
<?php
require __DIR__ . '/config.php';
require __DIR__ . '/captcha_lib.php';

$d = (int)($_GET['d'] ?? -1);
if ($d < 0 || $d > 3) { http_response_code(400); exit('Неверный индекс фрагмента'); }
if (empty($_SESSION['cap_frags'])) cap_generate();

header('Content-Type: image/png');
header('Cache-Control: no-store');
echo base64_decode($_SESSION['cap_frags'][$d]);
<?php
// Библиотека капчи «собери изображение из фрагментов» (Приложение 1)
// Фрагменты: 4 шт. (сетка 2x2). Правильный порядок: 1.png,2.png,3.png,4.png = верх-лево, верх-право, низ-лево, низ-право.

if (!function_exists('imagecreatetruecolor')) {
    http_response_code(500);
    exit('Не установлено расширение GD. В php.ini раскомментируйте строку: extension=gd');
}

define('CAP_FRAG_W', 160);
define('CAP_FRAG_H', 90);
define('CAP_PUZZLE_DIR', __DIR__ . '/assets/captcha/puzzle'); // готовые фрагменты 1.png..4.png
define('CAP_CUSTOM_DIR', __DIR__ . '/assets/captcha');        // целые картинки для автоматической нарезки

// Целое изображение 320x180: своя картинка или генерированный фон
function cap_whole_image()
{
    $files = [];
    if (is_dir(CAP_CUSTOM_DIR)) {
        foreach (scandir(CAP_CUSTOM_DIR) as $f) {
            if (preg_match('/\.(png|jpe?g|gif|webp)$/i', $f)) $files[] = CAP_CUSTOM_DIR . '/' . $f;
        }
    }
    if ($files) {
        $file = $files[array_rand($files)];
        $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $src  = null;
        if ($ext === 'png')                          $src = @imagecreatefrompng($file);
        elseif ($ext === 'jpg' || $ext === 'jpeg')   $src = @imagecreatefromjpeg($file);
        elseif ($ext === 'gif')                      $src = @imagecreatefromgif($file);
        elseif ($ext === 'webp' && function_exists('imagecreatefromwebp')) $src = @imagecreatefromwebp($file);
        if ($src) {
            $w = imagesx($src); $h = imagesy($src);
            $need = (CAP_FRAG_W * 2) / (CAP_FRAG_H * 2);
            if ($w / $h > $need) { $ch = $h; $cw = (int)($h * $need); }
            else                 { $cw = $w; $ch = (int)($w / $need); }
            $out = imagecreatetruecolor(CAP_FRAG_W * 2, CAP_FRAG_H * 2);
            imagecopyresampled($out, $src, 0, 0, (int)(($w - $cw) / 2), (int)(($h - $ch) / 2),
                CAP_FRAG_W * 2, CAP_FRAG_H * 2, $cw, $ch);
            imagedestroy($src);
            return $out;
        }
    }
    // запасной генерируемый фон
    $img = imagecreatetruecolor(CAP_FRAG_W * 2, CAP_FRAG_H * 2);
    $c1 = imagecolorallocate($img, random_int(120, 200), random_int(120, 200), random_int(140, 220));
    $c2 = imagecolorallocate($img, random_int(40, 120),  random_int(60, 140),  random_int(80, 180));
    for ($y = 0; $y < CAP_FRAG_H * 2; $y++) {
        $t = $y / (CAP_FRAG_H * 2);
        $col = imagecolorallocate($img,
            (int)((($c1 >> 16) & 255) * (1 - $t) + (($c2 >> 16) & 255) * $t),
            (int)((($c1 >> 8)  & 255) * (1 - $t) + (($c2 >> 8)  & 255) * $t),
            (int)( ($c1 & 255)        * (1 - $t) +  ($c2 & 255)        * $t));
        imageline($img, 0, $y, CAP_FRAG_W * 2, $y, $col);
    }
    for ($i = 0; $i < 16; $i++) {
        $col = imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255));
        $x = random_int(0, 319); $y = random_int(0, 179); $s = random_int(12, 70);
        random_int(0, 1)
            ? imagefilledrectangle($img, $x, $y, $x + $s, $y + $s, $col)
            : imagefilledellipse($img, $x, $y, $s, $s, $col);
    }
    for ($i = 0; $i < 10; $i++) {
        $col = imagecolorallocate($img, random_int(0, 255), random_int(0, 255), random_int(0, 255));
        imageline($img, random_int(0, 319), random_int(0, 179), random_int(0, 319), random_int(0, 179), $col);
    }
    return $img;
}

function cap_png_b64($img): string
{
    ob_start(); imagepng($img);
    $b64 = base64_encode(ob_get_clean());
    imagedestroy($img);
    return $b64;
}

// Сформировать новую головоломку: 4 перемешанных фрагмента + карта правильного порядка в сессии
function cap_generate(): void
{
    $correct = [];
    $ready = true;
    for ($i = 1; $i <= 4; $i++) {
        if (!is_file(CAP_PUZZLE_DIR . "/$i.png")) { $ready = false; break; }
    }
    if ($ready) {
        // готовые фрагменты (порядок файлов = правильный порядок сборки)
        for ($i = 1; $i <= 4; $i++) {
            $src = @imagecreatefrompng(CAP_PUZZLE_DIR . "/$i.png");
            if (!$src) { $ready = false; break; }
            $res = imagecreatetruecolor(CAP_FRAG_W, CAP_FRAG_H);
            imagecopyresampled($res, $src, 0, 0, 0, 0, CAP_FRAG_W, CAP_FRAG_H, imagesx($src), imagesy($src));
            imagedestroy($src);
            $correct[] = cap_png_b64($res);
        }
    }
    if (!$ready) {
        // нарезка целого изображения на 4 квадранта
        $whole = cap_whole_image();
        for ($r = 0; $r < 2; $r++) {
            for ($c = 0; $c < 2; $c++) {
                $fr = imagecrop($whole, ['x' => $c * CAP_FRAG_W, 'y' => $r * CAP_FRAG_H,
                                         'width' => CAP_FRAG_W, 'height' => CAP_FRAG_H]);
                $correct[] = cap_png_b64($fr);
            }
        }
        imagedestroy($whole);
    }

    // перемешанный порядок показа; cap_map[d] = правильный слот показанного фрагмента d
    $display = [0, 1, 2, 3];
    do { shuffle($display); } while ($display === [0, 1, 2, 3]);
    $_SESSION['cap_frags'] = array_map(function ($i) use ($correct) { return $correct[$i]; }, $display);
    $_SESSION['cap_map']   = $display;
}

// Есть ли в сессии свежие данные головоломки (иначе проверку считать некорректной)
function cap_is_fresh(): bool
{
    return !empty($_SESSION['cap_map']) && !empty($_SESSION['cap_frags']);
}

// Проверка раскладки: place[slot] = индекс показанного фрагмента (-1 = ячейка пуста)
function cap_verify_place($place): bool
{
    if (!is_array($place) || count($place) !== 4 || in_array(-1, $place, true)) return false;
    if (!cap_is_fresh()) return false;
    $used = [];
    foreach ($place as $slot => $displayIndex) {
        if (!is_int($displayIndex) || $displayIndex < 0 || $displayIndex > 3) return false;
        if ((int)($_SESSION['cap_map'][$displayIndex] ?? -1) !== (int)$slot) return false;
        $used[$displayIndex] = 1;
    }
    return count($used) === 4;
}
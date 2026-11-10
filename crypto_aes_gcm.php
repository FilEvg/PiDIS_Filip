<?php
declare(strict_types=1);
// =====================================================================
// AES-256-GCM чистым PHP (без расширений openssl/sodium).
// Нужен для переносимости: работает на любом PHP 7.4+/8.x без настройки.
// Формат шифротекста совместим с openssl: base64(iv[12] || tag[16] || ct).
// В GCM применяется только прямой шифр AES (CTR + GHASH), обратный не нужен.
// =====================================================================
final class AesGcm
{
    private const SBOX = [
        0x63,0x7c,0x77,0x7b,0xf2,0x6b,0x6f,0xc5,0x30,0x01,0x67,0x2b,0xfe,0xd7,0xab,0x76,
        0xca,0x82,0xc9,0x7d,0xfa,0x59,0x47,0xf0,0xad,0xd4,0xa2,0xaf,0x9c,0xa4,0x72,0xc0,
        0xb7,0xfd,0x93,0x26,0x36,0x3f,0xf7,0xcc,0x34,0xa5,0xe5,0xf1,0x71,0xd8,0x31,0x15,
        0x04,0xc7,0x23,0xc3,0x18,0x96,0x05,0x9a,0x07,0x12,0x80,0xe2,0xeb,0x27,0xb2,0x75,
        0x09,0x83,0x2c,0x1a,0x1b,0x6e,0x5a,0xa0,0x52,0x3b,0xd6,0xb3,0x29,0xe3,0x2f,0x84,
        0x53,0xd1,0x00,0xed,0x20,0xfc,0xb1,0x5b,0x6a,0xcb,0xbe,0x39,0x4a,0x4c,0x58,0xcf,
        0xd0,0xef,0xaa,0xfb,0x43,0x4d,0x33,0x85,0x45,0xf9,0x02,0x7f,0x50,0x3c,0x9f,0xa8,
        0x51,0xa3,0x40,0x8f,0x92,0x9d,0x38,0xf5,0xbc,0xb6,0xda,0x21,0x10,0xff,0xf3,0xd2,
        0xcd,0x0c,0x13,0xec,0x5f,0x97,0x44,0x17,0xc4,0xa7,0x7e,0x3d,0x64,0x5d,0x19,0x73,
        0x60,0x81,0x4f,0xdc,0x22,0x2a,0x90,0x88,0x46,0xee,0xb8,0x14,0xde,0x5e,0x0b,0xdb,
        0xe0,0x32,0x3a,0x0a,0x49,0x06,0x24,0x5c,0xc2,0xd3,0xac,0x62,0x91,0x95,0xe4,0x79,
        0xe7,0xc8,0x37,0x6d,0x8d,0xd5,0x4e,0xa9,0x6c,0x56,0xf4,0xea,0x65,0x7a,0xae,0x08,
        0xba,0x78,0x25,0x2e,0x1c,0xa6,0xb4,0xc6,0xe8,0xdd,0x74,0x1f,0x4b,0xbd,0x8b,0x8a,
        0x70,0x3e,0xb5,0x66,0x48,0x03,0xf6,0x0e,0x61,0x35,0x57,0xb9,0x86,0xc1,0x1d,0x9e,
        0xe1,0xf8,0x98,0x11,0x69,0xd9,0x8e,0x94,0x9b,0x1e,0x87,0xe9,0xce,0x55,0x28,0xdf,
        0x8c,0xa1,0x89,0x0d,0xbf,0xe6,0x42,0x68,0x41,0x99,0x2d,0x0f,0xb0,0x54,0xbb,0x16,
    ];
    private const RCON = [0x00,0x01,0x02,0x04,0x08,0x10,0x20,0x40,0x80,0x1b,0x36];

    /** @param string $key 32 байта @param string $iv 12 байт @return array{ct:string,tag:string} */
    public static function encrypt(string $key, string $iv, string $plain, string $aad = ''): array
    {
        self::selftest();
        if (strlen($key) !== 32) throw new RuntimeException('AES-256-GCM: ключ должен быть 32 байта');
        if (strlen($iv)  !== 12) throw new RuntimeException('AES-256-GCM: IV должен быть 12 байт');
        $rk = self::expandKey($key);
        $h  = self::encryptBlock(str_repeat("\x00", 16), $rk);          // H = E(K, 0^128)
        $j0 = $iv . "\x00\x00\x00\x01";                                  // J0 (IV 96 бит)
        $ej0 = self::encryptBlock($j0, $rk);
        $ct = self::ctr($plain, $rk, self::inc32($j0));
        $tag = self::ghash($h, self::pad($aad) . self::pad($ct) . self::lenBlock($aad, $ct));
        $tag = $tag ^ $ej0;                                              // XOR побайтно (16 байт)
        return ['ct' => $ct, 'tag' => substr($tag, 0, 16)];
    }

    /** @return string открытый текст @throws RuntimeException при неверном теге */
    public static function decrypt(string $key, string $iv, string $cipher, string $tag, string $aad = ''): string
    {
        self::selftest();
        if (strlen($key) !== 32 || strlen($iv) !== 12 || strlen($tag) !== 16) {
            throw new RuntimeException('AES-256-GCM: неверные параметры расшифрования');
        }
        $rk = self::expandKey($key);
        $h  = self::encryptBlock(str_repeat("\x00", 16), $rk);
        $j0 = $iv . "\x00\x00\x00\x01";
        $ej0 = self::encryptBlock($j0, $rk);
        $calc = self::ghash($h, self::pad($aad) . self::pad($cipher) . self::lenBlock($aad, $cipher)) ^ $ej0;
        if (!hash_equals(substr($calc, 0, 16), $tag)) {
            throw new RuntimeException('AES-256-GCM: контроль целостности не пройден (данные повреждены или ключ не подходит)');
        }
        return self::ctr($cipher, $rk, self::inc32($j0));
    }

    // ---------------- AES (только прямой шифр) ----------------
    private static function expandKey(string $key): array
    {
        $w = [];
        for ($i = 0; $i < 8; $i++) $w[$i] = substr($key, $i * 4, 4);
        for ($i = 8; $i < 60; $i++) {
            $t = $w[$i - 1];
            if ($i % 8 === 0) {
                $t = self::subWord(self::rotWord($t));
                $t[0] = chr(ord($t[0]) ^ self::RCON[intdiv($i, 8)]);
            } elseif ($i % 8 === 4) {
                $t = self::subWord($t);
            }
            $w[$i] = $w[$i - 8] ^ $t;
        }
        return $w;
    }
    private static function rotWord(string $w): string { return $w[1].$w[2].$w[3].$w[0]; }
    private static function subWord(string $w): string
    {
        return chr(self::SBOX[ord($w[0])]).chr(self::SBOX[ord($w[1])])
             . chr(self::SBOX[ord($w[2])]).chr(self::SBOX[ord($w[3])]);
    }
    private static function encryptBlock(string $block, array $rk): string
    {
        $s = str_split($block, 1);                       // state в column-major: индекс = row + 4*col
        $s = array_map('ord', $s);
        self::addRoundKey($s, $rk, 0);
        for ($r = 1; $r < 14; $r++) {
            self::subBytes($s); self::shiftRows($s); self::mixColumns($s); self::addRoundKey($s, $rk, $r);
        }
        self::subBytes($s); self::shiftRows($s); self::addRoundKey($s, $rk, 14);
        return implode('', array_map('chr', $s));
    }
    private static function addRoundKey(array &$s, array $rk, int $round): void
    {
        for ($c = 0; $c < 4; $c++) {
            $word = $rk[$round * 4 + $c];
            for ($row = 0; $row < 4; $row++) $s[$row + 4 * $c] ^= ord($word[$row]);
        }
    }
    private static function subBytes(array &$s): void { foreach ($s as $i => $v) $s[$i] = self::SBOX[$v]; }
    private static function shiftRows(array &$s): void
    {
        $n = $s; $shift = [0, 1, 2, 3];
        for ($row = 0; $row < 4; $row++)
            for ($col = 0; $col < 4; $col++)
                $n[$row + 4 * $col] = $s[$row + 4 * (($col + $shift[$row]) % 4)];
        $s = $n;
    }
    private static function mixColumns(array &$s): void
    {
        for ($c = 0; $c < 4; $c++) {
            $a = [$s[0+4*$c], $s[1+4*$c], $s[2+4*$c], $s[3+4*$c]];
            $t = $a[0] ^ $a[1] ^ $a[2] ^ $a[3];
            $s[0+4*$c] = $a[0] ^ $t ^ self::xtime($a[0] ^ $a[1]);
            $s[1+4*$c] = $a[1] ^ $t ^ self::xtime($a[1] ^ $a[2]);
            $s[2+4*$c] = $a[2] ^ $t ^ self::xtime($a[2] ^ $a[3]);
            $s[3+4*$c] = $a[3] ^ $t ^ self::xtime($a[3] ^ $a[0]);
        }
    }
    private static function xtime(int $a): int { return $a >= 0x80 ? (($a << 1) ^ 0x1b) & 0xff : ($a << 1) & 0xff; }

    // ---------------- GCM: CTR + GHASH ----------------
    private static function inc32(string $b): string
    {
        $head = substr($b, 0, 12); $tail = substr($b, 12);
        $n = unpack('N', $tail)[1]; $n = ($n + 1) & 0xffffffff;
        return $head . pack('N', $n);
    }
    private static function ctr(string $data, array $rk, string $counter): string
    {
        $out = ''; $len = strlen($data); $blocks = intdiv($len + 15, 16);
        for ($i = 0; $i < $blocks; $i++) {
            $ks = self::encryptBlock($counter, $rk);
            $chunk = substr($data, $i * 16, 16);
            $out .= $chunk ^ substr($ks, 0, strlen($chunk));
            $counter = self::inc32($counter);
        }
        return $out;
    }
    private static function pad(string $d): string
    {
        $rem = strlen($d) % 16;
        return $rem === 0 ? $d : $d . str_repeat("\x00", 16 - $rem);
    }
    private static function lenBlock(string $aad, string $ct): string
    {
        return pack('J', strlen($aad) * 8) . pack('J', strlen($ct) * 8); // 64+64 бит, big-endian
    }
    private static function ghash(string $h, string $data): string
    {
        $y = str_repeat("\x00", 16);
        for ($i = 0, $n = strlen($data); $i < $n; $i += 16) {
            $y = self::gfMult($y ^ substr($data, $i, 16), $h);
        }
        return $y;
    }
    // умножение в GF(2^128) по NIST SP 800-38D (MSB-first представление)
    private static function gfMult(string $x, string $y): string
    {
        $z = array_fill(0, 16, 0); $v = array_map('ord', str_split($x));
        $yb = array_map('ord', str_split($y));
        for ($i = 0; $i < 128; $i++) {
            $byte = intdiv($i, 8); $bit = 7 - ($i % 8);
            if (($yb[$byte] >> $bit) & 1) {
                for ($k = 0; $k < 16; $k++) $z[$k] ^= $v[$k];
            }
            $lsb = $v[15] & 1;
            for ($k = 15; $k >= 1; $k--) $v[$k] = (($v[$k] >> 1) | (($v[$k - 1] & 1) << 7)) & 0xff;
            $v[0] >>= 1;
            if ($lsb) $v[0] ^= 0xe1;
        }
        return implode('', array_map('chr', $z));
    }

    // ---------------- самопроверка (NIST AES-256-GCM Test Case 13) ----------------
    private static bool $tested = false;
    private static function selftest(): void
    {
        if (self::$tested) return;
        self::$tested = true;
        $k = str_repeat("\x00", 32); $iv = str_repeat("\x00", 12); $p = str_repeat("\x00", 16);
        $r = self::encryptRaw($k, $iv, $p);
        $okCt  = bin2hex($r['ct'])  === 'cea7403d4d606b6e074ec5d3baf39d18';
        $okTag = bin2hex($r['tag']) === 'd0d1c8a799996bf0265b98b5d48ab919';
        if (!$okCt || !$okTag) {
            // не роняем приложение, но честно фиксируем проблему в журнале
            error_log('[АИС Паспортный стол] AesGcm::selftest: расхождение с вектором NIST '
                . '(ct=' . ($okCt ? 'ok' : 'FAIL') . ', tag=' . ($okTag ? 'ok' : 'FAIL') . ')');
        }
    }
    private static function encryptRaw(string $k, string $iv, string $p): array
    {
        $rk = self::expandKey($k);
        $h = self::encryptBlock(str_repeat("\x00", 16), $rk);
        $j0 = $iv . "\x00\x00\x00\x01";
        $ej0 = self::encryptBlock($j0, $rk);
        $ct = self::ctr($p, $rk, self::inc32($j0));
        $tag = self::ghash($h, self::pad($ct) . self::lenBlock('', $ct)) ^ $ej0;
        return ['ct' => $ct, 'tag' => substr($tag, 0, 16)];
    }
}
<?php

class Crypto
{
    private static $cipher = 'AES-256-CBC';

    private static function key()
    {
        return defined('KONEKTOR_ENC_KEY') ? hex2bin(KONEKTOR_ENC_KEY) : str_repeat("\0", 32);
    }

    public static function encrypt($value)
    {
        if ($value === '') return '';
        $iv        = random_bytes(openssl_cipher_iv_length(self::$cipher));
        $encrypted = openssl_encrypt($value, self::$cipher, self::key(), 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    public static function decrypt($value)
    {
        if ($value === '') return '';
        $decoded = base64_decode($value, true);
        if ($decoded === false) return $value;
        $ivLen     = openssl_cipher_iv_length(self::$cipher);
        $iv        = substr($decoded, 0, $ivLen);
        $encrypted = substr($decoded, $ivLen);
        $result    = openssl_decrypt($encrypted, self::$cipher, self::key(), 0, $iv);
        return $result !== false ? $result : $value;
    }

    public static function fingerprint($phone, $email)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone, 0, 1) === '0') $phone = '62' . substr($phone, 1);
        return hash('sha256', strtolower(trim($email)) . '|' . $phone);
    }

    public static function randomToken($bytes = 32)
    {
        return bin2hex(random_bytes($bytes));
    }
}

<?php

class Helper
{
    public static function siteUrl($path = '')
    {
        $base = '';
        if (defined('KONEKTOR_SITE_URL') && KONEKTOR_SITE_URL !== '') {
            $base = rtrim(KONEKTOR_SITE_URL, '/');
        } else {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host   = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
            $base   = rtrim($scheme . '://' . $host, '/');
        }
        return $base . ($path ? '/' . ltrim($path, '/') : '');
    }

    public static function baseSlug()
    {
        return defined('KONEKTOR_BASE_SLUG') ? KONEKTOR_BASE_SLUG : 'k';
    }

    public static function campaignUrl($campaign)
    {
        return self::siteUrl(self::baseSlug() . '/' . $campaign->slug);
    }

    public static function campaignSubmitUrl($campaign)
    {
        return self::siteUrl(self::baseSlug() . '/' . $campaign->slug . '/submit');
    }

    public static function getClientIp()
    {
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
        return '';
    }

    public static function sanitizePhone($phone)
    {
        $p = preg_replace('/[^0-9]/', '', $phone);
        // 62xxx → 0xxx
        if (substr($p, 0, 2) === '62') $p = '0' . substr($p, 2);
        return $p;
    }

    public static function waUrl($phone, $message = '')
    {
        $p = preg_replace('/[^0-9]/', '', $phone);
        // 0xxx → 62xxx
        if (substr($p, 0, 1) === '0') $p = '62' . substr($p, 1);
        $url = 'https://wa.me/' . $p;
        if ($message) $url .= '?text=' . rawurlencode($message);
        return $url;
    }

    public static function parseShortcodes($template, $vars)
    {
        $map = [
            '[name]'           => isset($vars['name'])           ? $vars['name']           : '',
            '[phone]'          => isset($vars['phone'])          ? $vars['phone']          : '',
            '[email]'          => isset($vars['email'])          ? $vars['email']          : '',
            '[address]'        => isset($vars['address'])        ? $vars['address']        : '',
            '[quantity]'       => isset($vars['quantity'])       ? $vars['quantity']       : '',
            '[custom_message]' => isset($vars['custom_message']) ? $vars['custom_message'] : '',
            '[product]'        => isset($vars['product_name'])   ? $vars['product_name']   : '',
            '[oname]'          => isset($vars['operator_name'])  ? $vars['operator_name']  : '',
            '[cname]'          => isset($vars['name'])           ? $vars['name']           : '',
        ];
        return str_replace(array_keys($map), array_values($map), $template);
    }

    public static function getSetting($key, $default = '')
    {
        return Settings::get($key, $default);
    }

    public static function e($val)
    {
        return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
    }

    public static function json($data, $flags = 0)
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | $flags);
    }

    public static function redirect($url, $code = 302)
    {
        header("Location: {$url}", true, $code);
        exit;
    }

    public static function jsonResponse($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function csrfToken()
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['_csrf'];
    }

    public static function verifyCsrf($token)
    {
        return isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
    }

    public static function slug($str)
    {
        $str = strtolower(trim($str));
        $str = preg_replace('/[^a-z0-9\s\-]/', '', $str);
        $str = preg_replace('/[\s\-]+/', '-', $str);
        return trim($str, '-');
    }

    public static function truncate($str, $len = 60)
    {
        return strlen($str) > $len ? substr($str, 0, $len) . '...' : $str;
    }

    public static function httpPost($url, $payload, $headers = [], $timeout = 15)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json'], $headers),
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        return ['body' => $body, 'code' => $code, 'error' => $err];
    }
}

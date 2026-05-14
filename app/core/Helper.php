<?php

class Helper
{
    public static function siteUrl($path = '')
    {
        $base = '';
        if (defined('KONEKTOR_SITE_URL') && KONEKTOR_SITE_URL !== '') {
            $base = rtrim(KONEKTOR_SITE_URL, '/');
            // Force HTTPS if current request is HTTPS (fixes Mixed Content behind proxy)
            if (self::isHttps() && strpos($base, 'http:') === 0) {
                $base = preg_replace('#^http:#', 'https:', $base, 1);
            }
        } else {
            $scheme = self::isHttps() ? 'https' : 'http';
            $host   = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
            $base   = rtrim($scheme . '://' . $host, '/');
        }
        return $base . ($path ? '/' . ltrim($path, '/') : '');
    }

    public static function isHttps()
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') return true;
        if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') return true;
        if (!empty($_SERVER['HTTP_CLOUDFRONT_FORWARDED_PROTO']) && $_SERVER['HTTP_CLOUDFRONT_FORWARDED_PROTO'] === 'https') return true;
        if (!empty($_SERVER['HTTP_CF_VISITOR'])) {
            $cf = json_decode($_SERVER['HTTP_CF_VISITOR'], true);
            if (!empty($cf['scheme']) && $cf['scheme'] === 'https') return true;
        }
        return false;
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

    /**
     * Simple rate limiter using APCu (preferred) or session fallback.
     * Returns true if the action is allowed, false if rate limit exceeded.
     *
     * @param string $key    Unique key (e.g. "submit_{ip}")
     * @param int    $limit  Max allowed hits in the window
     * @param int    $window Window in seconds
     */
    public static function rateLimit($key, $limit = 10, $window = 60)
    {
        $cacheKey = 'knk_rl_' . md5($key);

        if (function_exists('apcu_fetch')) {
            $count = apcu_fetch($cacheKey, $success);
            if (!$success) {
                apcu_store($cacheKey, 1, $window);
                return true;
            }
            if ($count >= $limit) return false;
            apcu_inc($cacheKey);
            return true;
        }

        // Session fallback (shared server safe enough for basic protection)
        if (session_status() !== PHP_SESSION_ACTIVE) return true;
        $now = time();
        if (!isset($_SESSION[$cacheKey]) || ($now - $_SESSION[$cacheKey]['t']) >= $window) {
            $_SESSION[$cacheKey] = ['t' => $now, 'n' => 1];
            return true;
        }
        if ($_SESSION[$cacheKey]['n'] >= $limit) return false;
        $_SESSION[$cacheKey]['n']++;
        return true;
    }

    /**
     * Detect automated bots/crawlers that should not create leads.
     * Covers: social media link previewers, search engine bots, monitoring agents.
     */
    public static function isBotRequest()
    {
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        if ($ua === '') return true; // no UA = likely bot/script

        $botPatterns = [
            'facebookexternalhit', 'Facebot',          // Meta/Facebook preview
            'Twitterbot',                               // Twitter/X card preview
            'LinkedInBot',                              // LinkedIn preview
            'WhatsApp',                                 // WhatsApp link preview
            'TelegramBot',                              // Telegram preview
            'Discordbot',                               // Discord preview
            'Slackbot', 'slack-imgproxy',               // Slack preview
            'Pinterest',                                // Pinterest crawler
            'Googlebot', 'Googlebot-Image',             // Google search
            'bingbot', 'BingPreview',                   // Bing
            'DuckDuckBot',                              // DuckDuck Go
            'YandexBot',                                // Yandex
            'Baiduspider',                              // Baidu
            'ia_archiver',                              // Alexa/Wayback Machine
            'AhrefsBot', 'SemrushBot', 'MJ12bot',      // SEO tools
            'Screaming Frog', 'SiteAuditBot',          // Audit crawlers
            'Bytespider',                               // ByteDance/TikTok crawler
            'HeadlessChrome', 'PhantomJS',              // Headless browsers
            'python-requests', 'Go-http-client',        // Script crawlers
            'curl/', 'Wget/',                           // CLI tools
        ];

        foreach ($botPatterns as $pattern) {
            if (stripos($ua, $pattern) !== false) return true;
        }

        return false;
    }

    public static function fireWebhook($event, $data)
    {
        $url    = Settings::get('webhook_url');
        $secret = Settings::get('webhook_secret');
        if (!$url) return;

        $payload = json_encode(array_merge(['event' => $event, 'timestamp' => time()], $data), JSON_UNESCAPED_UNICODE);
        $sig     = hash_hmac('sha256', $payload, $secret ?: '');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Konektor-Signature: sha256=' . $sig,
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Fire server-side CAPI events when a lead status changes.
     * Reads status_events mapping from campaign pixel_config.
     * Supports per-platform event names: { "contacted": { "meta": "Contact", "tiktok": "Contact" } }
     * or flat mapping: { "contacted": "Contact" } (same event for all platforms).
     */
    public static function fireStatusEvent($leadId, $status)
    {
        $lead = Lead::find($leadId);
        if (!$lead) return;

        $campaign = Campaign::find($lead->campaign_id);
        if (!$campaign) return;

        $pixel = Campaign::decodeJsonField($campaign->pixel_config ?? null);
        $statusEvents = isset($pixel['status_events']) ? $pixel['status_events'] : [];

        $mapping = isset($statusEvents[$status]) ? $statusEvents[$status] : [];
        if (empty($mapping)) return;

        // Flat string mapping → convert to per-platform
        if (is_string($mapping)) {
            $mapping = ['meta' => $mapping, 'tiktok' => $mapping, 'snack' => $mapping];
        }

        // Backward compatibility for purchased/cancelled if no mapping
        if (empty($mapping['meta']) && in_array($status, ['purchased', 'cancelled'], true)) {
            $metaCfg = MetaApi::getConfig($campaign);
            $mapping['meta'] = $status === 'purchased'
                ? ($metaCfg['thanks_page_event'] ?? '')
                : ($metaCfg['form_submit_event'] ?? '');
        }

        $leadData = Lead::decrypt(clone $lead);
        $leadArr = [
            'source_url'   => $lead->source_url ?? '',
            'referrer'     => $lead->referrer ?? '',
            'name'         => $leadData->name ?? '',
            'email'        => $leadData->email ?? '',
            'phone'        => $leadData->phone ?? '',
            'product_name' => $campaign->product_name ?? '',
        ];

        $metaCfg   = MetaApi::getConfig($campaign);
        $tiktokCfg = TiktokApi::getConfig($campaign);
        $snackCfg  = SnackApi::getConfig($campaign);

        if (!empty($mapping['meta']) && !empty($metaCfg['token'])) {
            MetaApi::sendEvent($mapping['meta'], $leadArr, $metaCfg);
        }
        if (!empty($mapping['tiktok']) && !empty($tiktokCfg['pixel_id']) && !empty($tiktokCfg['access_token'])) {
            TiktokApi::sendEvent($mapping['tiktok'], $leadArr, $tiktokCfg);
        }
        if (!empty($mapping['snack']) && !empty($snackCfg['pixel_id']) && !empty($snackCfg['access_token'])) {
            SnackApi::sendEvent($mapping['snack'], $leadArr, $snackCfg);
        }
    }
}

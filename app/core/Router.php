<?php

/**
 * Simple front-controller router.
 * Entry point: public/index.php
 */
class Router
{
    private static $routes = [];

    public static function add($method, $pattern, $handler)
    {
        self::$routes[] = compact('method', 'pattern', 'handler');
    }

    public static function dispatch()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
        $uri    = '/' . trim($uri, '/');

        // Handle CORS preflight early — before campaign lookup or any output.
        // Embed forms (cross-origin) send OPTIONS before POST submit/pixel.
        if ($method === 'OPTIONS') {
            $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
            if ($origin) {
                header('Access-Control-Allow-Origin: ' . $origin);
                header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
                header('Access-Control-Allow-Headers: Content-Type');
                header('Access-Control-Allow-Credentials: true');
                header('Access-Control-Max-Age: 86400');
                header('Vary: Origin');
            }
            http_response_code(204);
            return;
        }

        foreach (self::$routes as $route) {
            if ($route['method'] !== $method && $route['method'] !== 'ANY') continue;

            // {path} is a greedy wildcard — matches slashes too
            $pattern = preg_replace('#\{path\}#', '(?P<path>.+)', $route['pattern']);
            // other {param} — no slashes
            $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
            if (preg_match($regex, $uri, $m)) {
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                call_user_func($route['handler'], $params);
                return;
            }
        }

        http_response_code(404);
        include KONEKTOR_ROOT . '/public/404.php';
    }

    public static function registerPublicRoutes()
    {
        $base   = Helper::baseSlug();
        $csSlug = Settings::get('cs_panel_slug', 'cs-panel');

        self::add('GET',    "/{$base}/{slug}",           ['PublicController', 'form']);
        self::add('POST',   "/{$base}/{slug}/submit",    ['PublicController', 'submit']);
        self::add('GET',    "/{$base}/{slug}/thanks",    ['PublicController', 'thanks']);
        self::add('GET',    "/{$base}/{slug}/pixel",     ['PublicController', 'pixel']);
        // Demo route — same base slug, /preview sub-path, no tracking
        self::add('GET',    "/{$base}/{slug}/preview",   ['PublicController', 'demo']);
        self::add('GET',    "/{$csSlug}/{token}",            ['CsController',     'panel']);
        self::add('POST',   "/{$csSlug}/{token}/update",   ['CsController',     'updateLead']);
        self::add('POST',   "/{$csSlug}/{token}/followup", ['CsController',     'followUp']);
        self::add('POST',   "/{$csSlug}/{token}/block",    ['CsController',     'blockLead']);
        self::add('POST',   "/{$csSlug}/{token}/unblock",  ['CsController',     'unblockLead']);
        self::add('GET',    '/api/{path}',               ['ApiController',    'handle']);
        self::add('POST',   '/api/{path}',               ['ApiController',    'handle']);
        self::add('PUT',    '/api/{path}',               ['ApiController',    'handle']);
        self::add('DELETE', '/api/{path}',               ['ApiController',    'handle']);
        // Telegram webhook callback
        self::add('POST',   '/telegram/callback',        ['TelegramApi', 'handleWebhook']);
    }
}

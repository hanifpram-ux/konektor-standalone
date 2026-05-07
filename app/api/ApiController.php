<?php

/**
 * REST API — PHP 7.1+ compatible.
 */
class ApiController
{
    public static function handle($params)
    {
        header('Content-Type: application/json; charset=utf-8');
        // Admin API: same-origin only — no CORS wildcard. Browser admin panel and
        // public pixel endpoint use dedicated routes that set their own CORS.
        header('X-Content-Type-Options: nosniff');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); return; }

        $path = trim(isset($params['path']) ? $params['path'] : '', '/');

        if ($path === 'auth/login')  { self::authLogin();  return; }
        if ($path === 'auth/logout') { self::authLogout(); return; }

        if (!Auth::check()) {
            $bearer = self::getBearerToken();
            if (!$bearer || !self::validateBearer($bearer)) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                return;
            }
        }

        $method = $_SERVER['REQUEST_METHOD'];
        $raw    = file_get_contents('php://input');
        $body   = $raw ? (json_decode($raw, true) ?: []) : [];

        // Route dispatch — PHP 7.1 compatible (no match expression)
        $m = [];

        if ($path === 'dashboard') {
            self::dashboard();
        } elseif ($path === 'auth/me') {
            self::authMe();

        // Campaigns
        } elseif ($path === 'campaigns' && $method === 'GET') {
            self::campaignList();
        } elseif ($path === 'campaigns' && $method === 'POST') {
            self::campaignCreate($body);
        } elseif (preg_match('#^campaigns/(\d+)$#', $path, $m) && $method === 'GET') {
            self::campaignGet((int)$m[1]);
        } elseif (preg_match('#^campaigns/(\d+)$#', $path, $m) && $method === 'PUT') {
            self::campaignUpdate((int)$m[1], $body);
        } elseif (preg_match('#^campaigns/(\d+)$#', $path, $m) && $method === 'DELETE') {
            self::campaignDelete((int)$m[1]);

        // Operators
        } elseif ($path === 'operators' && $method === 'GET') {
            self::operatorList();
        } elseif ($path === 'operators' && $method === 'POST') {
            self::operatorCreate($body);
        } elseif (preg_match('#^operators/(\d+)/token$#', $path, $m)) {
            self::operatorToken((int)$m[1]);
        } elseif (preg_match('#^operators/(\d+)$#', $path, $m) && $method === 'GET') {
            self::operatorGet((int)$m[1]);
        } elseif (preg_match('#^operators/(\d+)$#', $path, $m) && $method === 'PUT') {
            self::operatorUpdate((int)$m[1], $body);
        } elseif (preg_match('#^operators/(\d+)$#', $path, $m) && $method === 'DELETE') {
            self::operatorDelete((int)$m[1]);

        // Leads
        } elseif ($path === 'leads/export') {
            self::leadExport();
        } elseif ($path === 'leads' && $method === 'GET') {
            self::leadList();
        } elseif (preg_match('#^leads/(\d+)/block$#', $path, $m)) {
            self::leadBlock((int)$m[1], $body);
        } elseif (preg_match('#^leads/(\d+)$#', $path, $m) && $method === 'GET') {
            self::leadGet((int)$m[1]);
        } elseif (preg_match('#^leads/(\d+)$#', $path, $m) && $method === 'PUT') {
            self::leadUpdate((int)$m[1], $body);
        } elseif (preg_match('#^leads/(\d+)$#', $path, $m) && $method === 'DELETE') {
            self::leadDelete((int)$m[1]);

        // Blocked
        } elseif ($path === 'blocked' && $method === 'GET') {
            self::blockedList();
        } elseif (preg_match('#^blocked/(\d+)$#', $path, $m) && $method === 'DELETE') {
            self::blockedDelete((int)$m[1]);

        // Analytics
        } elseif ($path === 'analytics') {
            self::analytics();
        } elseif (preg_match('#^analytics/campaign/(\d+)$#', $path, $m)) {
            self::campaignAnalytics((int)$m[1]);

        // Logs & settings
        } elseif ($path === 'logs' && $method === 'GET') {
            self::apiLogs();
        } elseif ($path === 'settings' && $method === 'GET') {
            self::settingsGet();
        } elseif ($path === 'settings' && $method === 'POST') {
            self::settingsSave($body);
        } elseif ($path === 'pixel-test' && $method === 'POST') {
            self::pixelTest($body);
        } else {
            self::notFound();
        }
    }

    private static function authLogin()
    {
        // Rate limit: 10 login attempts per IP per 5 minutes
        $ip = Helper::getClientIp();
        if (!Helper::rateLimit('login_' . $ip, 10, 300)) {
            http_response_code(429);
            echo json_encode(['success' => false, 'message' => 'Terlalu banyak percobaan login. Coba lagi dalam 5 menit.']);
            return;
        }

        $raw  = stream_get_contents(fopen('php://input', 'r'), 4096);
        $body = $raw ? (json_decode($raw, true) ?: []) : [];
        $username = substr(isset($body['username']) ? (string)$body['username'] : '', 0, 200);
        $password = substr(isset($body['password']) ? (string)$body['password'] : '', 0, 1024);

        $ok = Auth::attempt($username, $password);
        if ($ok) {
            session_regenerate_id(true);
            echo json_encode(['success' => true, 'user' => Auth::user()]);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Username atau password salah.']);
        }
    }

    private static function authLogout()
    {
        Auth::logout();
        echo json_encode(['success' => true]);
    }

    private static function authMe()
    {
        echo json_encode(['success' => true, 'user' => Auth::user()]);
    }

    private static function dashboard()
    {
        $stats  = Analytics::getOverallStats();
        $daily  = Analytics::getDailyLeads(0, 30);
        $opStat = Analytics::getOperatorStats();
        echo json_encode(['success' => true, 'stats' => $stats, 'daily' => $daily, 'operators' => $opStat]);
    }

    private static function analytics()
    {
        $days  = (int)(isset($_GET['days']) ? $_GET['days'] : 30);
        $daily = Analytics::getDailyLeads(0, $days);
        $stats = Analytics::getOverallStats();
        echo json_encode(['success' => true, 'daily' => $daily, 'stats' => $stats]);
    }

    private static function campaignAnalytics($id)
    {
        $days  = (int)(isset($_GET['days']) ? $_GET['days'] : 30);
        $stats = Analytics::getCampaignStats($id, $days);
        $daily = Analytics::getDailyLeads($id, $days);
        echo json_encode(['success' => true, 'stats' => $stats, 'daily' => $daily]);
    }

    private static function campaignList()
    {
        $campaigns = Campaign::all();
        foreach ($campaigns as $c) {
            $c->url = Helper::campaignUrl($c);
        }
        echo json_encode(['success' => true, 'data' => $campaigns]);
    }

    private static function campaignGet($id)
    {
        $c = Campaign::find($id);
        if (!$c) { self::notFound(); return; }
        $c->url       = Helper::campaignUrl($c);
        $c->operators = Campaign::getOperators($id);
        echo json_encode(['success' => true, 'data' => $c]);
    }

    private static function campaignCreate($data)
    {
        $id = Campaign::save($data);
        echo json_encode(['success' => true, 'id' => $id]);
    }

    private static function campaignUpdate($id, $data)
    {
        Campaign::save($data, $id);
        echo json_encode(['success' => true]);
    }

    private static function campaignDelete($id)
    {
        Campaign::delete($id);
        echo json_encode(['success' => true]);
    }

    private static function operatorList()
    {
        $ops = Operator::all();
        echo json_encode(['success' => true, 'data' => $ops]);
    }

    private static function operatorGet($id)
    {
        $op = Operator::find($id);
        if (!$op) { self::notFound(); return; }
        echo json_encode(['success' => true, 'data' => $op]);
    }

    private static function operatorCreate($data)
    {
        $id = Operator::save($data);
        echo json_encode(['success' => true, 'id' => $id]);
    }

    private static function operatorUpdate($id, $data)
    {
        Operator::save($data, $id);
        echo json_encode(['success' => true]);
    }

    private static function operatorDelete($id)
    {
        Operator::delete($id);
        echo json_encode(['success' => true]);
    }

    private static function operatorToken($id)
    {
        $csSlug = Settings::get('cs_panel_slug', 'cs-panel');
        $token  = Operator::getToken($id);
        $url    = Helper::siteUrl($csSlug . '/' . $token);
        echo json_encode(['success' => true, 'token' => $token, 'url' => $url]);
    }

    private static function leadList()
    {
        $campId = (int)(isset($_GET['campaign_id']) ? $_GET['campaign_id'] : 0);
        $opId   = (int)(isset($_GET['operator_id']) ? $_GET['operator_id'] : 0);
        $args   = array_filter([
            'campaign_id' => $campId ?: null,
            'operator_id' => $opId   ?: null,
            'status'      => isset($_GET['status']) ? $_GET['status'] : '',
            'is_double'   => isset($_GET['is_double']) ? (int)$_GET['is_double'] : null,
            'search'      => isset($_GET['search'])    ? $_GET['search']          : '',
            'page'        => (int)(isset($_GET['page'])     ? $_GET['page']     : 1),
            'per_page'    => min(100, (int)(isset($_GET['per_page']) ? $_GET['per_page'] : 50)),
        ], function($v) { return $v !== null && $v !== ''; });

        $leads = Lead::all($args);
        $total = Lead::count($args);
        $leads = array_map(function($l) { return Lead::decrypt($l); }, $leads);
        echo json_encode(['success' => true, 'data' => $leads, 'total' => $total]);
    }

    private static function leadGet($id)
    {
        $lead = Lead::find($id);
        if (!$lead) { self::notFound(); return; }
        $lead = Lead::decrypt($lead);
        echo json_encode(['success' => true, 'data' => $lead]);
    }

    private static function leadUpdate($id, $data)
    {
        $ok = Lead::updateStatus($id, isset($data['status']) ? $data['status'] : '', isset($data['note']) ? $data['note'] : '');
        echo json_encode(['success' => $ok]);
    }

    private static function leadDelete($id)
    {
        Lead::delete($id);
        echo json_encode(['success' => true]);
    }

    private static function leadBlock($id, $data)
    {
        $lead = Lead::find($id);
        if (!$lead) { self::notFound(); return; }
        $decr = Lead::decrypt(clone $lead);
        Blocker::block([
            'campaign_id' => $lead->campaign_id,
            'ip_address'  => $lead->ip_address,
            'fingerprint' => $lead->fingerprint,
            'cookie_id'   => $lead->cookie_id,
            'phone'       => $decr->phone,
            'email'       => $decr->email,
            'reason'      => isset($data['reason']) ? $data['reason'] : '',
            'blocked_by'  => Auth::id(),
        ]);
        Lead::updateStatus($id, 'blocked');
        echo json_encode(['success' => true]);
    }

    private static function leadExport()
    {
        $campId = (int)(isset($_GET['campaign_id']) ? $_GET['campaign_id'] : 0);
        $args   = array_filter([
            'campaign_id' => $campId ?: null,
            'status'      => isset($_GET['status']) ? $_GET['status'] : '',
        ]);
        $rows = Lead::exportCsv($args);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="leads-' . date('Ymd-His') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        foreach ($rows as $row) fputcsv($out, $row);
        fclose($out);
        exit;
    }

    private static function blockedList()
    {
        $cid   = (int)(isset($_GET['campaign_id']) ? $_GET['campaign_id'] : 0) ?: null;
        $page  = max(1, (int)(isset($_GET['page']) ? $_GET['page'] : 1));
        $limit = 50;
        $data  = Blocker::all($cid, $limit, ($page - 1) * $limit);
        $total = Blocker::count($cid);
        echo json_encode(['success' => true, 'data' => $data, 'total' => $total]);
    }

    private static function blockedDelete($id)
    {
        Blocker::unblock($id);
        echo json_encode(['success' => true]);
    }

    private static function apiLogs()
    {
        $page  = max(1, (int)(isset($_GET['page']) ? $_GET['page'] : 1));
        $limit = 50;
        $logs  = Logger::getLogs($limit, ($page - 1) * $limit);
        $total = Logger::countLogs();
        echo json_encode(['success' => true, 'data' => $logs, 'total' => $total]);
    }

    private static function settingsGet()
    {
        $all = Settings::all();
        echo json_encode(['success' => true, 'data' => $all]);
    }

    private static function settingsSave($body)
    {
        $allowed = ['telegram_bot_token','cs_panel_slug','encrypt_lead_data','app_name'];
        foreach ($allowed as $k) {
            if (isset($body[$k])) Settings::set($k, strip_tags(trim($body[$k])));
        }
        echo json_encode(['success' => true]);
    }

    private static function pixelTest($body)
    {
        $campaignId = (int)(isset($body['campaign_id']) ? $body['campaign_id'] : 0);
        $eventType  = isset($body['event_type']) ? $body['event_type'] : 'form_submit';
        $platforms  = isset($body['platforms']) && is_array($body['platforms']) ? $body['platforms'] : [];
        $lead       = isset($body['lead']) && is_array($body['lead']) ? $body['lead'] : [];

        $allowed = ['page_load','form_submit','thanks_page'];
        if (!$campaignId || !in_array($eventType, $allowed)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Parameter tidak valid.']);
            return;
        }

        $campaign = Campaign::find($campaignId);
        if (!$campaign) { self::notFound(); return; }

        $leadData = [
            'name'         => strip_tags(isset($lead['name'])       ? $lead['name']       : 'Test User'),
            'phone'        => Helper::sanitizePhone(isset($lead['phone']) ? $lead['phone'] : '081234567890'),
            'email'        => filter_var(isset($lead['email'])      ? $lead['email']       : 'test@example.com', FILTER_SANITIZE_EMAIL),
            'source_url'   => strip_tags(isset($lead['source_url']) ? $lead['source_url'] : Helper::siteUrl()),
            'product_name' => isset($campaign->product_name) ? $campaign->product_name : '',
            'ip'           => Helper::getClientIp(),
            'user_agent'   => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'referrer'     => '',
            'click_id'     => '',
        ];

        $results = [];

        foreach ($platforms as $plat) {
            switch ($plat) {
                case 'meta':
                    $cfg = MetaApi::getConfig($campaign);
                    if (empty($cfg['pixel_id']) || empty($cfg['token'])) {
                        $results['meta'] = ['success' => false, 'message' => 'Meta: Pixel ID atau Token tidak dikonfigurasi.', 'response' => null];
                        break;
                    }
                    $eventName = $cfg[$eventType . '_event'] ?? '';
                    $resp = MetaApi::sendEvent($eventName, $leadData, $cfg);
                    $code = isset($resp['code']) ? (int)$resp['code'] : 0;
                    $results['meta'] = [
                        'success'  => ($code >= 200 && $code < 300),
                        'message'  => "Meta CAPI — Event: {$eventName} — HTTP {$code}",
                        'response' => isset($resp['body']) ? (is_string($resp['body']) ? json_decode($resp['body'], true) : $resp['body']) : null,
                    ];
                    break;

                case 'tiktok':
                    $cfg = TiktokApi::getConfig($campaign);
                    if (empty($cfg['pixel_id']) || empty($cfg['access_token'])) {
                        $results['tiktok'] = ['success' => false, 'message' => 'TikTok: Pixel ID atau Access Token tidak dikonfigurasi.', 'response' => null];
                        break;
                    }
                    $resp = TiktokApi::sendEvent($eventType, $leadData, $cfg);
                    $code = isset($resp['code']) ? (int)$resp['code'] : 0;
                    $body2 = isset($resp['body']) ? (is_string($resp['body']) ? json_decode($resp['body'], true) : $resp['body']) : null;
                    $ok    = ($code >= 200 && $code < 300) && (isset($body2['code']) ? $body2['code'] == 0 : true);
                    $results['tiktok'] = [
                        'success'  => $ok,
                        'message'  => "TikTok Events API — HTTP {$code}" . ($body2 && isset($body2['message']) ? " — {$body2['message']}" : ''),
                        'response' => $body2,
                    ];
                    break;

                case 'snack':
                    $cfg = SnackApi::getConfig($campaign);
                    if (empty($cfg['pixel_id']) || empty($cfg['access_token'])) {
                        $results['snack'] = ['success' => false, 'message' => 'SnackVideo: Pixel ID atau Access Token tidak dikonfigurasi.', 'response' => null];
                        break;
                    }
                    $resp = SnackApi::sendEvent($eventType, $leadData, $cfg);
                    $code = isset($resp['code']) ? (int)$resp['code'] : 0;
                    $results['snack'] = [
                        'success'  => ($code >= 200 && $code < 300),
                        'message'  => "SnackVideo/Kwai API — HTTP {$code}",
                        'response' => isset($resp['body']) ? (is_string($resp['body']) ? json_decode($resp['body'], true) : $resp['body']) : null,
                    ];
                    break;
            }
        }

        echo json_encode(['success' => true, 'results' => $results]);
    }

    private static function notFound()
    {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Not found']);
    }

    private static function getBearerToken()
    {
        $auth = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
        if (strpos($auth, 'Bearer ') === 0) return substr($auth, 7);
        return null;
    }

    private static function validateBearer($token)
    {
        return false;
    }
}

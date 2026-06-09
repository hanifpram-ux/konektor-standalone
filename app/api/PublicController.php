<?php

class PublicController
{
    // ─── CORS helper for embed endpoints ────────────────────────────────────
    // Called on every submit/pixel response so the browser accepts the reply.
    // Preflight (OPTIONS) is handled earlier by Router::dispatch() before any
    // campaign lookup — so this only runs for the actual POST/GET.
    private static function setCorsHeaders($campaign)
    {
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        if (!$origin) return;

        $allowed = json_decode(isset($campaign->allowed_domains) ? $campaign->allowed_domains : '[]', true);

        // Empty whitelist = allow any origin (most common embed use-case)
        $ok = empty($allowed);
        if (!$ok) {
            $host = parse_url($origin, PHP_URL_HOST);
            foreach ($allowed as $d) {
                $d = trim($d);
                if ($host === $d || substr($host, -(strlen($d) + 1)) === '.' . $d) {
                    $ok = true;
                    break;
                }
            }
        }

        if ($ok) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Vary: Origin');
        }
    }

    // ─── Public pages ────────────────────────────────────────────────────────

    public static function form($params)
    {
        $campaign = Campaign::findBySlug(isset($params['slug']) ? $params['slug'] : '');
        if (!$campaign) { http_response_code(404); include KONEKTOR_ROOT . '/public/404.php'; return; }

        // Block bots from logging page_load events and creating wa_link leads
        if (Helper::isBotRequest()) { http_response_code(204); return; }

        // Domain whitelist check
        $allowed = json_decode(isset($campaign->allowed_domains) ? $campaign->allowed_domains : '[]', true);
        if (!empty($allowed) && !in_array(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '', $allowed)) {
            http_response_code(403); echo 'Access denied.'; return;
        }

        $ip  = Helper::getClientIp();
        $vid = isset($_COOKIE['konektor_vid']) ? $_COOKIE['konektor_vid'] : '';
        if (Blocker::isBlocked(null, $ip, '', $vid)) {
            include KONEKTOR_ROOT . '/public/blocked.php'; return;
        }

        Analytics::logEvent($campaign->id, 'page_load');

        if ($campaign->type === 'wa_link') {
            // Link campaigns: skip landing page, go straight to thanks
            $vid = isset($_COOKIE['konektor_vid']) ? $_COOKIE['konektor_vid'] : '';
            // _src: prefer param injected by embed JS (window.location.href of landing page),
            // fallback to HTTP_REFERER (for direct link clicks from external pages)
            $src = isset($_GET['_src']) && $_GET['_src'] !== ''
                ? $_GET['_src']
                : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
            // _ref: prefer explicit param from embed JS (carries real traffic source like facebook.com)
            // over HTTP_REFERER which would just be the landing page domain
            $ref = isset($_GET['_ref']) && $_GET['_ref'] !== ''
                ? $_GET['_ref']
                : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
            $qs  = http_build_query(array_filter([
                '_vid'     => $vid,
                '_src'     => $src,
                '_ref'     => $ref,
                'click_id' => isset($_GET['click_id']) ? $_GET['click_id'] : (isset($_GET['clickid']) ? $_GET['clickid'] : ''),
            ]));
            header('Location: ' . Helper::campaignUrl($campaign) . '/thanks' . ($qs ? '?' . $qs : ''), true, 302);
            exit;
        } else {
            self::renderForm($campaign);
        }
    }

    public static function submit($params)
    {
        $campaign = Campaign::findBySlug(isset($params['slug']) ? $params['slug'] : '');
        if (!$campaign) {
            http_response_code(404);
            echo 'Kampanye tidak ditemukan.';
            return;
        }

        // CORS — send on actual POST response (OPTIONS preflight handled by Router)
        self::setCorsHeaders($campaign);

        // Block known bots
        if (Helper::isBotRequest()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Forbidden']);
            return;
        }

        // Rate limit: 20 form submissions per IP per minute
        $ip = Helper::getClientIp();
        if (!Helper::rateLimit('submit_' . $ip, 20, 60)) {
            http_response_code(429);
            $ct2 = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
            if (strpos($ct2, 'application/json') !== false) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Terlalu banyak permintaan. Coba lagi sebentar.']);
            } else {
                echo 'Terlalu banyak permintaan. Coba lagi sebentar.';
            }
            return;
        }

        // Detect embed/AJAX (JSON) vs native form POST
        $ct     = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
        $isJson = strpos($ct, 'application/json') !== false;

        if ($isJson) {
            $raw  = file_get_contents('php://input', false, null, 0, 65536);
            $data = ($raw !== false && $raw !== '') ? (json_decode($raw, true) ?: []) : [];
        } else {
            $data = $_POST;
        }

        // Enforce per-field length limits
        $data['name']           = substr(isset($data['name'])           ? (string)$data['name']           : '', 0, 200);
        $data['phone']          = substr(isset($data['phone'])          ? (string)$data['phone']          : '', 0, 30);
        $data['email']          = substr(isset($data['email'])          ? (string)$data['email']          : '', 0, 200);
        $data['address']        = substr(isset($data['address'])        ? (string)$data['address']        : '', 0, 500);
        $data['quantity']       = substr(isset($data['quantity'])       ? (string)$data['quantity']       : '', 0, 100);
        $data['custom_message'] = substr(isset($data['custom_message']) ? (string)$data['custom_message'] : '', 0, 2000);

        $phone = Helper::sanitizePhone(isset($data['phone']) ? $data['phone'] : '');
        $email = filter_var(trim(isset($data['email']) ? $data['email'] : ''), FILTER_SANITIZE_EMAIL);
        $name  = strip_tags(trim(isset($data['name'])  ? $data['name']  : ''));
        $vid   = substr(strip_tags(trim(isset($data['_vid']) ? $data['_vid'] : '')), 0, 128);

        // wa_link campaigns don't require form fields
        if ($campaign->type !== 'wa_link' && $phone === '' && $name === '') {
            if ($isJson) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Nama atau nomor HP harus diisi.']);
            } else {
                echo 'Nama atau nomor HP harus diisi.';
            }
            return;
        }

        // Validate phone: min 10 digits
        if ($phone !== '') {
            $digits = preg_replace('/\D/', '', $phone);
            if (strlen($digits) < 10) {
                if ($isJson) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'message' => 'Masukkan nomor HP yang valid (min. 10 digit).']);
                } else {
                    echo 'Masukkan nomor HP yang valid (min. 10 digit).';
                }
                return;
            }
        }

        // Validate email format
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if ($isJson) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Masukkan alamat email yang valid (contoh: nama@domain.com).']);
            } else {
                echo 'Masukkan alamat email yang valid (contoh: nama@domain.com).';
            }
            return;
        }

        $ip = Helper::getClientIp();
        $fp = Crypto::fingerprint($phone, $email);
        if (Blocker::isBlocked(null, $ip, $fp, $vid)) {
            if ($isJson) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'blocked' => true,
                    'message' => $campaign->block_message ?: 'Akses Anda telah diblokir.',
                ]);
            } else {
                include KONEKTOR_ROOT . '/public/blocked.php';
            }
            return;
        }

        // Duplicate lead check (pass source_url for domain/page scope)
        $sourceUrlForDouble = substr(strip_tags(isset($data['source_url']) ? $data['source_url'] : ''), 0, 2000);
        $isDouble = $campaign->double_lead_enabled
            ? Lead::checkDouble($campaign->id, $phone, $email, $vid, $ip, $sourceUrlForDouble)
            : false;

        // Rotator — pick operator (duplikat tetap ikut rotator sesuai bobot)
        $operator = Rotator::pick($campaign->id);

        // Collect standard + extra fields
        $stdKeys   = ['name','phone','email','address','quantity','custom_message','_vid','source_url','referrer','click_id'];
        $extraData = [];
        foreach ($data as $k => $v) {
            if (!in_array($k, $stdKeys)) $extraData[$k] = strip_tags((string)$v);
        }

        // Parse fbclid + UTM params from source_url (embed JS sends landing page URL)
        // Falls back to current $_GET if source_url not available
        $sourceUrlForTracking = isset($data['source_url']) ? $data['source_url'] : '';
        $trackingParams = Helper::parseTrackingParams($sourceUrlForTracking ?: null);
        if (!empty($trackingParams)) {
            $extraData = array_merge($trackingParams, $extraData); // tracking first, form data wins on conflict
        }

        // Referrer: the real traffic source (e.g. facebook.com, tiktok.com).
        // For embedded forms, JS sends document.referrer of the landing page as 'referrer'.
        // HTTP_REFERER at submit time is the landing page URL itself (not the ad platform),
        // so we only fall back to it when the JS did not supply a referrer value.
        $referrer = substr(strip_tags(isset($data['referrer']) ? $data['referrer'] : ''), 0, 2000);
        if ($referrer === '') {
            // Last resort: HTTP_REFERER — will be the landing page URL when form is embedded,
            // or the actual ad platform URL when form is loaded directly.
            $referrer = substr(isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '', 0, 2000);
        }

        $leadData = [
            'campaign_id'    => $campaign->id,
            'operator_id'    => $operator ? $operator->id : null,
            'name'           => $name,
            'email'          => $email,
            'phone'          => $phone,
            'address'        => strip_tags(isset($data['address'])        ? $data['address']        : ''),
            'quantity'       => strip_tags(isset($data['quantity'])       ? $data['quantity']       : ''),
            'custom_message' => strip_tags(isset($data['custom_message']) ? $data['custom_message'] : ''),
            'extra_data'     => $extraData,
            '_vid'           => $vid,
            'source_url'     => substr(strip_tags(isset($data['source_url']) ? $data['source_url'] : ''), 0, 2000),
            'referrer'       => $referrer,
            'click_id'       => strip_tags(isset($_GET['click_id']) ? $_GET['click_id'] : (isset($_GET['clickid']) ? $_GET['clickid'] : (isset($data['click_id']) ? $data['click_id'] : (isset($trackingParams['fbclid']) ? $trackingParams['fbclid'] : '')))),
            'product_name'   => isset($campaign->product_name) ? $campaign->product_name : '',
        ];

        $leadId = Lead::create($leadData);
        if ($isDouble) Lead::markDouble($leadId);

        Analytics::logEvent($campaign->id, 'form_submit', $leadId);

        // ── Server-side pixels: form_submit (only for new / non-double leads) ──
        $capiMetaFired   = false;
        $capiTiktokFired = false;
        if (!$isDouble) {
            $eventData = array_merge($leadData, [
                'product_name' => isset($campaign->product_name) ? $campaign->product_name : '',
                'ip'           => $ip,
                'user_agent'   => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            ]);

            $metaCfg   = MetaApi::getConfig($campaign);
            $tiktokCfg = TiktokApi::getConfig($campaign);
            $snackCfg  = SnackApi::getConfig($campaign);

            if (!empty($metaCfg['pixel_id']) && !empty($metaCfg['token']) && !self::useBrowserPixel($metaCfg, 'token')) {
                MetaApi::sendEvent($metaCfg['form_submit_event'] ?? '', $eventData, $metaCfg);
                $capiMetaFired = true;
            }
            if (!empty($tiktokCfg['pixel_id']) && !empty($tiktokCfg['access_token']) && !self::useBrowserPixel($tiktokCfg, 'access_token')) {
                TiktokApi::sendEvent('form_submit', $eventData, $tiktokCfg);
                $capiTiktokFired = true;
            }
            if (!empty($snackCfg['pixel_id']) && !empty($snackCfg['access_token']) && !self::useBrowserPixel($snackCfg, 'access_token')) {
                SnackApi::sendEvent('form_submit', $eventData, $snackCfg);
            }
        }

        // Telegram notification (skip duplikat)
        if (!$isDouble && $operator && !empty($operator->telegram_chat_id)) {
            $lead = Lead::find($leadId);
            if ($lead) TelegramApi::notifyLead($campaign, $lead, $operator);
        }

        // Webhook notification
        Helper::fireWebhook('lead.new', [
            'lead_id'       => $leadId,
            'campaign_id'   => $campaign->id,
            'campaign_name' => $campaign->name,
            'name'          => $name,
            'phone'         => $phone,
            'email'         => $email,
            'is_double'     => $isDouble,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        $thanksUrl = Helper::campaignUrl($campaign) . '/thanks?lid=' . $leadId . ($isDouble ? '&double=1' : '');

        if ($isJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success'          => true,
                'double'           => $isDouble,
                'thanks_page_url'  => $thanksUrl,
                'capi_meta'        => $capiMetaFired,
                'capi_tiktok'      => $capiTiktokFired,
                'message'          => $isDouble
                    ? ($campaign->double_lead_message ?: 'Terima kasih! Data Anda sudah tercatat.')
                    : 'Sukses!',
            ]);
        } else {
            // Native form POST — redirect browser to thanks page
            header('Location: ' . $thanksUrl, true, 302);
        }
    }

    public static function thanks($params)
    {
        $campaign = Campaign::findBySlug(isset($params['slug']) ? $params['slug'] : '');
        if (!$campaign) { http_response_code(404); include KONEKTOR_ROOT . '/public/404.php'; return; }

        $leadId    = (int)(isset($_GET['lid'])     ? $_GET['lid']     : 0);
        $isDouble  = !empty($_GET['double']);
        $isBlocked = !empty($_GET['blocked']);

        // Prevent pixel re-fire when visitor navigates back to the thanks page URL
        if (!$isDouble && $leadId && isset($_SESSION['knk_tf_' . $leadId])) {
            $isDouble = true;
        }

        // For wa_link: ignore any stale lid — always create fresh record below
        $lead      = ($leadId && $campaign->type !== 'wa_link') ? Lead::find($leadId) : null;
        $operator  = ($lead && $lead->operator_id) ? Operator::find($lead->operator_id) : null;
        $cfg       = Campaign::getThanksConfig($campaign);

        // For wa_link: create a click record on first thanks page visit only.
        // Session guard prevents duplicate leads + events on browser back-navigation.
        if ($campaign->type === 'wa_link') {
            // Reject bot/crawler requests — they must not create leads
            if (Helper::isBotRequest()) {
                http_response_code(204);
                return;
            }

            $operator = Rotator::pick($campaign->id);

            $vid    = isset($_COOKIE['konektor_vid']) ? $_COOKIE['konektor_vid'] : (isset($_GET['_vid']) ? $_GET['_vid'] : '');
            $srcUrl = isset($_GET['_src']) ? substr($_GET['_src'], 0, 2000) : '';
            // _ref holds HTTP_REFERER captured at landing (real ad source, e.g. facebook.com).
            // Do NOT fall back to HTTP_REFERER here — it would be the konektor redirect URL.
            $refUrl = isset($_GET['_ref']) ? substr($_GET['_ref'], 0, 2000) : '';

            // Parse fbclid + UTM from _src (landing page URL forwarded by redirect)
            $waTracking = Helper::parseTrackingParams($srcUrl ?: null);

            // Session guard: prevent duplicate lead + events on browser back-navigation.
            // Key is per-campaign + per-visitor so each unique visitor gets their own slot.
            $waSessKey = 'knk_wa_' . $campaign->id . '_' . md5($vid ?: session_id());
            if (isset($_SESSION[$waSessKey])) {
                // Visitor already passed through this thanks page in the current session
                // (back-navigation detected) — mark as double so pixels are skipped.
                $isDouble = true;
                $storedId = $_SESSION[$waSessKey];
                if ($storedId && !$lead) {
                    $leadId = $storedId;
                    $lead   = Lead::find($leadId);
                }
            } else {
                // First visit: detect cross-session repeat via double-lead check
                $isRepeat = $campaign->double_lead_enabled
                    ? Lead::checkDouble($campaign->id, '', '', $vid, '', $srcUrl)
                    : false;
                $waClickId = isset($_GET['click_id']) ? $_GET['click_id'] : (isset($waTracking['fbclid']) ? $waTracking['fbclid'] : '');
                try {
                    $leadId = Lead::create([
                        'campaign_id' => $campaign->id,
                        'operator_id' => $operator ? $operator->id : null,
                        'name'        => '',
                        'phone'       => '',
                        'email'       => '',
                        '_vid'        => $vid,
                        'source_url'  => $srcUrl,
                        'referrer'    => $refUrl,
                        'click_id'    => $waClickId,
                        'extra_data'  => !empty($waTracking) ? $waTracking : null,
                    ]);
                    if ($isRepeat) {
                        Lead::markDouble($leadId);
                        $isDouble = true;
                    }
                    $lead = Lead::find($leadId);
                } catch (Exception $e) {
                    // Silently ignore lead creation errors in wa_link flow
                }
                // Store in session so back-navigation reuses this lead without re-firing events
                if (isset($leadId) && $leadId) $_SESSION[$waSessKey] = $leadId;
            }
        }

        // For wa_link: log as form_submit (= link clicked event) — skip on back-navigation
        if ($campaign->type === 'wa_link' && $lead && !$isDouble) {
            Analytics::logEvent($campaign->id, 'form_submit', $lead->id);
        }

        Analytics::logEvent($campaign->id, 'thanks_page', $leadId ?: null);

        // ── Server-side pixels: thanks_page — full backend tracking ────────
        $decrypted = null;
        $srcFromGet  = isset($_GET['_src']) ? substr($_GET['_src'], 0, 2000) : '';
        $refFromGet  = isset($_GET['_ref']) ? substr($_GET['_ref'], 0, 2000) : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
        $clickId     = isset($_GET['click_id']) ? $_GET['click_id'] : (isset($_GET['clickid']) ? $_GET['clickid'] : '');

        if (!$isDouble && !$isBlocked) {
            if ($lead) $decrypted = Lead::decrypt(clone $lead);

            // source_url: prefer what's stored in lead (set from landing page JS), then _src param
            $sourceUrl = ($decrypted && !empty($decrypted->source_url)) ? $decrypted->source_url : $srcFromGet;
            $referrer  = ($decrypted && !empty($decrypted->referrer))   ? $decrypted->referrer   : $refFromGet;

            // Decode extra_data from lead (contains fbclid + UTM params saved at submit/wa_link time)
            $leadExtraData = [];
            if ($lead && !empty($lead->extra_data)) {
                $decoded = json_decode($lead->extra_data, true);
                if (is_array($decoded)) $leadExtraData = $decoded;
            }

            $eventData = [
                'name'         => $decrypted && isset($decrypted->name)   ? $decrypted->name   : '',
                'phone'        => $decrypted && isset($decrypted->phone)  ? $decrypted->phone  : '',
                'email'        => $decrypted && isset($decrypted->email)  ? $decrypted->email  : '',
                'product_name' => isset($campaign->product_name) ? $campaign->product_name : '',
                'source_url'   => $sourceUrl,
                'referrer'     => $referrer,
                'ip'           => Helper::getClientIp(),
                'user_agent'   => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
                'click_id'     => $clickId,
                'extra_data'   => $leadExtraData,
            ];

            $metaCfg   = MetaApi::getConfig($campaign);
            $tiktokCfg = TiktokApi::getConfig($campaign);
            $snackCfg  = SnackApi::getConfig($campaign);

            if (!empty($metaCfg['pixel_id']) && !empty($metaCfg['token']) && !self::useBrowserPixel($metaCfg, 'token')) {
                MetaApi::sendEvent($metaCfg['thanks_page_event'] ?? '', $eventData, $metaCfg);
            }
            if (!empty($tiktokCfg['pixel_id']) && !empty($tiktokCfg['access_token']) && !self::useBrowserPixel($tiktokCfg, 'access_token')) {
                TiktokApi::sendEvent('thanks_page', $eventData, $tiktokCfg);
            }
            if (!empty($snackCfg['pixel_id']) && !empty($snackCfg['access_token']) && !self::useBrowserPixel($snackCfg, 'access_token')) {
                SnackApi::sendEvent('thanks_page', $eventData, $snackCfg);
            }

            // Mark in session so back-button revisit to the same thanks URL won't re-fire pixels
            if ($leadId) $_SESSION['knk_tf_' . $leadId] = 1;
        }

        // ── Telegram notification — only for form campaigns ───────────────
        // Link campaigns do not send Telegram notifications (no personal data collected)

        // ── Build redirect URL ─────────────────────────────────────────────
        $redirectUrl = '';
        if ($cfg['redirect_type'] === 'cs' && $operator) {
            $leadArr     = $decrypted ? (array)$decrypted : [];
            $redirectUrl = Rotator::getRedirectUrl($operator, $campaign, $leadArr);
            Analytics::logEvent($campaign->id, 'wa_click', $leadId ?: null);
        } elseif ($cfg['redirect_type'] === 'url' && !empty($cfg['redirect_url'])) {
            $redirectUrl = $cfg['redirect_url'];
        }

        // Browser-side scripts: skip entirely for double/blocked leads
        $metaCfg  = MetaApi::getConfig($campaign);
        $tiktokCfg= TiktokApi::getConfig($campaign);
        $snackCfg = SnackApi::getConfig($campaign);
        if ($isDouble || $isBlocked) {
            $metaSc   = '';
            $tiktokSc = '';
            $googleSc = '';
            $snackSc  = '';
        } else {
            $metaSc   = self::useBrowserPixel($metaCfg,   'token')        ? MetaApi::getPixelScript($campaign, 'thanks_page') : '';
            $tiktokSc = self::useBrowserPixel($tiktokCfg, 'access_token') ? TiktokApi::getScript($campaign, 'thanks_page')    : '';
            $googleSc = GoogleApi::getScript($campaign, 'thanks_page');
            $snackSc  = self::useBrowserPixel($snackCfg,  'access_token') ? SnackApi::getScript($campaign) : '';
        }

        // Pass form config so thanks.php can derive colors from the same scheme
        $formCfg = Campaign::getFormConfig($campaign);

        include KONEKTOR_ROOT . '/public/thanks.php';
    }

    public static function pixel($params)
    {
        $campaign = Campaign::findBySlug(isset($params['slug']) ? $params['slug'] : '');
        if (!$campaign) { http_response_code(204); return; }

        self::setCorsHeaders($campaign);

        // Block bots — they must not create PageView CAPI events (causes inflated Meta/TikTok counts)
        if (Helper::isBotRequest()) { http_response_code(204); return; }

        // Session dedup: one PageView per campaign per session (prevents reload/refresh double-fire)
        $sessKey = 'knk_pv_' . $campaign->id;
        if (isset($_SESSION[$sessKey])) { http_response_code(204); return; }
        $_SESSION[$sessKey] = 1;

        $sourceUrl = isset($_GET['url']) ? $_GET['url'] : '';
        $eventData = [
            'source_url' => $sourceUrl,
            'ip'         => Helper::getClientIp(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'referrer'   => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
        ];

        $metaCfg   = MetaApi::getConfig($campaign);
        $tiktokCfg = TiktokApi::getConfig($campaign);
        $snackCfg  = SnackApi::getConfig($campaign);

        // Only fire CAPI if platform is in CAPI mode (not browser-pixel mode)
        if (!empty($metaCfg['pixel_id']) && !empty($metaCfg['token']) && !self::useBrowserPixel($metaCfg, 'token')) {
            MetaApi::sendEvent($metaCfg['page_load_event'] ?? '', $eventData, $metaCfg);
        }
        if (!empty($tiktokCfg['pixel_id']) && !empty($tiktokCfg['access_token']) && !self::useBrowserPixel($tiktokCfg, 'access_token')) {
            TiktokApi::sendEvent('page_load', $eventData, $tiktokCfg);
        }
        if (!empty($snackCfg['pixel_id']) && !empty($snackCfg['access_token']) && !self::useBrowserPixel($snackCfg, 'access_token')) {
            SnackApi::sendEvent('page_load', $eventData, $snackCfg);
        }

        http_response_code(204);
    }

    // ─── Demo — browser-side pixels shown, no server-side tracking/leads ────────

    public static function demo($params)
    {
        $campaign = Campaign::findBySlug(isset($params['slug']) ? $params['slug'] : '');
        if (!$campaign) { http_response_code(404); include KONEKTOR_ROOT . '/public/404.php'; return; }

        $isDemoMode = true; // flag picked up by templates
        $cfg        = Campaign::getFormConfig($campaign);

        // Browser-side pixel scripts: respect pixel_mode setting
        $metaCfg  = MetaApi::getConfig($campaign);
        $tiktokCfg= TiktokApi::getConfig($campaign);
        $snackCfg = SnackApi::getConfig($campaign);
        $metaSc   = self::useBrowserPixel($metaCfg,   'token')        ? MetaApi::getPixelScript($campaign, 'page_load') : '';
        $tiktokSc = self::useBrowserPixel($tiktokCfg, 'access_token') ? TiktokApi::getScript($campaign, 'page_load')    : '';
        $googleSc = GoogleApi::getScript($campaign, 'page_load');
        $snackSc  = self::useBrowserPixel($snackCfg,  'access_token') ? SnackApi::getScript($campaign) : '';

        if ($campaign->type === 'wa_link') {
            include KONEKTOR_ROOT . '/public/link.php';
        } else {
            include KONEKTOR_ROOT . '/public/form.php';
        }
    }

    // ─── Pixel mode helpers ───────────────────────────────────────────────────

    // Returns true if browser-side pixel should fire for this platform.
    // pixel_mode='pixel' → always browser. pixel_mode='capi' (or absent) → browser only if no token.
    private static function useBrowserPixel(array $cfg, string $tokenKey): bool
    {
        $mode = isset($cfg['pixel_mode']) ? $cfg['pixel_mode'] : 'capi';
        if ($mode === 'pixel') return true;
        return empty($cfg[$tokenKey]);
    }

    // ─── Render helpers ──────────────────────────────────────────────────────

    private static function renderForm($campaign)
    {
        $cfg       = Campaign::getFormConfig($campaign);
        $metaCfg   = MetaApi::getConfig($campaign);
        $tiktokCfg = TiktokApi::getConfig($campaign);
        $snackCfg  = SnackApi::getConfig($campaign);
        $metaSc   = self::useBrowserPixel($metaCfg,   'token')        ? MetaApi::getPixelScript($campaign, 'page_load') : '';
        $tiktokSc = self::useBrowserPixel($tiktokCfg, 'access_token') ? TiktokApi::getScript($campaign, 'page_load')    : '';
        $googleSc = GoogleApi::getScript($campaign, 'page_load');
        $snackSc  = self::useBrowserPixel($snackCfg,  'access_token') ? SnackApi::getScript($campaign) : '';
        include KONEKTOR_ROOT . '/public/form.php';
    }

    private static function renderWaLink($campaign)
    {
        $cfg       = Campaign::getFormConfig($campaign);
        $metaCfg   = MetaApi::getConfig($campaign);
        $tiktokCfg = TiktokApi::getConfig($campaign);
        $snackCfg  = SnackApi::getConfig($campaign);
        $metaSc   = self::useBrowserPixel($metaCfg,   'token')        ? MetaApi::getPixelScript($campaign, 'page_load') : '';
        $tiktokSc = self::useBrowserPixel($tiktokCfg, 'access_token') ? TiktokApi::getScript($campaign, 'page_load')    : '';
        $googleSc = GoogleApi::getScript($campaign, 'page_load');
        $snackSc  = self::useBrowserPixel($snackCfg,  'access_token') ? SnackApi::getScript($campaign) : '';
        include KONEKTOR_ROOT . '/public/link.php';
    }
}

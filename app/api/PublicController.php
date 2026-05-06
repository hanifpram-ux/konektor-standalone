<?php

class PublicController
{
    // ─── Public pages ────────────────────────────────────────────────────────

    public static function form($params)
    {
        $campaign = Campaign::findBySlug(isset($params['slug']) ? $params['slug'] : '');
        if (!$campaign) { http_response_code(404); include KONEKTOR_ROOT . '/public/404.php'; return; }

        // Domain whitelist check
        $allowed = json_decode(isset($campaign->allowed_domains) ? $campaign->allowed_domains : '[]', true);
        if (!empty($allowed) && !in_array(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '', $allowed)) {
            http_response_code(403); echo 'Access denied.'; return;
        }

        // Block check
        $ip  = Helper::getClientIp();
        $vid = isset($_COOKIE['konektor_vid']) ? $_COOKIE['konektor_vid'] : '';
        if ($campaign->block_enabled && Blocker::isBlocked($campaign->id, $ip, '', $vid)) {
            include KONEKTOR_ROOT . '/public/blocked.php'; return;
        }

        Analytics::logEvent($campaign->id, 'page_load');

        if ($campaign->type === 'wa_link') {
            // Link campaigns: skip landing page, go straight to thanks
            $vid = isset($_COOKIE['konektor_vid']) ? $_COOKIE['konektor_vid'] : '';
            $qs  = http_build_query(array_filter([
                '_vid'     => $vid,
                '_src'     => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
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
        header('Content-Type: application/json; charset=utf-8');

        $campaign = Campaign::findBySlug(isset($params['slug']) ? $params['slug'] : '');
        if (!$campaign) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Kampanye tidak ditemukan.']);
            return;
        }

        $raw  = file_get_contents('php://input');
        $data = $raw ? (json_decode($raw, true) ?: []) : [];

        $phone = Helper::sanitizePhone(isset($data['phone']) ? $data['phone'] : '');
        $email = filter_var(trim(isset($data['email']) ? $data['email'] : ''), FILTER_SANITIZE_EMAIL);
        $name  = strip_tags(trim(isset($data['name'])  ? $data['name']  : ''));
        $vid   = strip_tags(trim(isset($data['_vid'])  ? $data['_vid']  : ''));

        // wa_link campaigns don't require form fields
        if ($campaign->type !== 'wa_link' && $phone === '' && $name === '') {
            echo json_encode(['success' => false, 'message' => 'Nama atau nomor HP harus diisi.']);
            return;
        }

        // Block check
        $ip = Helper::getClientIp();
        $fp = Crypto::fingerprint($phone, $email);
        if ($campaign->block_enabled && Blocker::isBlocked($campaign->id, $ip, $fp, $vid)) {
            echo json_encode([
                'success'         => false,
                'blocked'         => true,
                'message'         => $campaign->block_message ?: 'Akses Anda telah diblokir.',
                'thanks_page_url' => Helper::campaignUrl($campaign) . '/thanks?blocked=1',
            ]);
            return;
        }

        // Duplicate lead check
        $isDouble = $campaign->double_lead_enabled
            ? Lead::checkDouble($campaign->id, $phone, $email, $vid)
            : false;

        // Rotator — pick operator
        $operator = Rotator::pick($campaign->id);

        // Collect standard + extra fields
        $stdKeys   = ['name','phone','email','address','quantity','custom_message','_vid','source_url','referrer','click_id'];
        $extraData = [];
        foreach ($data as $k => $v) {
            if (!in_array($k, $stdKeys)) $extraData[$k] = strip_tags((string)$v);
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
            'referrer'       => substr(strip_tags(isset($data['referrer'])   ? $data['referrer']   : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '')), 0, 2000),
            'click_id'       => strip_tags(isset($_GET['click_id']) ? $_GET['click_id'] : (isset($_GET['clickid']) ? $_GET['clickid'] : (isset($data['click_id']) ? $data['click_id'] : ''))),
            'product_name'   => isset($campaign->product_name) ? $campaign->product_name : '',
        ];

        $leadId = Lead::create($leadData);
        if ($isDouble) Lead::markDouble($leadId);

        Analytics::logEvent($campaign->id, 'form_submit', $leadId);

        // ── Server-side pixels: form_submit (only for new / non-double leads) ──
        if (!$isDouble) {
            $eventData = array_merge($leadData, [
                'product_name' => isset($campaign->product_name) ? $campaign->product_name : '',
                'ip'           => $ip,
                'user_agent'   => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            ]);

            $metaCfg   = MetaApi::getConfig($campaign);
            $tiktokCfg = TiktokApi::getConfig($campaign);
            $snackCfg  = SnackApi::getConfig($campaign);

            if (!empty($metaCfg['pixel_id']) && !empty($metaCfg['token'])) {
                MetaApi::sendEvent($metaCfg['form_submit_event'] ?? '', $eventData, $metaCfg);
            }
            if (!empty($tiktokCfg['pixel_id']) && !empty($tiktokCfg['access_token'])) {
                TiktokApi::sendEvent('form_submit', $eventData, $tiktokCfg);
            }
            if (!empty($snackCfg['pixel_id']) && !empty($snackCfg['access_token'])) {
                SnackApi::sendEvent('form_submit', $eventData, $snackCfg);
            }
        }

        // Telegram notification
        if ($operator && !empty($operator->telegram_chat_id)) {
            $lead = Lead::find($leadId);
            if ($lead) TelegramApi::notifyLead($campaign, $lead, $operator);
        }

        $thanksUrl = Helper::campaignUrl($campaign) . '/thanks?lid=' . $leadId . ($isDouble ? '&double=1' : '');

        echo json_encode([
            'success'         => true,
            'double'          => $isDouble,
            'thanks_page_url' => $thanksUrl,
            'message'         => $isDouble
                ? ($campaign->double_lead_message ?: 'Terima kasih! Data Anda sudah tercatat.')
                : 'Sukses!',
        ]);
    }

    public static function thanks($params)
    {
        $campaign = Campaign::findBySlug(isset($params['slug']) ? $params['slug'] : '');
        if (!$campaign) { http_response_code(404); include KONEKTOR_ROOT . '/public/404.php'; return; }

        $leadId    = (int)(isset($_GET['lid'])     ? $_GET['lid']     : 0);
        $isDouble  = !empty($_GET['double']);
        $isBlocked = !empty($_GET['blocked']);
        // For wa_link: ignore any stale lid — always create fresh record below
        $lead      = ($leadId && $campaign->type !== 'wa_link') ? Lead::find($leadId) : null;
        $operator  = ($lead && $lead->operator_id) ? Operator::find($lead->operator_id) : null;
        $cfg       = Campaign::getThanksConfig($campaign);

        // For wa_link: always create a click record on thanks page visit
        // (no form submit — every visit to thanks IS the click event)
        if ($campaign->type === 'wa_link') {
            $operator = Rotator::pick($campaign->id);

            // Always create a click record for wa_link thanks visits
            $vid    = isset($_COOKIE['konektor_vid']) ? $_COOKIE['konektor_vid'] : (isset($_GET['_vid']) ? $_GET['_vid'] : '');
            $srcUrl = isset($_GET['_src']) ? substr($_GET['_src'], 0, 2000) : '';
            // Detect repeat click via cookie
            $isRepeat = $campaign->double_lead_enabled && $vid
                ? (bool)DB::val(
                    "SELECT id FROM " . DB::t('leads') . " WHERE campaign_id=? AND cookie_id=? LIMIT 1",
                    [$campaign->id, $vid]
                )
                : false;
            try {
                $leadId = Lead::create([
                    'campaign_id' => $campaign->id,
                    'operator_id' => $operator ? $operator->id : null,
                    'name'        => '',
                    'phone'       => '',
                    'email'       => '',
                    '_vid'        => $vid,
                    'source_url'  => $srcUrl,
                    'referrer'    => isset($_SERVER['HTTP_REFERER']) ? substr($_SERVER['HTTP_REFERER'], 0, 2000) : '',
                    'click_id'    => isset($_GET['click_id']) ? $_GET['click_id'] : '',
                ]);
                if ($isRepeat) Lead::markDouble($leadId);
                $lead = Lead::find($leadId);
            } catch (Exception $e) {
                // Silently ignore lead creation errors in wa_link flow
            }
        }

        // For wa_link: also log as form_submit (= link clicked event)
        if ($campaign->type === 'wa_link' && $lead) {
            Analytics::logEvent($campaign->id, 'form_submit', $lead->id);
        }

        Analytics::logEvent($campaign->id, 'thanks_page', $leadId ?: null);

        // ── Server-side pixels: thanks_page — full backend tracking ────────
        $decrypted = null;
        $sourceUrl = isset($_GET['_src'])      ? $_GET['_src']      : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
        $clickId   = isset($_GET['click_id'])  ? $_GET['click_id']  : (isset($_GET['clickid']) ? $_GET['clickid'] : '');

        if (!$isDouble && !$isBlocked) {
            if ($lead) $decrypted = Lead::decrypt(clone $lead);

            $eventData = [
                'name'         => $decrypted && isset($decrypted->name)   ? $decrypted->name   : '',
                'phone'        => $decrypted && isset($decrypted->phone)  ? $decrypted->phone  : '',
                'email'        => $decrypted && isset($decrypted->email)  ? $decrypted->email  : '',
                'product_name' => isset($campaign->product_name) ? $campaign->product_name : '',
                'source_url'   => $decrypted && isset($decrypted->source_url) ? $decrypted->source_url : $sourceUrl,
                'referrer'     => $decrypted && isset($decrypted->referrer)   ? $decrypted->referrer   : $sourceUrl,
                'ip'           => Helper::getClientIp(),
                'user_agent'   => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
                'click_id'     => $clickId,
            ];

            $metaCfg   = MetaApi::getConfig($campaign);
            $tiktokCfg = TiktokApi::getConfig($campaign);
            $snackCfg  = SnackApi::getConfig($campaign);

            if (!empty($metaCfg['pixel_id']) && !empty($metaCfg['token'])) {
                MetaApi::sendEvent($metaCfg['thanks_page_event'] ?? '', $eventData, $metaCfg);
            }
            if (!empty($tiktokCfg['pixel_id']) && !empty($tiktokCfg['access_token'])) {
                TiktokApi::sendEvent('thanks_page', $eventData, $tiktokCfg);
            }
            if (!empty($snackCfg['pixel_id']) && !empty($snackCfg['access_token'])) {
                SnackApi::sendEvent('thanks_page', $eventData, $snackCfg);
            }

            // Also fire form_submit event for wa_link click (equivalent to conversion)
            if ($campaign->type === 'wa_link') {
                if (!empty($metaCfg['pixel_id']) && !empty($metaCfg['token'])) {
                    MetaApi::sendEvent($metaCfg['form_submit_event'] ?? '', $eventData, $metaCfg);
                }
                if (!empty($tiktokCfg['pixel_id']) && !empty($tiktokCfg['access_token'])) {
                    TiktokApi::sendEvent('form_submit', $eventData, $tiktokCfg);
                }
            }
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

        // Browser-side scripts: Google always; Meta/TikTok skipped if CAPI token configured
        $metaCfg  = MetaApi::getConfig($campaign);
        $tiktokCfg= TiktokApi::getConfig($campaign);
        $metaSc   = empty($metaCfg['token'])   ? MetaApi::getPixelScript($campaign, 'thanks_page') : '';
        $tiktokSc = empty($tiktokCfg['access_token']) ? TiktokApi::getScript($campaign, 'thanks_page') : '';
        $googleSc = GoogleApi::getScript($campaign, 'thanks_page');
        $snackSc  = SnackApi::getScript($campaign);

        include KONEKTOR_ROOT . '/public/thanks.php';
    }

    public static function pixel($params)
    {
        $campaign = Campaign::findBySlug(isset($params['slug']) ? $params['slug'] : '');
        if (!$campaign) { http_response_code(204); return; }

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

        if (!empty($metaCfg['pixel_id']) && !empty($metaCfg['token'])) {
            MetaApi::sendEvent($metaCfg['page_load_event'] ?? '', $eventData, $metaCfg);
        }
        if (!empty($tiktokCfg['pixel_id']) && !empty($tiktokCfg['access_token'])) {
            TiktokApi::sendEvent('page_load', $eventData, $tiktokCfg);
        }
        if (!empty($snackCfg['pixel_id']) && !empty($snackCfg['access_token'])) {
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

        // Browser-side pixel scripts: inject hanya jika CAPI token belum diisi
        $metaCfg  = MetaApi::getConfig($campaign);
        $tiktokCfg= TiktokApi::getConfig($campaign);
        $metaSc   = empty($metaCfg['token'])   ? MetaApi::getPixelScript($campaign, 'page_load') : '';
        $tiktokSc = empty($tiktokCfg['access_token']) ? TiktokApi::getScript($campaign, 'page_load') : '';
        $googleSc = GoogleApi::getScript($campaign, 'page_load');
        $snackSc  = SnackApi::getScript($campaign);

        if ($campaign->type === 'wa_link') {
            include KONEKTOR_ROOT . '/public/link.php';
        } else {
            include KONEKTOR_ROOT . '/public/form.php';
        }
    }

    // ─── Render helpers ──────────────────────────────────────────────────────

    private static function renderForm($campaign)
    {
        $cfg      = Campaign::getFormConfig($campaign);
        $metaCfg  = MetaApi::getConfig($campaign);
        $tiktokCfg= TiktokApi::getConfig($campaign);
        // Jika CAPI token diisi, skip browser pixel — server-side CAPI sudah cukup
        $metaSc   = empty($metaCfg['token'])   ? MetaApi::getPixelScript($campaign, 'page_load') : '';
        $tiktokSc = empty($tiktokCfg['access_token']) ? TiktokApi::getScript($campaign, 'page_load') : '';
        $googleSc = GoogleApi::getScript($campaign, 'page_load');
        $snackSc  = SnackApi::getScript($campaign);
        include KONEKTOR_ROOT . '/public/form.php';
    }

    private static function renderWaLink($campaign)
    {
        $cfg      = Campaign::getFormConfig($campaign);
        $metaCfg  = MetaApi::getConfig($campaign);
        $tiktokCfg= TiktokApi::getConfig($campaign);
        $googleSc = GoogleApi::getScript($campaign, 'page_load');
        $metaSc   = empty($metaCfg['token'])   ? MetaApi::getPixelScript($campaign, 'page_load') : '';
        $tiktokSc = empty($tiktokCfg['access_token']) ? TiktokApi::getScript($campaign, 'page_load') : '';
        $snackSc  = SnackApi::getScript($campaign);
        include KONEKTOR_ROOT . '/public/link.php';
    }
}

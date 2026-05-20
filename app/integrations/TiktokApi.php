<?php

/**
 * TikTok Events API v1.3
 * Endpoint: https://business-api.tiktok.com/open_api/v1.3/event/track/
 */
class TiktokApi
{
    const API_ENDPOINT = 'https://business-api.tiktok.com/open_api/v1.3/event/track/';

    public static function getConfig($campaign)
    {
        $pixel = Campaign::decodeJsonField($campaign->pixel_config ?? null);
        $cfg = isset($pixel['tiktok']) ? $pixel['tiktok'] : [];
        // Backward compatibility: if 'enabled' key doesn't exist, treat as enabled (old campaigns)
        if (array_key_exists('enabled', $cfg) && empty($cfg['enabled'])) return [];
        return $cfg;
    }

    public static function sendEvent($eventType, $leadData, $cfg)
    {
        $pixelId     = trim(isset($cfg['pixel_id'])     ? $cfg['pixel_id']     : '');
        $accessToken = trim(isset($cfg['access_token']) ? $cfg['access_token'] : '');
        if (!$pixelId || !$accessToken) return null;

        $eventName = self::resolveEventName($cfg, $eventType);
        if (empty($eventName)) return null;
        $eventId   = bin2hex(random_bytes(16));

        $userData = [
            'ip'         => isset($leadData['ip'])         ? $leadData['ip']         : Helper::getClientIp(),
            'user_agent' => isset($leadData['user_agent']) ? $leadData['user_agent'] : substr(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '', 0, 512),
        ];

        if (!empty($_COOKIE['_ttp'])) {
            $userData['ttp'] = $_COOKIE['_ttp'];
        }

        if (!empty($leadData['email'])) {
            $userData['email'] = hash('sha256', strtolower(trim($leadData['email'])));
        }
        if (!empty($leadData['phone'])) {
            $phone = preg_replace('/[^0-9]/', '', $leadData['phone']);
            if (substr($phone, 0, 1) === '0') $phone = '62' . substr($phone, 1);
            $userData['phone_number'] = hash('sha256', '+' . $phone);
        }

        $event = [
            'event'      => $eventName,
            'event_id'   => $eventId,
            'event_time' => time(),
            'user'       => $userData,
            'page'       => [
                'url'      => isset($leadData['source_url']) ? $leadData['source_url'] : '',
                'referrer' => isset($leadData['referrer'])   ? $leadData['referrer']   : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''),
            ],
        ];

        $convEvents = ['Purchase','PlaceAnOrder','CompleteRegistration','SubmitForm','Subscribe','AddToCart','Checkout'];
        if (in_array($eventName, $convEvents)) {
            $props = [];
            if (!empty($cfg['value'])) {
                $props['value']    = (float)$cfg['value'];
                $props['currency'] = isset($cfg['currency']) ? $cfg['currency'] : 'IDR';
            }
            if (!empty($leadData['product_name'])) {
                $props['content_name'] = $leadData['product_name'];
                $props['content_type'] = 'product';
            }
            if ($props) $event['properties'] = $props;
        }

        $payload = [
            'pixel_code'      => $pixelId,
            'event_source'    => 'web',
            'event_source_id' => $pixelId,
            'data'            => [$event],
        ];

        if (!empty($cfg['test_event_code'])) {
            $payload['test_event_code'] = $cfg['test_event_code'];
        }

        $response = Helper::httpPost(
            self::API_ENDPOINT,
            $payload,
            ['Access-Token: ' . $accessToken]
        );

        return $response;
    }

    public static function getScript($campaign, $eventType)
    {
        $cfg = self::getConfig($campaign);
        if (empty($cfg['pixel_id'])) return '';

        $pixelId   = $cfg['pixel_id'];
        $eventName = self::resolveEventName($cfg, $eventType);
        if (empty($eventName)) return '';
        $eventId   = bin2hex(random_bytes(8));

        if ($eventType === 'thanks_page' && !empty($cfg['value'])) {
            $value    = (float)$cfg['value'];
            $currency = isset($cfg['currency']) ? $cfg['currency'] : 'IDR';
            $track    = "ttq.track('{$eventName}', { value: {$value}, currency: '{$currency}' }, { event_id: '{$eventId}' });";
        } else {
            $track = "ttq.track('{$eventName}', {}, { event_id: '{$eventId}' });";
        }

        $pageLine = ($eventType === 'page_load') ? "\n    ttq.page();" : '';

        return <<<HTML
<!-- TikTok Pixel -->
<script>
  !function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js",o=n&&n.partner;ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};n=document.createElement("script");n.type="text/javascript",n.async=!0,n.src=r+"?sdkid="+e+"&lib="+t;e=document.getElementsByTagName("script")[0];e.parentNode.insertBefore(n,e)};
    ttq.load('{$pixelId}');{$pageLine}
  }(window,document,'ttq');
  {$track}
</script>
<!-- End TikTok Pixel -->

HTML;
    }

    private static function resolveEventName($cfg, $eventType)
    {
        $keys = [
            'page_load'   => 'page_load_event',
            'form_submit' => 'form_submit_event',
            'thanks_page' => 'thanks_page_event',
        ];
        $k = isset($keys[$eventType]) ? $keys[$eventType] : '';
        if (!$k) return '';

        // If key exists in config but is empty, user chose "Tidak Digunakan"
        if (array_key_exists($k, $cfg) && trim($cfg[$k]) === '') {
            return '';
        }

        return trim(!empty($cfg[$k]) ? $cfg[$k] : '');
    }
}

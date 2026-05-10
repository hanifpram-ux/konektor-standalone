<?php

/**
 * Meta Conversions API v21.0 (latest Graph API)
 */
class MetaApi
{
    const API_VERSION = 'v21.0';

    public static function getConfig($campaign)
    {
        $pixel = Campaign::decodeJsonField($campaign->pixel_config ?? null);
        $cfg = isset($pixel['meta']) ? $pixel['meta'] : [];
        // Backward compatibility: if 'enabled' key doesn't exist, treat as enabled (old campaigns)
        if (array_key_exists('enabled', $cfg) && empty($cfg['enabled'])) return [];
        return $cfg;
    }

    public static function sendEvent($eventName, $leadData, $cfg)
    {
        if (empty($eventName)) return null;

        $token = trim(isset($cfg['token']) ? $cfg['token'] : '');
        if (!$token) return null;

        // Single pixel ID only
        $pixelId = trim(isset($cfg['pixel_id']) ? $cfg['pixel_id'] : '');
        if (empty($pixelId)) return null;

        $eventId  = bin2hex(random_bytes(16));
        $userData = self::buildUserData($leadData);

        $eventData = [
            'event_name'       => $eventName,
            'event_time'       => time(),
            'event_id'         => $eventId,
            'action_source'    => 'website',
            'event_source_url' => isset($leadData['source_url']) ? $leadData['source_url'] : '',
            'user_data'        => $userData,
        ];

        $noCustom = ['PageView','ViewContent','Search'];
        if (!in_array($eventName, $noCustom)) {
            $custom = [
                'currency' => isset($cfg['currency']) ? $cfg['currency'] : 'IDR',
                'value'    => (float)(isset($cfg['value']) ? $cfg['value'] : 0),
            ];
            if (!empty($leadData['product_name'])) {
                $custom['content_name'] = $leadData['product_name'];
            }
            $eventData['custom_data'] = $custom;
        }

        $payload = ['data' => [$eventData]];
        if (!empty($cfg['test_event_code'])) {
            $payload['test_event_code'] = $cfg['test_event_code'];
        }

        $endpoint = "https://graph.facebook.com/" . self::API_VERSION . "/{$pixelId}/events";
        return Helper::httpPost($endpoint . "?access_token={$token}", $payload);
    }

    public static function getPixelScript($campaign, $eventType)
    {
        $cfg = self::getConfig($campaign);
        if (empty($cfg['pixel_id'])) return '';

        // Single pixel ID only
        $pixelId = trim($cfg['pixel_id']);
        if (empty($pixelId)) return '';

        $eventMap = [
            'page_load'   => !empty($cfg['page_load_event'])   ? $cfg['page_load_event']   : '',
            'form_submit' => !empty($cfg['form_submit_event']) ? $cfg['form_submit_event'] : '',
            'thanks_page' => !empty($cfg['thanks_page_event']) ? $cfg['thanks_page_event'] : '',
        ];
        $event   = isset($eventMap[$eventType]) ? $eventMap[$eventType] : '';
        if (empty($event)) return '';
        $eventId = bin2hex(random_bytes(8));

        $initOpts = ($eventType !== 'page_load') ? ", { autoConfig: false }" : '';
        $inits = "  fbq('init', '{$pixelId}'{$initOpts});\n";

        if ($eventType === 'thanks_page' && !empty($cfg['value'])) {
            $value    = (float)$cfg['value'];
            $currency = isset($cfg['currency']) ? $cfg['currency'] : 'IDR';
            $track    = "  fbq('track', '{$event}', { value: {$value}, currency: '{$currency}' }, { eventID: '{$eventId}' });";
        } else {
            $track = "  fbq('track', '{$event}', {}, { eventID: '{$eventId}' });";
        }

        return <<<HTML
<!-- Meta Pixel -->
<script>
  !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
{$inits}{$track}
</script>
<!-- End Meta Pixel -->

HTML;
    }

    private static function buildUserData($lead)
    {
        $ud = [
            'client_ip_address' => Helper::getClientIp(),
            'client_user_agent' => substr(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '', 0, 512),
        ];
        if (!empty($_COOKIE['_fbp'])) $ud['fbp'] = $_COOKIE['_fbp'];
        if (!empty($_COOKIE['_fbc'])) $ud['fbc'] = $_COOKIE['_fbc'];

        if (!empty($lead['email'])) {
            $ud['em'] = [hash('sha256', strtolower(trim($lead['email'])))];
        }
        if (!empty($lead['phone'])) {
            $p = preg_replace('/[^0-9]/', '', $lead['phone']);
            if (substr($p, 0, 1) === '0') $p = '62' . substr($p, 1);
            $ud['ph'] = [hash('sha256', $p)];
        }
        if (!empty($lead['name'])) {
            $parts = explode(' ', trim($lead['name']), 2);
            $ud['fn'] = [hash('sha256', strtolower($parts[0]))];
            if (!empty($parts[1])) $ud['ln'] = [hash('sha256', strtolower($parts[1]))];
        }
        return $ud;
    }
}

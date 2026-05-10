<?php

/**
 * SnackVideo / Kwai Ads Event API
 * Endpoint: https://www.adsnebula.com/log/common/api
 */
class SnackApi
{
    const ENDPOINT = 'https://www.adsnebula.com/log/common/api';

    public static function getConfig($campaign)
    {
        $pixel = Campaign::decodeJsonField($campaign->pixel_config ?? null);
        $cfg = isset($pixel['snack']) ? $pixel['snack'] : [];
        // Backward compatibility: if 'enabled' key doesn't exist, treat as enabled (old campaigns)
        if (array_key_exists('enabled', $cfg) && empty($cfg['enabled'])) return [];
        return $cfg;
    }

    /**
     * @param string $eventType  'page_load' | 'form_submit' | 'thanks_page'
     *                           OR a direct event name string (for status_events mapping)
     */
    public static function sendEvent($eventType, $leadData, $cfg)
    {
        $pixelId     = trim(isset($cfg['pixel_id'])     ? $cfg['pixel_id']     : '');
        $accessToken = trim(isset($cfg['access_token']) ? $cfg['access_token'] : '');
        if (!$pixelId || !$accessToken) return null;

        $isTest = !empty($cfg['test_mode']);

        // Try resolving as event_type key first; fall back to treating it as a literal event name
        $resolved = self::resolveEventName($cfg, $eventType);
        $eventName = $resolved !== '' ? $resolved : (in_array($eventType, ['page_load','form_submit','thanks_page']) ? '' : trim($eventType));
        if (empty($eventName)) return null;

        if ($isTest && !empty($cfg['test_click_id'])) {
            $clickId = $cfg['test_click_id'];
        } else {
            $clickId = isset($leadData['click_id']) ? $leadData['click_id'] : (isset($_GET['click_id']) ? $_GET['click_id'] : (isset($_GET['clickid']) ? $_GET['clickid'] : ''));
        }

        $payload = [
            'pixelId'         => $pixelId,
            'access_token'    => $accessToken,
            'event_name'      => $eventName,
            'clickid'         => $clickId,
            'is_attributed'   => 1,
            'mmpcode'         => 'PL',
            'pixelSdkVersion' => '9.9.9',
            'testFlag'        => $isTest ? 1 : 0,
            'trackFlag'       => $isTest ? 1 : 0,
            'third_party'     => 'konektor',
        ];

        if (!empty($cfg['value'])) {
            $payload['value']    = (string)$cfg['value'];
            $payload['currency'] = isset($cfg['currency']) ? $cfg['currency'] : 'IDR';
        }

        $properties = [];
        if (!empty($leadData['product_name'])) $properties['content_name'] = $leadData['product_name'];
        if (!empty($leadData['quantity']))      $properties['quantity']     = (int)$leadData['quantity'];
        if ($properties) $payload['properties'] = json_encode($properties, JSON_UNESCAPED_UNICODE);

        $response = Helper::httpPost(
            self::ENDPOINT,
            $payload,
            ['accept: application/json;charset=utf-8']
        );

        return $response;
    }

    public static function getScript($campaign)
    {
        $cfg = self::getConfig($campaign);
        return trim(isset($cfg['html']) ? $cfg['html'] : '');
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

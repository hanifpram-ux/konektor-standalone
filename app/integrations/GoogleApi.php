<?php

/**
 * Google Ads / GTM / GA4 integration.
 *
 * Server-side: Google Ads Conversions API (gclid-based, fired on form_submit/thanks_page)
 * Browser-side: gtag.js script injected into HTML (page_load, form_submit, thanks_page)
 */
class GoogleApi
{
    public static function getConfig($campaign)
    {
        $pixel = $campaign->pixel_config ? json_decode($campaign->pixel_config, true) : [];
        return isset($pixel['google']) ? $pixel['google'] : [];
    }

    public static function getScript($campaign, $eventType)
    {
        $cfg    = self::getConfig($campaign);
        $output = '';

        // Google Tag Manager
        if (!empty($cfg['gtm_id'])) {
            $gtm = htmlspecialchars(trim($cfg['gtm_id']), ENT_QUOTES);
            $output .= <<<HTML
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f)})(window,document,'script','dataLayer','{$gtm}');</script>
<!-- End Google Tag Manager -->

HTML;
        }

        // Google Ads Conversion Tracking — browser-side gtag
        if (!empty($cfg['conversion_id'])) {
            $convId  = htmlspecialchars(trim($cfg['conversion_id']), ENT_QUOTES);
            $labelMap = [
                'page_load'   => isset($cfg['page_load_label'])   ? $cfg['page_load_label']   : '',
                'form_submit' => isset($cfg['form_submit_label']) ? $cfg['form_submit_label'] : '',
                'thanks_page' => isset($cfg['thanks_page_label']) ? $cfg['thanks_page_label'] : '',
            ];
            $label = trim(isset($labelMap[$eventType]) ? $labelMap[$eventType] : '');

            if ($label) {
                $label    = htmlspecialchars($label, ENT_QUOTES);
                $sendTo   = "AW-{$convId}/{$label}";
                $value    = !empty($cfg['value']) ? (float)$cfg['value'] : null;
                $currency = htmlspecialchars(isset($cfg['currency']) ? $cfg['currency'] : 'IDR', ENT_QUOTES);

                $convParams = "'send_to': '{$sendTo}'";
                if ($value !== null) {
                    $convParams .= ", 'value': {$value}, 'currency': '{$currency}'";
                }

                $output .= <<<HTML
<!-- Google Ads Conversion -->
<script async src="https://www.googletagmanager.com/gtag/js?id=AW-{$convId}"></script>
<script>
  window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
  gtag('js',new Date());gtag('config','AW-{$convId}');
  gtag('event','conversion',{{$convParams}});
</script>
<!-- End Google Ads Conversion -->

HTML;
            } else {
                $output .= <<<HTML
<script async src="https://www.googletagmanager.com/gtag/js?id=AW-{$convId}"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','AW-{$convId}');</script>

HTML;
            }
        }

        // Google Analytics 4 (GA4)
        if (!empty($cfg['ga4_id'])) {
            $ga4   = htmlspecialchars(trim($cfg['ga4_id']), ENT_QUOTES);
            $eMap  = [
                'page_load'   => 'page_view',
                'form_submit' => 'generate_lead',
                'thanks_page' => 'purchase',
            ];
            $gaEvent = isset($eMap[$eventType]) ? $eMap[$eventType] : '';
            $evLine  = ($gaEvent && $gaEvent !== 'page_view')
                ? "gtag('event','{$gaEvent}');"
                : '';

            $output .= <<<HTML
<!-- Google Analytics 4 -->
<script async src="https://www.googletagmanager.com/gtag/js?id={$ga4}"></script>
<script>
  window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
  gtag('js',new Date());gtag('config','{$ga4}');{$evLine}
</script>
<!-- End GA4 -->

HTML;
        }

        return $output;
    }

    public static function sendConversion($eventType, $leadData, $cfg)
    {
        $convId = trim(isset($cfg['conversion_id']) ? $cfg['conversion_id'] : '');
        $gclid  = trim(isset($leadData['click_id']) ? $leadData['click_id'] : '');
        if (!$convId || !$gclid) return null;

        $labelMap = [
            'form_submit' => isset($cfg['form_submit_label']) ? $cfg['form_submit_label'] : '',
            'thanks_page' => isset($cfg['thanks_page_label']) ? $cfg['thanks_page_label'] : '',
        ];
        $label = trim(isset($labelMap[$eventType]) ? $labelMap[$eventType] : '');
        if (!$label) return null;

        $value    = !empty($cfg['value']) ? (float)$cfg['value'] : 0;
        $currency = isset($cfg['currency']) ? $cfg['currency'] : 'IDR';
        $convTime = date('Y-m-d H:i:sP');

        $payload = [
            'conversions' => [[
                'gclid'                => $gclid,
                'conversion_action'    => "customers/{$convId}/conversionActions/{$label}",
                'conversion_date_time' => $convTime,
                'conversion_value'     => $value,
                'currency_code'        => $currency,
            ]],
        ];

        return $payload;
    }
}

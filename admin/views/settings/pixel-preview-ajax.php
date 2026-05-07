<?php
require_once dirname(__DIR__, 3) . '/admin/inc/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$campId    = (int)(isset($_GET['campaign_id']) ? $_GET['campaign_id'] : 0);
$eventType = isset($_GET['event_type']) ? $_GET['event_type'] : 'page_load';

if (!$campId) {
    echo json_encode(['html' => '']);
    exit;
}

$campaign = Campaign::find($campId);
if (!$campaign) {
    echo json_encode(['html' => '']);
    exit;
}

$html = '';

// Meta
$metaScript = MetaApi::getPixelScript($campaign, $eventType);
if ($metaScript) {
    $html .= '<div style="margin-bottom:12px;">';
    $html .= '<div style="font-size:11px;font-weight:600;color:#1877f2;margin-bottom:4px;">Meta / Facebook Pixel</div>';
    $html .= '<pre style="background:hsl(var(--muted));padding:10px 12px;border-radius:6px;font-size:11px;overflow-x:auto;white-space:pre-wrap;max-height:200px;overflow-y:auto;margin:0;">' . ae(trim($metaScript)) . '</pre>';
    $html .= '</div>';
}

// TikTok
$tiktokScript = TiktokApi::getScript($campaign, $eventType);
if ($tiktokScript) {
    $html .= '<div style="margin-bottom:12px;">';
    $html .= '<div style="font-size:11px;font-weight:600;color:#010101;margin-bottom:4px;">TikTok Pixel</div>';
    $html .= '<pre style="background:hsl(var(--muted));padding:10px 12px;border-radius:6px;font-size:11px;overflow-x:auto;white-space:pre-wrap;max-height:200px;overflow-y:auto;margin:0;">' . ae(trim($tiktokScript)) . '</pre>';
    $html .= '</div>';
}

// Google
$googleScript = GoogleApi::getScript($campaign, $eventType);
if ($googleScript) {
    $html .= '<div style="margin-bottom:12px;">';
    $html .= '<div style="font-size:11px;font-weight:600;color:#4285f4;margin-bottom:4px;">Google Ads / GTM / GA4</div>';
    $html .= '<pre style="background:hsl(var(--muted));padding:10px 12px;border-radius:6px;font-size:11px;overflow-x:auto;white-space:pre-wrap;max-height:200px;overflow-y:auto;margin:0;">' . ae(trim($googleScript)) . '</pre>';
    $html .= '</div>';
}

// SnackVideo
$snackScript = SnackApi::getScript($campaign);
if ($snackScript) {
    $html .= '<div style="margin-bottom:12px;">';
    $html .= '<div style="font-size:11px;font-weight:600;color:#e4372c;margin-bottom:4px;">SnackVideo / Kwai Pixel</div>';
    $html .= '<pre style="background:hsl(var(--muted));padding:10px 12px;border-radius:6px;font-size:11px;overflow-x:auto;white-space:pre-wrap;max-height:200px;overflow-y:auto;margin:0;">' . ae(trim($snackScript)) . '</pre>';
    $html .= '</div>';
}

if (!$html) {
    $html = '<p style="text-align:center;color:hsl(var(--muted-foreground));font-size:13px;padding:20px;">Tidak ada pixel yang dikonfigurasi untuk event ini.</p>';
}

echo json_encode(['html' => $html]);

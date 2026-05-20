<?php
/**
 * Link preview page — rendered by PublicController::demo() via /k/{slug}/preview
 * Only used for demo/preview — live /k/{slug} redirects directly to /thanks.
 * Variables: $campaign, $cfg, $metaSc, $tiktokSc, $googleSc, $snackSc, $isDemoMode
 */

// ─── Same 9-scheme system as form.php ────────────────────────────────────────
$cs  = isset($cfg['custom_style']) && is_array($cfg['custom_style']) ? $cfg['custom_style'] : [];
$tpl = isset($cfg['template']) ? $cfg['template'] : 'modern';
$sz  = isset($cfg['size'])     ? $cfg['size']     : 'default';

$schemes = [
    'modern'       => ['bg'=>'#ffffff',                                   'card'=>'#ffffff',              'accent'=>'#2563eb','text'=>'#0f172a','btnText'=>'#ffffff'],
    'classic'      => ['bg'=>'#fff8f0',                                   'card'=>'#ffffff',              'accent'=>'#dc2626','text'=>'#1a1a1a','btnText'=>'#ffffff'],
    'classic_flat' => ['bg'=>'#b7d09a',                                   'card'=>'#b7d09a',              'accent'=>'#FF5000','text'=>'#000000','btnText'=>'#ffffff'],
    'minimal'      => ['bg'=>'#fafafa',                                   'card'=>'#ffffff',              'accent'=>'#111111','text'=>'#111111','btnText'=>'#ffffff'],
    'card'         => ['bg'=>'#eff6ff',                                   'card'=>'#ffffff',              'accent'=>'#2563eb','text'=>'#0f172a','btnText'=>'#ffffff'],
    'gradient'     => ['bg'=>'linear-gradient(135deg,#1e3a5f,#0f2027)',  'card'=>'rgba(255,255,255,.08)','accent'=>'#38bdf8','text'=>'#e0f2fe','btnText'=>'#0f172a'],
    'rose'         => ['bg'=>'#fff1f2',                                   'card'=>'#ffffff',              'accent'=>'#e11d48','text'=>'#0f172a','btnText'=>'#ffffff'],
    'forest'       => ['bg'=>'#f0fdf4',                                   'card'=>'#ffffff',              'accent'=>'#16a34a','text'=>'#0f172a','btnText'=>'#ffffff'],
    'sunset'       => ['bg'=>'linear-gradient(135deg,#ff6b6b,#feca57)',  'card'=>'rgba(255,255,255,.95)','accent'=>'#ee5a24','text'=>'#2d3436','btnText'=>'#ffffff'],
    'ocean'        => ['bg'=>'linear-gradient(135deg,#0077b6,#00b4d8)',  'card'=>'rgba(255,255,255,.12)','accent'=>'#48cae4','text'=>'#caf0f8','btnText'=>'#003049'],
];
$sizeMap = [
    'compact'   => ['maxW'=>'380px','pad'=>'28px 24px','bfsize'=>'15px','bpy'=>'14px'],
    'default'   => ['maxW'=>'480px','pad'=>'32px 28px','bfsize'=>'16px','bpy'=>'16px'],
    'large'     => ['maxW'=>'600px','pad'=>'40px 36px','bfsize'=>'17px','bpy'=>'18px'],
    'fullwidth' => ['maxW'=>'100%', 'pad'=>'32px 28px','bfsize'=>'16px','bpy'=>'16px'],
];

$sc  = isset($schemes[$tpl]) ? $schemes[$tpl] : $schemes['modern'];
$szv = isset($sizeMap[$sz])  ? $sizeMap[$sz]  : $sizeMap['default'];

$bg      = !empty($cs['color_bg'])       ? $cs['color_bg']       : $sc['bg'];
$card    = !empty($cs['card_bg'])        ? $cs['card_bg']        : $sc['card'];
$accent  = !empty($cs['color_accent'])   ? $cs['color_accent']   : $sc['accent'];
$text    = !empty($cs['color_text'])     ? $cs['color_text']     : $sc['text'];
$btnText = !empty($cs['color_btn_text']) ? $cs['color_btn_text'] : $sc['btnText'];
$maxW    = !empty($cs['max_width'])      ? $cs['max_width']      : $szv['maxW'];
$pad     = !empty($cs['padding'])        ? $cs['padding']        : $szv['pad'];
$bfsize  = !empty($cs['btn_font_size'])  ? $cs['btn_font_size']  : $szv['bfsize'];
$bpy     = !empty($cs['btn_py'])         ? $cs['btn_py']         : $szv['bpy'];
$radius  = !empty($cs['border_radius'])  ? $cs['border_radius']  : '16px';
$btnLabel= isset($cfg['submit_label'])   ? $cfg['submit_label']  : 'Hubungi Sekarang';
$iconKey = isset($cs['link_icon']) ? $cs['link_icon'] : 'wa';
$iconSvgs = [
    'wa'    => '<svg style="width:22px;height:22px;fill:currentColor;flex-shrink:0" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.123.554 4.117 1.528 5.852L0 24l6.335-1.508A11.945 11.945 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.818 9.818 0 01-5.002-1.368l-.36-.214-3.72.885.916-3.617-.234-.372A9.818 9.818 0 012.182 12C2.182 6.57 6.57 2.182 12 2.182S21.818 6.57 21.818 12 17.43 21.818 12 21.818z"/></svg>',
    'tg'    => '<svg style="width:22px;height:22px;fill:currentColor;flex-shrink:0" viewBox="0 0 24 24"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.248-1.97 9.284c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L6.92 14.41l-2.96-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.836.176z"/></svg>',
    'line'  => '<svg style="width:22px;height:22px;fill:currentColor;flex-shrink:0" viewBox="0 0 24 24"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.627-.63h2.386c.349 0 .63.285.63.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.627-.63.349 0 .631.285.631.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.281.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/></svg>',
    'email' => '<svg style="width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>',
    'none'  => '',
];
$btnIcon = isset($iconSvgs[$iconKey]) ? $iconSvgs[$iconKey] : $iconSvgs['wa'];

$siteName = Settings::get('app_name', 'Konektor');
$title    = (isset($campaign->product_name) && $campaign->product_name
            ? $campaign->product_name : $campaign->name)
          . ' — ' . ($campaign->store_name ?: $siteName);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= Helper::e($title) ?> [Preview]</title>
<meta name="robots" content="noindex,nofollow">
<?= isset($metaSc)   ? $metaSc   : '' ?>
<?= isset($tiktokSc) ? $tiktokSc : '' ?>
<?= isset($googleSc) ? $googleSc : '' ?>
<?= !empty($snackSc) ? $snackSc  : '' ?>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: <?= Helper::e($bg) ?>;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
  }
  .wrap { width: 100%; max-width: <?= Helper::e($maxW) ?>; }
  .card {
    background: <?= Helper::e($card) ?>;
    border-radius: <?= Helper::e($radius) ?>;
    padding: <?= Helper::e($pad) ?>;
    text-align: center;
    box-shadow: 0 8px 32px rgba(0,0,0,.10);
  }
  .store   { font-size: 11px; color: <?= Helper::e($accent) ?>; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; margin-bottom: 8px; }
  .product { font-size: 22px; font-weight: 800; color: <?= Helper::e($text) ?>; margin-bottom: 20px; line-height: 1.3; }
  .btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 10px;
    width: 100%; padding: <?= Helper::e($bpy) ?> 20px;
    background: <?= Helper::e($accent) ?>; color: <?= Helper::e($btnText) ?>;
    border-radius: <?= Helper::e($radius) ?>; font-size: <?= Helper::e($bfsize) ?>; font-weight: 700;
    cursor: default; text-decoration: none; font-family: inherit;
    opacity: .85;
  }
  .knk-link-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 10px;
    width: 100%; padding: <?= Helper::e($bpy) ?> 20px;
    background: <?= Helper::e($accent) ?>; color: <?= Helper::e($btnText) ?>!important;
    border-radius: <?= Helper::e($radius) ?>; font-size: <?= Helper::e($bfsize) ?>; font-weight: 700;
    text-decoration: none!important; font-family: inherit;
    transition: background .15s;
  }
  .knk-link-btn:hover {
    opacity: 1;
    color: <?= Helper::e($btnText) ?>!important;
    text-decoration: unset!important;
  }
  .demo-bar {
    position: fixed; top: 0; left: 0; right: 0;
    background: #1e293b; color: #94a3b8;
    font-size: 12px; text-align: center;
    padding: 6px 16px;
    z-index: 9999;
  }
  .demo-bar strong { color: #f8fafc; }
  body { padding-top: 40px; }
  @media (max-width: 480px) { body { padding: 40px 16px 16px; } }
</style>
</head>
<body>

<div class="demo-bar">
  <strong>Mode Preview</strong> — Ini tampilan halaman Link. Tombol tidak aktif. Live URL: <strong><?= Helper::e(Helper::campaignUrl($campaign)) ?></strong>
</div>

<div class="wrap">
  <div class="card">
    <?php if ($campaign->store_name): ?>
    <p class="store"><?= Helper::e($campaign->store_name) ?></p>
    <?php endif; ?>
    <?php if ($campaign->product_name): ?>
    <p class="product"><?= Helper::e($campaign->product_name) ?></p>
    <?php endif; ?>

    <!-- Tombol disabled di preview -->
    <span class="btn" title="Tombol tidak aktif di mode preview">
      <?= $btnIcon ?>
      <?= Helper::e($btnLabel) ?>
    </span>

  </div>
</div>
</body>
</html>

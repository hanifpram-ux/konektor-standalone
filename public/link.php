<?php
/**
 * Link preview page — rendered by PublicController::demo() via /k/{slug}/preview
 * This file is ONLY used for demo/preview — never for live traffic.
 * Live /k/{slug} redirects directly to /thanks (no landing page shown).
 * Variables: $campaign, $cfg, $metaSc, $tiktokSc, $googleSc, $snackSc
 */

$cs       = isset($cfg['custom_style']) && is_array($cfg['custom_style']) ? $cfg['custom_style'] : [];
$accent   = !empty($cs['color_accent'])   ? $cs['color_accent']   : '#16a34a';
$bg       = !empty($cs['color_bg'])       ? $cs['color_bg']       : '#f0fdf4';
$btnText  = !empty($cs['color_btn_text']) ? $cs['color_btn_text'] : '#ffffff';
$maxW     = !empty($cs['max_width'])      ? $cs['max_width']      : '420px';
$radius   = !empty($cs['border_radius'])  ? $cs['border_radius']  : '16px';
$pad      = !empty($cs['padding'])        ? $cs['padding']        : '32px 28px';
$btnLabel = isset($cfg['submit_label'])   ? $cfg['submit_label']  : 'Hubungi Sekarang';

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
    background: #fff;
    border-radius: <?= Helper::e($radius) ?>;
    padding: <?= Helper::e($pad) ?>;
    text-align: center;
    box-shadow: 0 8px 32px rgba(0,0,0,.10);
  }
  .store   { font-size: 11px; color: <?= Helper::e($accent) ?>; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; margin-bottom: 8px; }
  .product { font-size: 22px; font-weight: 800; color: #0f172a; margin-bottom: 20px; line-height: 1.3; }
  .btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 10px;
    width: 100%; padding: 16px 20px;
    background: <?= Helper::e($accent) ?>; color: <?= Helper::e($btnText) ?>;
    border-radius: 10px; font-size: 16px; font-weight: 700;
    cursor: default; text-decoration: none; font-family: inherit;
    opacity: .85;
  }
  .demo-bar {
    position: fixed; top: 0; left: 0; right: 0;
    background: #1e293b; color: #94a3b8;
    font-size: 12px; text-align: center;
    padding: 6px 16px;
    z-index: 9999;
    font-family: -apple-system, BlinkMacSystemFont, sans-serif;
  }
  .demo-bar strong { color: #f8fafc; }
  body { padding-top: 36px; }
  @media (max-width: 480px) { body { padding: 36px 16px 16px; } }
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

    <!-- Tombol — disabled di preview, tidak ada href/JS -->
    <span class="btn" title="Tombol tidak aktif di mode preview">
      <svg style="width:22px;height:22px;fill:currentColor;flex-shrink:0" viewBox="0 0 24 24">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
        <path d="M12 0C5.373 0 0 5.373 0 12c0 2.123.554 4.117 1.528 5.852L0 24l6.335-1.508A11.945 11.945 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.818 9.818 0 01-5.002-1.368l-.36-.214-3.72.885.916-3.617-.234-.372A9.818 9.818 0 012.182 12C2.182 6.57 6.57 2.182 12 2.182S21.818 6.57 21.818 12 17.43 21.818 12 21.818z"/>
      </svg>
      <?= Helper::e($btnLabel) ?>
    </span>

  </div>
</div>
</body>
</html>

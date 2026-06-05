<?php
/**
 * Thanks page — rendered by PublicController::thanks()
 * Variables: $campaign, $cfg, $formCfg, $lead, $operator, $redirectUrl, $isDouble
 */

// ─── Derive colors from form/link scheme ────────────────────────────────────
$formCs  = isset($formCfg['custom_style']) && is_array($formCfg['custom_style']) ? $formCfg['custom_style'] : [];
$formTpl = isset($formCfg['template']) ? $formCfg['template'] : 'modern';

$schemes = [
    'modern'       => ['bg'=>'#f8fafc', 'card'=>'#ffffff', 'accent'=>'#2563eb', 'text'=>'#0f172a', 'shadow'=>'0 8px 32px rgba(0,0,0,.08)'],
    'classic'      => ['bg'=>'#fff8f0', 'card'=>'#ffffff', 'accent'=>'#dc2626', 'text'=>'#1a1a1a', 'shadow'=>'0 4px 16px rgba(220,38,38,.10)'],
    'classic_flat' => ['bg'=>'#b7d09a', 'card'=>'#b7d09a', 'accent'=>'#FF5000', 'text'=>'#000000', 'shadow'=>'none'],
    'minimal'      => ['bg'=>'#fafafa', 'card'=>'#ffffff', 'accent'=>'#111111', 'text'=>'#111111', 'shadow'=>'none'],
    'card'         => ['bg'=>'#eff6ff', 'card'=>'#ffffff', 'accent'=>'#2563eb', 'text'=>'#0f172a', 'shadow'=>'0 8px 32px rgba(37,99,235,.12)'],
    'gradient'     => ['bg'=>'linear-gradient(135deg,#1e3a5f,#0f2027)', 'card'=>'rgba(255,255,255,.10)', 'accent'=>'#38bdf8', 'text'=>'#e0f2fe', 'shadow'=>'0 8px 40px rgba(0,0,0,.3)'],
    'rose'         => ['bg'=>'#fff1f2', 'card'=>'#ffffff', 'accent'=>'#e11d48', 'text'=>'#0f172a', 'shadow'=>'0 4px 20px rgba(225,29,72,.10)'],
    'forest'       => ['bg'=>'#f0fdf4', 'card'=>'#ffffff', 'accent'=>'#16a34a', 'text'=>'#0f172a', 'shadow'=>'0 4px 20px rgba(22,163,74,.10)'],
    'sunset'       => ['bg'=>'linear-gradient(135deg,#ff6b6b,#feca57)', 'card'=>'rgba(255,255,255,.97)', 'accent'=>'#ee5a24', 'text'=>'#2d3436', 'shadow'=>'0 8px 32px rgba(238,90,36,.15)'],
    'ocean'        => ['bg'=>'linear-gradient(135deg,#0077b6,#00b4d8)', 'card'=>'rgba(255,255,255,.14)', 'accent'=>'#48cae4', 'text'=>'#caf0f8', 'shadow'=>'0 8px 40px rgba(0,119,182,.3)'],
];
$sc = isset($schemes[$formTpl]) ? $schemes[$formTpl] : $schemes['modern'];

$theme     = isset($cfg['theme']) && is_array($cfg['theme']) ? $cfg['theme'] : [];
$bgColor   = !empty($theme['bg_color'])     ? $theme['bg_color']     : (!empty($formCs['color_bg'])     ? $formCs['color_bg']     : $sc['bg']);
$cardColor = !empty($theme['card_color'])   ? $theme['card_color']   : (!empty($formCs['card_bg'])      ? $formCs['card_bg']      : $sc['card']);
$accent    = !empty($theme['accent_color']) ? $theme['accent_color'] : (!empty($formCs['color_accent']) ? $formCs['color_accent'] : $sc['accent']);
$textColor = !empty($theme['text_color'])   ? $theme['text_color']   : (!empty($formCs['color_text'])   ? $formCs['color_text']   : $sc['text']);
$shadow    = $sc['shadow'];
$radiusDefault = ($formTpl === 'classic_flat') ? '3px' : '16px';
$radius    = !empty($formCs['border_radius']) ? $formCs['border_radius'] : $radiusDefault;

$heading   = !empty($theme['heading'])     ? $theme['heading']     : 'Terima Kasih!';
$desc      = !empty($cfg['description'])   ? $cfg['description']   : 'Pesanan Anda telah kami terima. Tim kami akan segera menghubungi Anda.';
$delay     = (int)(isset($cfg['delay_redirect']) ? $cfg['delay_redirect'] : 3);
$showCount = !empty($cfg['show_countdown']);

// Browser-side pixel scripts
if (!isset($metaSc))   $metaSc   = MetaApi::getPixelScript($campaign, 'thanks_page');
if (!isset($tiktokSc)) $tiktokSc = TiktokApi::getScript($campaign, 'thanks_page');
if (!isset($googleSc)) $googleSc = GoogleApi::getScript($campaign, 'thanks_page');

// Card shape based on template variant
$tplName   = isset($cfg['template']) ? $cfg['template'] : 'modern';
$cardExtra = 'border-radius:' . Helper::e($radius) . ';box-shadow:' . Helper::e($shadow) . ';';
if ($tplName === 'minimal')    $cardExtra = 'border-radius:0;box-shadow:none;border-top:4px solid ' . Helper::e($accent) . ';';
elseif ($tplName === 'card')   $cardExtra = 'border-radius:20px;box-shadow:0 16px 48px rgba(0,0,0,.12);';
elseif ($tplName === 'fullscreen') $cardExtra = 'min-height:100vh;border-radius:0;display:flex;flex-direction:column;align-items:center;justify-content:center;box-shadow:none;';

// Button label & icon SVG based on redirect URL
$ru      = isset($redirectUrl) ? $redirectUrl : '';
$btnLabel = 'Lanjutkan';
$btnIcon  = '';

if ($ru) {
    if (strpos($ru, 'wa.me') !== false || strpos($ru, 'whatsapp') !== false) {
        $btnLabel = isset($cfg['btn_label']) && $cfg['btn_label'] ? $cfg['btn_label'] : 'Chat via WhatsApp';
        $btnIcon  = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.123.554 4.117 1.528 5.852L0 24l6.335-1.508A11.945 11.945 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.818 9.818 0 01-5.002-1.368l-.36-.214-3.72.885.916-3.617-.234-.372A9.818 9.818 0 012.182 12C2.182 6.57 6.57 2.182 12 2.182S21.818 6.57 21.818 12 17.43 21.818 12 21.818z"/></svg>';
    } elseif (strpos($ru, 't.me') !== false || strpos($ru, 'telegram') !== false) {
        $btnLabel = isset($cfg['btn_label']) && $cfg['btn_label'] ? $cfg['btn_label'] : 'Chat via Telegram';
        $btnIcon  = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.248-1.97 9.284c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L6.92 14.41l-2.96-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.836.176z"/></svg>';
    } elseif (strpos($ru, 'mailto:') !== false) {
        $btnLabel = isset($cfg['btn_label']) && $cfg['btn_label'] ? $cfg['btn_label'] : 'Hubungi via Email';
        $btnIcon  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>';
    } elseif (strpos($ru, 'line.me') !== false) {
        $btnLabel = isset($cfg['btn_label']) && $cfg['btn_label'] ? $cfg['btn_label'] : 'Chat via LINE';
        $btnIcon  = '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.627-.63h2.386c.349 0 .63.285.63.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.627-.63.349 0 .631.285.631.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.281.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/></svg>';
    } else {
        $btnLabel = isset($cfg['btn_label']) && $cfg['btn_label'] ? $cfg['btn_label'] : 'Lanjutkan';
        $btnIcon  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';
    }
}

// Icon box color (lighter tint of accent)
$iconBg = 'rgba(0,0,0,.04)';

$siteName = Settings::get('app_name', 'Konektor');
$title    = Helper::e($heading) . ' — ' . ($campaign->store_name ?: $siteName);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $title ?></title>
<meta name="robots" content="noindex,nofollow">
<?= isset($metaSc)   ? $metaSc   : '' ?>
<?= isset($tiktokSc) ? $tiktokSc : '' ?>
<?= isset($googleSc) ? $googleSc : '' ?>
<?= !empty($snackSc) ? $snackSc  : '' ?>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
  background: <?= Helper::e($bgColor) ?>;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
}
.tk-card {
  width: 100%;
  max-width: 440px;
  background: <?= Helper::e($cardColor) ?>;
  padding: 44px 36px 40px;
  text-align: center;
  color: <?= Helper::e($textColor) ?>;
  <?= $cardExtra ?>
}
.tk-icon-wrap {
  width: 72px; height: 72px;
  background: <?= Helper::e($iconBg) ?>;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 24px;
}
.tk-icon-wrap svg {
  width: 36px; height: 36px;
  stroke: <?= Helper::e($accent) ?>;
  fill: none;
  stroke-width: 2.5;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.tk-heading {
  font-size: 22px; font-weight: 800;
  color: <?= Helper::e($textColor) ?>;
  margin-bottom: 10px; line-height: 1.3;
}
.tk-desc {
  font-size: 14px; line-height: 1.7;
  color: <?= Helper::e($textColor) ?>; opacity: .68;
  margin-bottom: 28px;
}
.tk-double {
  background: rgba(0,0,0,.04);
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 13px;
  color: <?= Helper::e($textColor) ?>; opacity: .75;
  margin-bottom: 20px;
  line-height: 1.55;
}
.tk-progress-wrap {
  height: 3px; background: rgba(0,0,0,.07);
  border-radius: 999px; overflow: hidden;
  margin-bottom: 10px;
}
.tk-progress-bar {
  height: 100%; width: 0;
  background: <?= Helper::e($accent) ?>;
  border-radius: 999px;
  transition: width .05s linear;
}
.tk-countdown {
  font-size: 12px; font-weight: 600;
  color: <?= Helper::e($accent) ?>;
  margin-bottom: 20px;
}
.tk-btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 9px;
  width: 100%; padding: 14px 20px;
  background: <?= Helper::e($accent) ?>; color: #fff;
  border: none; border-radius: 10px;
  font-size: 15px; font-weight: 700;
  cursor: pointer; text-decoration: none;
  font-family: inherit;
  transition: opacity .18s, transform .12s;
}
.tk-btn:hover { opacity: .88; transform: translateY(-1px); }
.tk-btn svg { width: 18px; height: 18px; flex-shrink: 0; }
<?php if ($formTpl === 'classic_flat'): ?>
.tk-btn { border-radius: 3px; height: 50px; padding: 0 20px; }
.tk-btn:hover { opacity: 1; background: #e04800; transform: none; color: #fff!important; text-decoration: none!important; }
<?php endif; ?>
@media (max-width: 480px) {
  body { padding: 16px; }
  .tk-card { padding: 36px 24px 32px; }
}
</style>
</head>
<body>
<div class="tk-card">

  <!-- Check icon -->
  <div class="tk-icon-wrap">
    <?php if ($isDouble): ?>
    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
    <?php else: ?>
    <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
    <?php endif; ?>
  </div>

  <?php if ($isDouble): ?>
  <div class="tk-double">
    <?= Helper::e($campaign->double_lead_message ?: 'Data Anda sudah tercatat sebelumnya. Tim kami akan segera menghubungi Anda.') ?>
  </div>
  <?php endif; ?>

  <h1 class="tk-heading"><?= Helper::e($heading) ?></h1>
  <p class="tk-desc"><?= nl2br(Helper::e($desc)) ?></p>

  <?php if ($ru): ?>

    <?php if ($showCount): ?>
    <div class="tk-progress-wrap"><div class="tk-progress-bar" id="tkBar"></div></div>
    <p class="tk-countdown">Menghubungkan dalam <strong id="tkSec"><?= $delay ?></strong> detik...</p>
    <?php endif; ?>

    <a href="<?= htmlspecialchars($ru, ENT_QUOTES, 'UTF-8') ?>" class="tk-btn" id="tkBtn">
      <?= $btnIcon ?>
      <?= Helper::e($btnLabel) ?>
    </a>

  <?php endif; ?>

</div>

<?php if ($ru): ?>
<script>
(function(){
  var delay = <?= (int)$delay ?>;
  var url   = <?= json_encode($ru, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP) ?>;
  var show  = <?= $showCount ? 'true' : 'false' ?>;
  var bar   = document.getElementById('tkBar');
  var sec   = document.getElementById('tkSec');
  var total = delay * 1000, elapsed = 0, iv = 50;
  if (delay <= 0) { location.href = url; return; }
  var t = setInterval(function(){
    elapsed += iv;
    var pct = Math.min(elapsed / total * 100, 100);
    if (bar) bar.style.width = pct + '%';
    if (show && sec) sec.textContent = Math.max(0, Math.ceil((total - elapsed) / 1000));
    if (elapsed >= total) { clearInterval(t); location.href = url; }
  }, iv);
})();
</script>
<?php endif; ?>
</body>
</html>

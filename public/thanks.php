<?php
/**
 * Thanks page — rendered by PublicController::thanks()
 * Variables: $campaign, $cfg, $lead (decrypted optional), $operator, $redirectUrl, $isDouble
 */

$theme     = $cfg['theme']          ?? [];
$tplName   = $cfg['template']       ?? 'modern';
$bgColor   = $theme['bg_color']     ?? '#f0fdf4';
$cardColor = $theme['card_color']   ?? '#ffffff';
$accent    = $theme['accent_color'] ?? '#16a34a';
$textColor = $theme['text_color']   ?? '#0f172a';
$icon      = $theme['icon']         ?? '✅';
$heading   = $theme['heading']      ?? 'Pesanan Berhasil!';
$desc      = $cfg['description']    ?? 'Terima kasih! Pesanan Anda sedang kami proses.';
$delay     = (int)($cfg['delay_redirect'] ?? 3);
$showCount = !empty($cfg['show_countdown']);
// Browser-side scripts injected by PublicController::thanks() into $metaSc, $tiktokSc, $googleSc
// If not set (direct include), generate them here as fallback
if (!isset($metaSc))   $metaSc   = MetaApi::getPixelScript($campaign, 'thanks_page');
if (!isset($tiktokSc)) $tiktokSc = TiktokApi::getScript($campaign, 'thanks_page');
if (!isset($googleSc)) $googleSc = GoogleApi::getScript($campaign, 'thanks_page');

// Template-specific styles
$templates = [
    'modern' => [
        'body_extra'  => '',
        'card_extra'  => 'border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.08);',
        'icon_style'  => "font-size:64px;margin-bottom:16px;",
    ],
    'minimal' => [
        'body_extra'  => '',
        'card_extra'  => 'border-radius:0;box-shadow:none;border-top:4px solid '.$accent.';',
        'icon_style'  => "font-size:56px;margin-bottom:12px;",
    ],
    'card' => [
        'body_extra'  => '',
        'card_extra'  => 'border-radius:20px;box-shadow:0 16px 48px rgba(0,0,0,.12);',
        'icon_style'  => "font-size:72px;margin-bottom:20px;",
    ],
    'celebration' => [
        'body_extra'  => "background:linear-gradient(135deg,{$bgColor},#fff9c4);",
        'card_extra'  => 'border-radius:20px;box-shadow:0 12px 40px rgba(0,0,0,.12);',
        'icon_style'  => "font-size:80px;margin-bottom:20px;animation:bounce 1s infinite alternate;",
    ],
    'fullscreen' => [
        'body_extra'  => '',
        'card_extra'  => 'min-height:100vh;border-radius:0;display:flex;flex-direction:column;align-items:center;justify-content:center;',
        'icon_style'  => "font-size:96px;margin-bottom:24px;",
    ],
];
$tpl = $templates[$tplName] ?? $templates['modern'];

$siteName = Settings::get('app_name', 'Konektor');
$title    = $heading . ' — ' . ($campaign->store_name ?: $siteName);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= Helper::e($title) ?></title>
<meta name="robots" content="noindex,nofollow">
<?= $metaSc ?>
<?= $tiktokSc ?>
<?= $googleSc ?>
<?= !empty($snackSc) ? $snackSc : '' ?>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: <?= $bgColor ?>;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    <?= $tpl['body_extra'] ?>
  }
  .knk-thanks {
    width: 100%;
    max-width: 480px;
    background: <?= $cardColor ?>;
    padding: 40px 32px;
    text-align: center;
    color: <?= $textColor ?>;
    <?= $tpl['card_extra'] ?>
  }
  .knk-icon { <?= $tpl['icon_style'] ?> }
  .knk-heading { font-size: 24px; font-weight: 800; color: <?= $textColor ?>; margin-bottom: 12px; }
  .knk-desc { font-size: 15px; color: <?= $textColor ?>; opacity: .75; margin-bottom: 24px; line-height: 1.6; }
  .knk-countdown { font-size: 13px; color: <?= $accent ?>; font-weight: 600; margin-bottom: 20px; }
  .knk-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 10px;
    width: 100%; padding: 14px 20px;
    background: <?= $accent ?>; color: #fff;
    border: none; border-radius: 10px;
    font-size: 15px; font-weight: 700;
    cursor: pointer; text-decoration: none;
    font-family: inherit; transition: opacity .2s, transform .1s;
  }
  .knk-btn:hover { opacity: .88; transform: translateY(-1px); }
  .knk-btn-sec {
    display: block; margin-top: 12px; font-size: 13px;
    color: <?= $accent ?>; text-decoration: none; font-weight: 600;
  }
  .knk-double-notice {
    background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px;
    padding: 12px 16px; font-size: 13px; color: #856404; margin-bottom: 20px;
  }
  @keyframes bounce { from { transform: translateY(0); } to { transform: translateY(-10px); } }
  @media (max-width: 480px) { body { padding: 16px; } .knk-thanks { padding: 28px 20px; } }
</style>
</head>
<body>
<div class="knk-thanks">
  <div class="knk-icon"><?= htmlspecialchars($icon) ?></div>

  <?php if ($isDouble): ?>
  <div class="knk-double-notice">Data Anda sudah tercatat sebelumnya. Tim kami akan segera menghubungi Anda!</div>
  <?php endif; ?>

  <h1 class="knk-heading"><?= Helper::e($heading) ?></h1>
  <p class="knk-desc"><?= nl2br(Helper::e($desc)) ?></p>

  <?php if ($showCount && $redirectUrl): ?>
  <p class="knk-countdown" id="knkCountdown">Menghubungkan dalam <span id="knkSec"><?= $delay ?></span> detik...</p>
  <?php endif; ?>

  <?php if ($redirectUrl): ?>
    <?php
    $opType  = isset($operator->type) ? $operator->type : '';
    $btnMap  = [
        'whatsapp' => 'Chat WhatsApp Sekarang',
        'email'    => 'Hubungi via Email',
        'telegram' => 'Chat Telegram',
        'line'     => 'Chat LINE',
    ];
    $btnText = isset($btnMap[$opType]) ? $btnMap[$opType] : 'Lanjutkan';
    ?>
  <a href="<?= htmlspecialchars($redirectUrl) ?>" class="knk-btn" id="knkRedirectBtn">
    <?= $btnText ?>
  </a>
  <?php endif; ?>

  <?php if ($campaign->store_name): ?>
  <a href="#" class="knk-btn-sec"><?= Helper::e($campaign->store_name) ?></a>
  <?php endif; ?>
</div>

<?php if ($redirectUrl): ?>
<script>
(function() {
  var delay    = <?= $delay ?>;
  var url      = <?= json_encode($redirectUrl) ?>;
  var showCount= <?= $showCount ? 'true' : 'false' ?>;
  var secEl    = document.getElementById('knkSec');
  var rem      = delay;

  function tick() {
    rem--;
    if (secEl) secEl.textContent = rem;
    if (rem <= 0) { window.location.href = url; return; }
    setTimeout(tick, 1000);
  }

  if (delay > 0) {
    setTimeout(tick, 1000);
  } else {
    window.location.href = url;
  }
})();
</script>
<?php endif; ?>
</body>
</html>

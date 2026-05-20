<?php
/**
 * Public form page — rendered by PublicController::renderForm() / demo()
 * Variables available: $campaign, $cfg, $metaSc, $tiktokSc, $googleSc, $snackSc, $isDemoMode
 */
$isDemoMode = isset($isDemoMode) && $isDemoMode;
$submitUrl  = Helper::campaignSubmitUrl($campaign);
$template  = $cfg['template']     ?? 'modern';
$size      = $cfg['size']         ?? 'default';
$btnLabel  = $cfg['submit_label'] ?? 'Kirim Sekarang';
$cs        = $cfg['custom_style'] ?? [];
$fields    = array_merge($cfg['fields'] ?? [], $cfg['extra_fields'] ?? []);

// ─── Template color schemes ──────────────────────────────────────────────
$schemes = [
    'modern'       => ['bg'=>'#ffffff','card'=>'#ffffff','accent'=>'#2563eb','text'=>'#0f172a','border'=>'#e2e8f0','btn_text'=>'#ffffff','input_bg'=>'#f8fafc','shadow'=>'0 4px 24px rgba(0,0,0,.08)'],
    'classic'      => ['bg'=>'#fff8f0','card'=>'#ffffff','accent'=>'#dc2626','text'=>'#1a1a1a','border'=>'#d4a27a','btn_text'=>'#ffffff','input_bg'=>'#fff','shadow'=>'0 2px 16px rgba(220,38,38,.10)'],
    'classic_flat' => ['bg'=>'#b7d09a','card'=>'#b7d09a','accent'=>'#FF5000','text'=>'#000000','border'=>'#ccc','btn_text'=>'#ffffff','input_bg'=>'#fff','shadow'=>'none'],
    'minimal'      => ['bg'=>'#fafafa','card'=>'#ffffff','accent'=>'#111111','text'=>'#111111','border'=>'#e2e8f0','btn_text'=>'#ffffff','input_bg'=>'#fff','shadow'=>'none'],
    'card'         => ['bg'=>'#eff6ff','card'=>'#ffffff','accent'=>'#2563eb','text'=>'#0f172a','border'=>'#bfdbfe','btn_text'=>'#ffffff','input_bg'=>'#fff','shadow'=>'0 8px 32px rgba(37,99,235,.12)'],
    'gradient'     => ['bg'=>'linear-gradient(135deg,#1e3a5f,#0f2027)','card'=>'rgba(255,255,255,.08)','accent'=>'#38bdf8','text'=>'#e0f2fe','border'=>'rgba(255,255,255,.20)','btn_text'=>'#0f172a','input_bg'=>'rgba(255,255,255,.10)','shadow'=>'0 8px 40px rgba(0,0,0,.3)'],
    'rose'         => ['bg'=>'#fff1f2','card'=>'#ffffff','accent'=>'#e11d48','text'=>'#0f172a','border'=>'#fecdd3','btn_text'=>'#ffffff','input_bg'=>'#fff','shadow'=>'0 4px 20px rgba(225,29,72,.10)'],
    'forest'       => ['bg'=>'#f0fdf4','card'=>'#ffffff','accent'=>'#16a34a','text'=>'#0f172a','border'=>'#bbf7d0','btn_text'=>'#ffffff','input_bg'=>'#fff','shadow'=>'0 4px 20px rgba(22,163,74,.10)'],
    'sunset'       => ['bg'=>'linear-gradient(135deg,#ff6b6b,#feca57)','card'=>'rgba(255,255,255,.95)','accent'=>'#ee5a24','text'=>'#2d3436','border'=>'rgba(238,90,36,.3)','btn_text'=>'#ffffff','input_bg'=>'#fff','shadow'=>'0 8px 32px rgba(238,90,36,.15)'],
    'ocean'        => ['bg'=>'linear-gradient(135deg,#0077b6,#00b4d8)','card'=>'rgba(255,255,255,.12)','accent'=>'#48cae4','text'=>'#caf0f8','border'=>'rgba(255,255,255,.25)','btn_text'=>'#003049','input_bg'=>'rgba(255,255,255,.12)','shadow'=>'0 8px 40px rgba(0,119,182,.3)'],
];

$scheme = $schemes[$template] ?? $schemes['modern'];

// Custom style overrides
$bg       = !empty($cs['color_bg'])       ? $cs['color_bg']       : $scheme['bg'];
$card     = !empty($cs['card_bg'])        ? $cs['card_bg']        : $scheme['card'];
$accent   = !empty($cs['color_accent'])   ? $cs['color_accent']   : $scheme['accent'];
$text     = !empty($cs['color_text'])     ? $cs['color_text']     : $scheme['text'];
$border   = !empty($cs['color_border'])   ? $cs['color_border']   : $scheme['border'];
$btnText  = !empty($cs['color_btn_text']) ? $cs['color_btn_text'] : $scheme['btn_text'];
$inputBg  = !empty($cs['input_bg'])       ? $cs['input_bg']       : $scheme['input_bg'];
$shadow   = !empty($cs['shadow'])         ? $cs['shadow']         : $scheme['shadow'];

// Size presets
$sizeMap = [
    'compact'   => ['max_width'=>'380px','padding'=>'20px 18px','fsize'=>'13px','btn_fs'=>'14px','btn_py'=>'11px'],
    'default'   => ['max_width'=>'480px','padding'=>'28px 24px','fsize'=>'14px','btn_fs'=>'15px','btn_py'=>'14px'],
    'large'     => ['max_width'=>'600px','padding'=>'40px 36px','fsize'=>'15px','btn_fs'=>'16px','btn_py'=>'16px'],
    'fullwidth' => ['max_width'=>'100%', 'padding'=>'28px 24px','fsize'=>'14px','btn_fs'=>'15px','btn_py'=>'14px'],
];
$sz      = $sizeMap[$size] ?? $sizeMap['default'];
$maxW    = !empty($cs['max_width'])     ? $cs['max_width']     : $sz['max_width'];
$padding = !empty($cs['padding'])       ? $cs['padding']       : $sz['padding'];
$fsize   = !empty($cs['font_size'])     ? $cs['font_size']     : $sz['fsize'];
$btnFs   = !empty($cs['btn_font_size']) ? $cs['btn_font_size'] : $sz['btn_fs'];
$btnPy   = !empty($cs['btn_py'])        ? $cs['btn_py']        : $sz['btn_py'];
$radiusDefault = ($template === 'classic_flat') ? '3px' : '12px';
$radius  = !empty($cs['border_radius']) ? $cs['border_radius'] : $radiusDefault;
$fontFam = !empty($cs['font_family'])   ? $cs['font_family']   : '-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif';

$siteName = Settings::get('app_name', 'Konektor');
$title    = ($campaign->product_name ?? $campaign->name) . ' — ' . ($campaign->store_name ?: $siteName);

// Visitor ID (server sets cookie if missing, but also inject hidden field)
$vid = isset($_COOKIE['konektor_vid']) ? $_COOKIE['konektor_vid'] : '';
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
    font-family: <?= $fontFam ?>;
    background: <?= $bg ?>;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }
  .knk-wrap { width: 100%; max-width: <?= $maxW ?>; }
  .knk-card {
    background: <?= $card ?>;
    border-radius: <?= $radius ?>;
    box-shadow: <?= $shadow ?>;
    padding: <?= $padding ?>;
    color: <?= $text ?>;
  }
  .knk-store { font-size: 12px; color: <?= $accent ?>; text-align: center; margin-bottom: 6px; font-weight: 600; letter-spacing: .5px; text-transform: uppercase; }
  .knk-product { font-size: 20px; font-weight: 800; text-align: center; margin-bottom: 4px; color: <?= $text ?>; }
  .knk-desc { font-size: 13px; color: <?= $text ?>; opacity: .65; text-align: center; margin-bottom: 22px; }
  .knk-divider { height: 1px; background: <?= $border ?>; margin: 16px 0; }
  .knk-field { margin-bottom: 14px; }
  .knk-label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px; color: <?= $text ?>; }
  .knk-req { color: #ef4444; margin-left: 2px; }
  .knk-input, .knk-textarea, .knk-select {
    width: 100%; padding: 10px 12px; font-size: <?= $fsize ?>;
    border: 1.5px solid <?= $border ?>; border-radius: 8px;
    background: <?= $inputBg ?>; color: <?= $text ?>;
    outline: none; font-family: inherit; transition: border-color .15s;
  }
  .knk-input:focus, .knk-textarea:focus, .knk-select:focus { border-color: <?= $accent ?>; }
  .knk-textarea { resize: vertical; min-height: 80px; }
  .knk-btn {
    display: block; width: 100%;
    padding: <?= $btnPy ?> 20px;
    font-size: <?= $btnFs ?>; font-weight: 700;
    background: <?= $accent ?>; color: <?= $btnText ?>;
    border: none; border-radius: 8px; cursor: pointer;
    font-family: inherit; margin-top: 6px; transition: opacity .15s, transform .1s;
  }
  .knk-btn:hover { opacity: .88; transform: translateY(-1px); }
  .knk-err { padding: 10px 14px; border-radius: 8px; margin-bottom: 14px; font-size: 13px; font-weight: 600; background: #fee2e2; color: #b91c1c; }
<?php if ($template === 'classic_flat'): ?>
  .knk-err { border-radius: 4px; background: #ffebee; color: #c62828; border: 1px solid #ef5350; text-align: center; }
  .knk-label { font-weight: bold; }
  .knk-req { color: #B30000; }
  .knk-input, .knk-textarea, .knk-select { height: 36px; border-radius: 3px; padding: 0 8px; border: 1px solid #ccc; transition: border-color .15s, box-shadow .15s; }
  .knk-input:focus, .knk-textarea:focus, .knk-select:focus { border-color: #81bd4b; box-shadow: 0 0 0 2px rgba(129,189,75,0.2); }
  .knk-textarea { height: auto; padding: 8px; }
  .knk-btn { height: 50px; padding: 0 20px; border-radius: 3px; }
  .knk-btn:hover { opacity: 1; background: #e04800; transform: none; }
  .knk-btn:disabled { opacity: 0.5; cursor: not-allowed; }
<?php endif; ?>
  @media (max-width: 480px) { body { padding: 12px; } }
</style>
</head>
<body>

<div class="knk-wrap">
  <div class="knk-card">
    <?php if ($campaign->store_name): ?>
    <p class="knk-store"><?= Helper::e($campaign->store_name) ?></p>
    <?php endif; ?>
    <?php if ($campaign->product_name): ?>
    <p class="knk-product"><?= Helper::e($campaign->product_name) ?></p>
    <?php endif; ?>
    <?php if (!empty($cs['tagline'])): ?>
    <p class="knk-desc"><?= Helper::e($cs['tagline']) ?></p>
    <?php endif; ?>
    <?php if ($campaign->store_name || $campaign->product_name): ?>
    <div class="knk-divider"></div>
    <?php endif; ?>

    <?php if ($isDemoMode): ?>
    <div class="knk-err">Mode Demo — form tidak dikirim. Buka URL kampanye asli untuk submit nyata.</div>
    <?php endif; ?>

    <form action="<?= Helper::e($submitUrl) ?>" method="POST">
      <input type="hidden" name="_vid" value="<?= Helper::e($vid) ?>">
      <input type="hidden" name="source_url" id="knk-source-url" value="<?= Helper::e((knk_is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/')) ?>">
      <input type="hidden" name="referrer" id="knk-referrer" value="<?= Helper::e($_SERVER['HTTP_REFERER'] ?? '') ?>">

      <?php
      // Pass click_id from URL to form
      $clickId = $_GET['click_id'] ?? $_GET['clickid'] ?? '';
      if ($clickId):
      ?>
      <input type="hidden" name="click_id" value="<?= Helper::e($clickId) ?>">
      <?php endif; ?>

      <?php foreach ($fields as $f):
        if (empty($f['enabled'])) continue;
        $fname  = htmlspecialchars($f['name'] ?? '');
        $label  = htmlspecialchars($f['label'] ?? '');
        $ph     = htmlspecialchars($f['placeholder'] ?? ($f['label'] ?? ''));
        $type   = htmlspecialchars($f['type'] ?? 'text');
        $req    = !empty($f['required']);
        $reqAttr= $req ? ' required' : '';
        $reqMark= $req ? '<span class="knk-req">*</span>' : '';
      ?>
      <div class="knk-field">
        <label class="knk-label"><?= $label ?> <?= $reqMark ?></label>
        <?php if ($type === 'textarea'): ?>
          <textarea name="<?= $fname ?>" rows="3" placeholder="<?= $ph ?>" class="knk-textarea"<?= $reqAttr ?>></textarea>
        <?php elseif ($type === 'select' && !empty($f['options'])): ?>
          <select name="<?= $fname ?>" class="knk-select"<?= $reqAttr ?>>
            <option value="">-- Pilih --</option>
            <?php foreach ($f['options'] as $opt): ?>
            <option value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <?php $extra = ($fname === 'phone') ? ' inputmode="numeric" autocomplete="tel"' : ''; ?>
          <input type="<?= $type ?>" name="<?= $fname ?>" placeholder="<?= $ph ?>" class="knk-input"<?= $reqAttr ?><?= $extra ?>>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>

      <div class="knk-field">
        <button type="submit" class="knk-btn"><?= htmlspecialchars($btnLabel) ?></button>
      </div>
    </form>
  </div>
</div>

<script>
// Override source_url with actual page URL (important for embed: JS runs on landing page, not konektor server)
(function() {
  var su = document.getElementById('knk-source-url');
  var rf = document.getElementById('knk-referrer');
  if (su && window.location.href) su.value = window.location.href;
  if (rf && !rf.value && document.referrer) rf.value = document.referrer;
})();
</script>
</body>
</html>

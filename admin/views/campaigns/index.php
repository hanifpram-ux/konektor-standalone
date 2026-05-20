<?php
require_once dirname(__DIR__, 3) . '/admin/inc/bootstrap.php';
$pageTitle = 'Kampanye';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_delete_id'])) {
    Campaign::delete((int)$_POST['_delete_id']);
    flashSet('success', 'Kampanye berhasil dihapus.');
    header('Location: index.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_duplicate_id'])) {
    Campaign::duplicate((int)$_POST['_duplicate_id']);
    flashSet('success', 'Kampanye berhasil diduplikasi.');
    header('Location: index.php'); exit;
}

$campaigns = Campaign::all();

// ── Pre-build snippet data for each campaign ──────────────────────────────
function buildSnippetData($c)
{
    $url       = Helper::campaignUrl($c);
    $submitUrl = Helper::campaignSubmitUrl($c);
    $formCfg   = Campaign::getFormConfig($c);
    $cs        = isset($formCfg['custom_style']) && is_array($formCfg['custom_style']) ? $formCfg['custom_style'] : [];
    $fields    = array_merge(isset($formCfg['fields']) ? $formCfg['fields'] : [], isset($formCfg['extra_fields']) ? $formCfg['extra_fields'] : []);

    // ── Scheme — identical to standalone/public/form.php ────────────────
    $schemes = [
        'modern'   => ['bg'=>'#ffffff',                                    'card'=>'#ffffff',              'accent'=>'#2563eb','text'=>'#0f172a','border'=>'#e2e8f0',             'btnText'=>'#ffffff','inputBg'=>'#f8fafc','shadow'=>'0 4px 24px rgba(0,0,0,.08)'],
        'classic'  => ['bg'=>'#fff8f0',                                    'card'=>'#ffffff',              'accent'=>'#dc2626','text'=>'#1a1a1a','border'=>'#d4a27a',             'btnText'=>'#ffffff','inputBg'=>'#fff',   'shadow'=>'0 2px 16px rgba(220,38,38,.10)'],
        'minimal'  => ['bg'=>'#fafafa',                                    'card'=>'#ffffff',              'accent'=>'#111111','text'=>'#111111','border'=>'#e2e8f0',             'btnText'=>'#ffffff','inputBg'=>'#fff',   'shadow'=>'none'],
        'card'     => ['bg'=>'#eff6ff',                                    'card'=>'#ffffff',              'accent'=>'#2563eb','text'=>'#0f172a','border'=>'#bfdbfe',             'btnText'=>'#ffffff','inputBg'=>'#fff',   'shadow'=>'0 8px 32px rgba(37,99,235,.12)'],
        'gradient' => ['bg'=>'linear-gradient(135deg,#1e3a5f,#0f2027)',   'card'=>'rgba(255,255,255,.08)','accent'=>'#38bdf8','text'=>'#e0f2fe','border'=>'rgba(255,255,255,.20)','btnText'=>'#0f172a','inputBg'=>'rgba(255,255,255,.10)','shadow'=>'0 8px 40px rgba(0,0,0,.3)'],
        'rose'     => ['bg'=>'#fff1f2',                                    'card'=>'#ffffff',              'accent'=>'#e11d48','text'=>'#0f172a','border'=>'#fecdd3',             'btnText'=>'#ffffff','inputBg'=>'#fff',   'shadow'=>'0 4px 20px rgba(225,29,72,.10)'],
        'forest'   => ['bg'=>'#f0fdf4',                                    'card'=>'#ffffff',              'accent'=>'#16a34a','text'=>'#0f172a','border'=>'#bbf7d0',             'btnText'=>'#ffffff','inputBg'=>'#fff',   'shadow'=>'0 4px 20px rgba(22,163,74,.10)'],
        'sunset'   => ['bg'=>'linear-gradient(135deg,#ff6b6b,#feca57)',   'card'=>'rgba(255,255,255,.95)','accent'=>'#ee5a24','text'=>'#2d3436','border'=>'rgba(238,90,36,.3)',  'btnText'=>'#ffffff','inputBg'=>'#fff',   'shadow'=>'0 8px 32px rgba(238,90,36,.15)'],
        'ocean'    => ['bg'=>'linear-gradient(135deg,#0077b6,#00b4d8)',   'card'=>'rgba(255,255,255,.12)','accent'=>'#48cae4','text'=>'#caf0f8','border'=>'rgba(255,255,255,.25)','btnText'=>'#003049','inputBg'=>'rgba(255,255,255,.12)','shadow'=>'0 8px 40px rgba(0,119,182,.3)'],
    ];
    $sizes = [
        'compact'  => ['maxW'=>'380px','pad'=>'20px 18px','fsize'=>'13px','bfsize'=>'14px','bpy'=>'11px'],
        'default'  => ['maxW'=>'480px','pad'=>'28px 24px','fsize'=>'14px','bfsize'=>'15px','bpy'=>'14px'],
        'large'    => ['maxW'=>'600px','pad'=>'40px 36px','fsize'=>'15px','bfsize'=>'16px','bpy'=>'16px'],
        'fullwidth'=> ['maxW'=>'100%', 'pad'=>'28px 24px','fsize'=>'14px','bfsize'=>'15px','bpy'=>'14px'],
    ];
    $tpl = isset($formCfg['template']) ? $formCfg['template'] : 'modern';
    $sz  = isset($formCfg['size'])     ? $formCfg['size']     : 'default';
    $sc  = isset($schemes[$tpl]) ? $schemes[$tpl] : $schemes['modern'];
    $szv = isset($sizes[$sz])    ? $sizes[$sz]    : $sizes['default'];

    $bg     = !empty($cs['color_bg'])       ? $cs['color_bg']       : $sc['bg'];
    $card   = !empty($cs['card_bg'])        ? $cs['card_bg']        : $sc['card'];
    $acc    = !empty($cs['color_accent'])   ? $cs['color_accent']   : $sc['accent'];
    $txt    = !empty($cs['color_text'])     ? $cs['color_text']     : $sc['text'];
    $bdr    = !empty($cs['color_border'])   ? $cs['color_border']   : $sc['border'];
    $btnTxt = !empty($cs['color_btn_text']) ? $cs['color_btn_text'] : $sc['btnText'];
    $inpBg  = !empty($cs['input_bg'])       ? $cs['input_bg']       : $sc['inputBg'];
    $shadow = !empty($cs['shadow'])         ? $cs['shadow']         : $sc['shadow'];
    $maxW   = !empty($cs['max_width'])      ? $cs['max_width']      : $szv['maxW'];
    $pad    = !empty($cs['padding'])        ? $cs['padding']        : $szv['pad'];
    $fsize  = !empty($cs['font_size'])      ? $cs['font_size']      : $szv['fsize'];
    $bfsize = !empty($cs['btn_font_size'])  ? $cs['btn_font_size']  : $szv['bfsize'];
    $bpy    = !empty($cs['btn_py'])         ? $cs['btn_py']         : $szv['bpy'];
    $radius = !empty($cs['border_radius'])  ? $cs['border_radius']  : '12px';
    $btnLbl = htmlspecialchars(isset($formCfg['submit_label']) ? $formCfg['submit_label'] : 'Kirim Sekarang', ENT_QUOTES);

    // Sanitize all CSS values — strip anything that could break out of a <style> block
    // (prevents </style><script> injection via custom_style settings)
    $cssVal = function($v) {
        // Remove </style, <script, and control chars; keep only safe CSS characters
        $v = preg_replace('#</?style[^>]*>#i', '', (string)$v);
        $v = preg_replace('#<script[^>]*>.*?</script>#is', '', $v);
        $v = preg_replace('#[\x00-\x08\x0b\x0c\x0e-\x1f]#', '', $v);
        return $v;
    };
    $bg     = $cssVal($bg);   $card   = $cssVal($card);
    $acc    = $cssVal($acc);  $txt    = $cssVal($txt);
    $bdr    = $cssVal($bdr);  $btnTxt = $cssVal($btnTxt);
    $inpBg  = $cssVal($inpBg); $shadow = $cssVal($shadow);
    $maxW   = $cssVal($maxW);  $pad    = $cssVal($pad);
    $fsize  = $cssVal($fsize); $bfsize = $cssVal($bfsize);
    $bpy    = $cssVal($bpy);   $radius = $cssVal($radius);

    // ── Pixel scripts ─────────────────────────────────────────────────────
    $metaSc    = MetaApi::getPixelScript($c, 'page_load');
    $tiktokSc  = TiktokApi::getScript($c, 'page_load');
    $googleSc  = GoogleApi::getScript($c, 'page_load');
    $snackHtml = SnackApi::getScript($c);

    // ── Fields HTML ───────────────────────────────────────────────────────
    $fh = '';
    foreach ($fields as $f) {
        if (empty($f['enabled'])) continue;
        $fname = htmlspecialchars(isset($f['name'])        ? $f['name']        : '', ENT_QUOTES);
        $label = htmlspecialchars(isset($f['label'])       ? $f['label']       : '', ENT_QUOTES);
        $ph    = htmlspecialchars(isset($f['placeholder']) ? $f['placeholder'] : (isset($f['label']) ? $f['label'] : ''), ENT_QUOTES);
        $ftype = htmlspecialchars(isset($f['type'])        ? $f['type']        : 'text', ENT_QUOTES);
        $req   = !empty($f['required']);
        $ra    = $req ? ' required' : '';
        $rl    = $req ? '<span style="color:#ef4444;margin-left:2px">*</span>' : '';
        $fieldStyle = "width:100%;padding:10px 12px;font-size:{$fsize};border:1.5px solid {$bdr};border-radius:8px;background:{$inpBg};color:{$txt};outline:none;font-family:inherit;";

        $fh .= "<div style=\"margin-bottom:14px;\"><label style=\"display:block;font-size:13px;font-weight:600;margin-bottom:5px;color:{$txt}\">{$label}{$rl}</label>";
        if ($ftype === 'textarea') {
            $fh .= "<textarea name=\"{$fname}\" rows=\"3\" placeholder=\"{$ph}\" style=\"{$fieldStyle}resize:vertical;\"{$ra}></textarea>";
        } elseif ($ftype === 'select' && !empty($f['options'])) {
            $fh .= "<select name=\"{$fname}\" style=\"{$fieldStyle}\"{$ra}><option value=\"\">-- Pilih --</option>";
            foreach ((array)$f['options'] as $opt) {
                $fh .= '<option value="' . htmlspecialchars($opt, ENT_QUOTES) . '">' . htmlspecialchars($opt, ENT_QUOTES) . '</option>';
            }
            $fh .= '</select>';
        } else {
            $extra = ($fname === 'phone') ? ' inputmode="numeric" autocomplete="tel"' : '';
            $fh .= "<input type=\"{$ftype}\" name=\"{$fname}\" placeholder=\"{$ph}\" style=\"{$fieldStyle}\"{$ra}{$extra}>";
        }
        $fh .= '</div>';
    }

    $storeLine   = $c->store_name   ? "<p style=\"font-size:11px;color:{$acc};text-align:center;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin:0 0 6px\">" . htmlspecialchars($c->store_name, ENT_QUOTES) . "</p>" : '';
    $productLine = $c->product_name ? "<p style=\"font-size:19px;font-weight:800;text-align:center;margin:0 0 4px;color:{$txt}\">" . htmlspecialchars($c->product_name, ENT_QUOTES) . "</p>" : '';
    $tagLine     = !empty($cs['tagline']) ? "<p style=\"font-size:12px;opacity:.65;text-align:center;margin:0 0 14px;color:{$txt}\">" . htmlspecialchars($cs['tagline'], ENT_QUOTES) . "</p>" : '';
    $divider     = ($storeLine || $productLine) ? "<div style=\"height:1px;background:{$bdr};margin:12px 0 16px\"></div>" : '';

    $vidJs = "function _knkVid(){var m=document.cookie.match('(?:^|;)\\\\s*konektor_vid=([^;]*)');if(m)return decodeURIComponent(m[1]);var v=Array.from(crypto.getRandomValues(new Uint8Array(16))).map(b=>b.toString(16).padStart(2,'0')).join('');document.cookie='konektor_vid='+v+';path=/;max-age=31536000;SameSite=Lax';return v;}";

    // Escape for safe embedding in JS string literals and HTML comments
    $submitUrlJs = addslashes(filter_var($submitUrl, FILTER_SANITIZE_URL));
    $urlJs       = addslashes(filter_var($url, FILTER_SANITIZE_URL));
    $campNameSafe = htmlspecialchars(preg_replace('/[<>"\']/', '', $c->name), ENT_QUOTES);

    // ── Form embed code ───────────────────────────────────────────────────
    // .knk-wrap: NO flex/min-height — responsive mengikuti container host
    $formCode = <<<HTML
<!-- Konektor Form: {$campNameSafe} -->
{$metaSc}{$tiktokSc}{$googleSc}
<style>
.knk-wrap{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;width:100%;}
.knk-card{background:{$card};border-radius:{$radius};box-shadow:{$shadow};padding:{$pad};color:{$txt};max-width:{$maxW};margin:0 auto;}
.knk-err{display:none;padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:13px;font-weight:600;background:#fee2e2;color:#b91c1c;}
.knk-err.show{display:block;}
</style>
<div class="knk-wrap">
  <div class="knk-card">
    {$storeLine}{$productLine}{$tagLine}{$divider}
    <div class="knk-err" id="knkErr"></div>
    <form id="knkForm" novalidate>
      {$fh}
      <button type="submit" id="knkBtn" disabled style="width:100%;padding:{$bpy} 20px;font-size:{$bfsize};font-weight:700;background:{$acc};color:{$btnTxt};border:none;border-radius:8px;cursor:pointer;font-family:inherit;margin-top:6px;transition:opacity .15s;">{$btnLbl}</button>
    </form>
  </div>
</div>
<script>
(function(){
  {$vidJs}
  var form=document.getElementById('knkForm'),btn=document.getElementById('knkBtn'),err=document.getElementById('knkErr');
  var phoneEl=form.querySelector('input[name=phone]');
  if(phoneEl){phoneEl.addEventListener('input',function(){this.value=this.value.replace(/[^0-9]/g,'');});phoneEl.addEventListener('blur',function(){var v=this.value.replace(/[^0-9]/g,'');if(v.startsWith('62'))v='0'+v.slice(2);this.value=v;});}
  function chk(){var ok=true;form.querySelectorAll('[required]').forEach(function(el){if(!el.value.trim())ok=false;});btn.disabled=!ok;}
  form.addEventListener('input',chk);form.addEventListener('change',chk);chk();
  form.addEventListener('submit',function(e){
    e.preventDefault();btn.disabled=true;err.className='knk-err';
    var d={_vid:_knkVid(),source_url:location.href,referrer:document.referrer};
    var p=new URLSearchParams(location.search);d.click_id=p.get('click_id')||p.get('clickid')||'';
    new FormData(form).forEach(function(v,k){d[k]=v;});
    fetch('{$submitUrlJs}',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'include',body:JSON.stringify(d)})
    .then(function(r){return r.json();})
    .then(function(res){
      if(res.thanks_page_url){
        if(!res.double){if(window.fbq)fbq('track','Lead');if(window.ttq)ttq.track('SubmitForm');if(window.gtag)gtag('event','generate_lead');}
        location.href=res.thanks_page_url;return;
      }
      err.textContent=res.message||'Terjadi kesalahan.';err.className='knk-err show';chk();
    })
    .catch(function(){err.textContent='Koneksi gagal.';err.className='knk-err show';chk();});
  });
})();
</script>
<!-- End Konektor Form -->
HTML;

    // ── Link/button embed code ─────────────────────────────────────────────
    $linkLabel = htmlspecialchars(isset($formCfg['submit_label']) ? $formCfg['submit_label'] : 'Hubungi Sekarang', ENT_QUOTES);
    // $urlJs already set above (sanitized)
    $iconKey = isset($cs['link_icon']) ? $cs['link_icon'] : 'wa';
    $iconSvgs = [
        'wa'    => '<svg style="width:20px;height:20px;fill:currentColor;flex-shrink:0" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.123.554 4.117 1.528 5.852L0 24l6.335-1.508A11.945 11.945 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.818 9.818 0 01-5.002-1.368l-.36-.214-3.72.885.916-3.617-.234-.372A9.818 9.818 0 012.182 12C2.182 6.57 6.57 2.182 12 2.182S21.818 6.57 21.818 12 17.43 21.818 12 21.818z"/></svg>',
        'tg'    => '<svg style="width:20px;height:20px;fill:currentColor;flex-shrink:0" viewBox="0 0 24 24"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.248-1.97 9.284c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L6.92 14.41l-2.96-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.836.176z"/></svg>',
        'line'  => '<svg style="width:20px;height:20px;fill:currentColor;flex-shrink:0" viewBox="0 0 24 24"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.627-.63h2.386c.349 0 .63.285.63.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.627-.63.349 0 .631.285.631.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.281.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/></svg>',
        'email' => '<svg style="width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>',
        'none'  => '',
    ];
    $waIcon = isset($iconSvgs[$iconKey]) ? $iconSvgs[$iconKey] : $iconSvgs['wa'];

    if ($c->type === 'wa_link') {
        // Link: anchor langsung ke URL kampanye + pass _vid untuk tracking
        $btnCode = <<<HTML
<!-- Konektor Link Button: {$campNameSafe} -->
<style>
.knk-btn-wrap{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;width:100%;}
.knk-link-btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;width:100%;max-width:{$maxW};padding:{$bpy} 20px;background:{$acc};color:{$btnTxt};border:none;border-radius:{$radius};font-size:{$bfsize};font-weight:700;cursor:pointer;text-decoration:none;font-family:inherit;transition:opacity .2s;margin:0 auto;}
.knk-link-btn:hover{opacity:.88;}
</style>
<div class="knk-btn-wrap">
  <a href="{$url}" class="knk-link-btn" id="knkLinkBtn">
    {$waIcon} {$linkLabel}
  </a>
</div>
<script>
(function(){
  {$vidJs}
  var btn=document.getElementById('knkLinkBtn');
  if(!btn)return;
  btn.addEventListener('click',function(e){
    e.preventDefault();
    var href=this.getAttribute('href');
    var sep=href.indexOf('?')>=0?'&':'?';
    location.href=href+sep+'_vid='+encodeURIComponent(_knkVid())+'&_src='+encodeURIComponent(location.href);
  });
})();
</script>
<!-- End Konektor Link Button -->
HTML;
    } else {
        // Form campaign: tombol navigasi ke halaman form
        $btnCode = <<<HTML
<!-- Konektor Button: {$campNameSafe} -->
{$metaSc}{$tiktokSc}{$googleSc}
<style>
.knk-btn-wrap{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;width:100%;}
.knk-link-btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;width:100%;max-width:{$maxW};padding:{$bpy} 20px;background:{$acc};color:{$btnTxt};border:none;border-radius:{$radius};font-size:{$bfsize};font-weight:700;cursor:pointer;text-decoration:none;font-family:inherit;transition:opacity .2s;margin:0 auto;}
.knk-link-btn:hover{opacity:.88;}
</style>
<div class="knk-btn-wrap">
  <a href="{$url}" class="knk-link-btn" id="knkLinkBtn">
    {$linkLabel}
  </a>
</div>
<script>
(function(){
  {$vidJs}
  var btn=document.getElementById('knkLinkBtn');
  if(!btn)return;
  btn.addEventListener('click',function(e){
    e.preventDefault();
    var href=this.getAttribute('href');
    var sep=href.indexOf('?')>=0?'&':'?';
    location.href=href+sep+'_vid='+encodeURIComponent(_knkVid())+'&_src='+encodeURIComponent(location.href);
  });
})();
</script>
<!-- End Konektor Button -->
HTML;
    }

    $demoUrl = Helper::campaignUrl($c) . '/preview';

    return [
        'id'        => $c->id,
        'name'      => $c->name,
        'type'      => $c->type,
        'url'       => $url,
        'demo_url'  => $demoUrl,
        'form_code' => $formCode,
        'btn_code'  => $btnCode,
    ];
}

// Build snippets for all campaigns (lightweight enough for admin)
$snippets = [];
foreach ($campaigns as $c) {
    $snippets[$c->id] = buildSnippetData($c);
}

include dirname(__DIR__, 2) . '/inc/head.php';
include dirname(__DIR__, 2) . '/inc/sidebar.php';
?>
<div class="page-wrapper">

  <!-- Dialog pilih tipe kampanye -->
  <div id="typePickerOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;" onclick="if(event.target===this)closePicker()">
    <div style="background:hsl(var(--card));border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.25);width:100%;max-width:480px;padding:28px 28px 24px;margin:24px;">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
        <h3 style="font-size:16px;font-weight:700;">Pilih Jenis Kampanye</h3>
        <button onclick="closePicker()" style="background:none;border:none;cursor:pointer;color:hsl(var(--muted-foreground));padding:4px;"><?= icon('x') ?></button>
      </div>
      <p style="font-size:13px;color:hsl(var(--muted-foreground));margin-bottom:20px;">Pilih jenis kampanye sesuai kebutuhan Anda</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">

        <!-- Form Lead -->
        <a href="create.php?type=form" style="text-decoration:none;" class="type-pick-card">
          <div class="type-pick-icon" style="background:hsl(217 91% 60%/0.1);color:hsl(217 91% 50%);">
            <?= icon('file-text','',28) ?>
          </div>
          <div class="type-pick-title">Form Lead</div>
          <div class="type-pick-desc">Halaman form untuk mengumpulkan data nama, HP, email, dll. dari calon pembeli.</div>
          <div class="type-pick-badge">Paling populer</div>
        </a>

        <!-- Link -->
        <a href="create.php?type=wa_link" style="text-decoration:none;" class="type-pick-card">
          <div class="type-pick-icon" style="background:hsl(142 76% 36%/0.1);color:hsl(142 76% 36%);">
            <?= icon('link','',28) ?>
          </div>
          <div class="type-pick-title">Link</div>
          <div class="type-pick-desc">Halaman landing dengan tombol langsung ke kontak CS — WA, Telegram, atau Email.</div>
          <div class="type-pick-badge" style="background:hsl(142 76% 36%/0.1);color:hsl(142 76% 36%);">Cepat &amp; simpel</div>
        </a>

      </div>
    </div>
  </div>

  <div class="page-header">
    <div>
      <h1 class="page-title">Kampanye</h1>
      <p class="page-desc"><?= count($campaigns) ?> kampanye terdaftar</p>
    </div>
    <button onclick="openPicker()" class="btn btn-default"><?= icon('plus') ?> Kampanye Baru</button>
  </div>

  <?php include dirname(__DIR__, 2) . '/inc/flash.php'; ?>

  <?php if (empty($campaigns)): ?>
  <div class="card">
    <div class="empty-state">
      <?= icon('megaphone','',40) ?>
      <p class="empty-state-title">Belum ada kampanye</p>
      <p class="empty-state-desc">Buat kampanye pertama Anda untuk mulai mengumpulkan lead.</p>
      <button onclick="openPicker()" class="btn btn-default" style="margin-top:16px;"><?= icon('plus') ?> Buat Kampanye</button>
    </div>
  </div>
  <?php else: ?>
  <div style="display:flex;flex-direction:column;gap:8px;">
    <?php foreach ($campaigns as $c):
      $url     = Helper::campaignUrl($c);
      $formCfg = Campaign::getFormConfig($c);
      $ops     = Campaign::getOperators($c->id);
      $leadCnt = Lead::count(['campaign_id' => $c->id]);
    ?>
    <div class="card" style="padding:0;">
      <div style="display:flex;align-items:center;gap:16px;padding:16px 20px;">

        <!-- Type icon -->
        <div style="width:40px;height:40px;background:hsl(var(--muted));border-radius:8px;display:flex;align-items:center;justify-content:center;color:hsl(var(--muted-foreground));flex-shrink:0;">
          <?= icon($c->type === 'wa_link' ? 'message-square' : 'file-text') ?>
        </div>

        <!-- Info -->
        <div style="flex:1;min-width:0;">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap;">
            <span style="font-size:14px;font-weight:600;"><?= ae($c->name) ?></span>
            <span class="badge <?= $c->status === 'active' ? 'badge-success' : 'badge-secondary' ?>">
              <?= $c->status === 'active' ? 'Aktif' : 'Nonaktif' ?>
            </span>
            <span class="badge badge-outline"><?= $c->type === 'form' ? 'Form' : 'Link' ?></span>
            <span class="badge badge-outline" style="text-transform:capitalize;"><?= ae(isset($formCfg['template']) ? $formCfg['template'] : 'modern') ?></span>
          </div>
          <?php if ($c->product_name): ?>
          <div style="font-size:12px;color:hsl(var(--muted-foreground));margin-bottom:4px;"><?= ae($c->product_name) ?></div>
          <?php endif; ?>
          <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
            <code style="font-size:11px;color:hsl(var(--muted-foreground));font-family:monospace;max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= ae($url) ?></code>
            <button onclick="copyText('<?= ae(addslashes($url)) ?>',this)" class="btn btn-ghost btn-sm btn-icon" style="width:22px;height:22px;padding:3px;">
              <?= icon('copy') ?>
            </button>
            <a href="<?= ae($url) ?>" target="_blank" class="btn btn-ghost btn-sm btn-icon" style="width:22px;height:22px;padding:3px;">
              <?= icon('external-link') ?>
            </a>
          </div>
          <?php if (!empty($ops)): ?>
          <div style="display:flex;gap:4px;margin-top:6px;flex-wrap:wrap;">
            <?php foreach ($ops as $op): ?>
            <span class="badge badge-secondary"><?= ae($op->name) ?> (<?= (int)$op->weight ?>x)</span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

        <!-- Stats + actions -->
        <div style="display:flex;align-items:center;gap:12px;flex-shrink:0;">
          <div style="text-align:right;">
            <div style="font-size:18px;font-weight:700;"><?= number_format($leadCnt) ?></div>
            <div style="font-size:11px;color:hsl(var(--muted-foreground));">lead</div>
          </div>
          <div style="width:1px;height:36px;background:hsl(var(--border));"></div>
          <div style="display:flex;gap:4px;">
            <!-- Snippet/embed button -->
            <button onclick="openSnippet(<?= $c->id ?>)" class="btn btn-outline btn-sm" title="Dapatkan kode embed / link">
              <?= icon('code') ?>
            </button>
            <a href="../leads/index.php?campaign_id=<?= $c->id ?>" class="btn btn-outline btn-sm" title="Lihat Leads kampanye ini">
              <?= icon('users') ?>
            </a>
            <a href="create.php?id=<?= $c->id ?>" class="btn btn-outline btn-sm" title="Edit"><?= icon('pencil') ?></a>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Duplikat kampanye ini?')">
              <input type="hidden" name="_duplicate_id" value="<?= $c->id ?>">
              <button type="submit" class="btn btn-outline btn-sm" title="Duplikat">
                <?= icon('copy') ?>
              </button>
            </form>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus kampanye ini?')">
              <input type="hidden" name="_delete_id" value="<?= $c->id ?>">
              <button type="submit" class="btn btn-outline btn-sm" style="color:hsl(var(--destructive));border-color:hsl(var(--destructive)/0.3);" title="Hapus">
                <?= icon('trash-2') ?>
              </button>
            </form>
          </div>
        </div>

      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div><!-- /page-wrapper -->

<!-- ═══ SNIPPET DIALOG ═══════════════════════════════════════════════════════ -->
<div id="snippetDialog" class="dialog-overlay" style="display:none;" onclick="if(event.target===this)closeSnippet()">
  <div class="dialog-content" style="max-width:720px;width:100%;">
    <!-- Header -->
    <div class="dialog-header" style="display:flex;align-items:flex-start;justify-content:space-between;padding:20px 24px 0;">
      <div>
        <div style="display:flex;align-items:center;gap:8px;">
          <h3 class="dialog-title" id="snippetCampName">Kode Embed</h3>
          <span id="snippetTypeBadge" class="badge badge-outline"></span>
        </div>
        <p class="dialog-desc" id="snippetFlowDesc">Pixel tracking sudah terintegrasi.</p>
      </div>
      <button onclick="closeSnippet()" class="btn btn-ghost btn-icon" style="margin-top:-4px;"><?= icon('x') ?></button>
    </div>

    <!-- Alur event visual -->
    <div id="snippetFlowBar" style="display:flex;align-items:center;gap:0;padding:12px 24px 0;">
    </div>

    <!-- Tabs -->
    <div style="padding:12px 24px 0;">
      <div style="display:flex;gap:4px;border-bottom:1px solid hsl(var(--border));margin-bottom:16px;">
        <button id="stab-html" onclick="switchSnippetTab('html')"
          style="padding:8px 16px;font-size:13px;font-weight:500;border:none;background:transparent;cursor:pointer;border-bottom:2px solid hsl(var(--primary));color:hsl(var(--primary));margin-bottom:-1px;display:flex;align-items:center;gap:6px;">
          <?= icon('code','',13) ?> <span id="stab-html-label">Kode HTML</span>
        </button>
        <button id="stab-url" onclick="switchSnippetTab('url')"
          style="padding:8px 16px;font-size:13px;font-weight:500;border:none;background:transparent;cursor:pointer;border-bottom:2px solid transparent;color:hsl(var(--muted-foreground));margin-bottom:-1px;display:flex;align-items:center;gap:6px;">
          <?= icon('external-link','',13) ?> URL Langsung
        </button>
        <button id="stab-demo" onclick="switchSnippetTab('demo')"
          style="padding:8px 16px;font-size:13px;font-weight:500;border:none;background:transparent;cursor:pointer;border-bottom:2px solid transparent;color:hsl(var(--muted-foreground));margin-bottom:-1px;display:flex;align-items:center;gap:6px;">
          <?= icon('eye','',13) ?> Demo
        </button>
      </div>
    </div>

    <!-- Tab: Kode HTML (Form atau Link sesuai jenis kampanye) -->
    <div id="spane-html" class="snippet-pane" style="padding:0 24px 20px;">
      <p id="spane-html-desc" style="font-size:12px;color:hsl(var(--muted-foreground));margin-bottom:10px;">
        Kode HTML yang bisa dipasang di website, WordPress, atau landing page Anda. Pixel tracking sudah terintegrasi.
      </p>
      <div style="position:relative;">
        <textarea id="code-html" readonly
          style="width:100%;height:260px;font-family:'JetBrains Mono','Cascadia Code','Fira Code',monospace;font-size:11px;line-height:1.6;
                 border:1px solid hsl(var(--border));border-radius:var(--radius);padding:12px 14px;resize:none;
                 background:hsl(222.2 84% 4.9%);color:hsl(142 76% 55%);outline:none;"></textarea>
        <button onclick="copySnippet('html')" id="copy-html"
          style="position:absolute;top:8px;right:8px;" class="btn btn-secondary btn-sm">
          <?= icon('copy') ?> Salin
        </button>
      </div>
      <div id="spane-html-hint" style="margin-top:10px;padding:10px 12px;background:hsl(217 91% 60%/0.08);border:1px solid hsl(217 91% 60%/0.2);border-radius:var(--radius);font-size:12px;color:hsl(217 91% 40%);">
        <?= icon('info','',13) ?> Paste kode ini di dalam <code>&lt;body&gt;</code>.
      </div>
    </div>

    <!-- Tab: URL langsung -->
    <div id="spane-url" class="snippet-pane" style="display:none;padding:0 24px 20px;">
      <p id="url-pane-desc" style="font-size:12px;color:hsl(var(--muted-foreground));margin-bottom:10px;">
        URL langsung ke halaman kampanye. Gunakan untuk iklan, bio link, atau QR code.
      </p>
      <div style="display:flex;gap:8px;margin-bottom:12px;">
        <input id="url-direct" readonly class="input" style="font-family:monospace;font-size:13px;flex:1;">
        <button onclick="copyText(document.getElementById('url-direct').value,this)" class="btn btn-outline" style="flex-shrink:0;">
          <?= icon('copy') ?> Salin URL
        </button>
        <a id="url-open" href="#" target="_blank" class="btn btn-default" style="flex-shrink:0;">
          <?= icon('external-link') ?> Buka
        </a>
      </div>
      <div style="padding:12px 14px;background:hsl(var(--muted));border-radius:var(--radius);font-size:12px;color:hsl(var(--muted-foreground));">
        <strong>Parameter tracking:</strong> Tambahkan <code style="background:hsl(var(--background));padding:1px 5px;border-radius:3px;">?click_id=GCLID</code> untuk Google Ads,
        <code style="background:hsl(var(--background));padding:1px 5px;border-radius:3px;">?click_id=CLICKID</code> untuk SnackVideo.
        TikTok <code style="background:hsl(var(--background));padding:1px 5px;border-radius:3px;">_ttp</code> cookie ditangkap otomatis.
      </div>
    </div>

    <!-- Tab: Demo -->
    <div id="spane-demo" class="snippet-pane" style="display:none;padding:0 24px 20px;">
      <p style="font-size:12px;color:hsl(var(--muted-foreground));margin-bottom:12px;">
        Pratinjau halaman kampanye yang sebenarnya. Buka di tab baru untuk melihat tampilan pengunjung.
      </p>
      <div style="border:1px solid hsl(var(--border));border-radius:var(--radius);overflow:hidden;">
        <div style="padding:10px 14px;background:hsl(var(--muted));display:flex;align-items:center;gap:8px;">
          <div style="display:flex;gap:6px;">
            <div style="width:10px;height:10px;border-radius:50%;background:#ff5f57;"></div>
            <div style="width:10px;height:10px;border-radius:50%;background:#ffbd2e;"></div>
            <div style="width:10px;height:10px;border-radius:50%;background:#28c840;"></div>
          </div>
          <code id="demo-url-bar" style="font-size:11px;color:hsl(var(--muted-foreground));flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></code>
          <a id="demo-open-link" href="#" target="_blank" class="btn btn-ghost btn-sm" style="flex-shrink:0;">
            <?= icon('external-link','',12) ?> Buka
          </a>
        </div>
        <iframe id="demo-frame" src="about:blank"
          style="width:100%;height:480px;border:none;display:block;background:#fff;"
          title="Preview Kampanye"></iframe>
      </div>
    </div>

  </div>
</div>

<!-- Store snippets as JSON -->
<script id="snippetData" type="application/json">
<?= json_encode($snippets, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>
</script>

<script>
var SNIPPETS = JSON.parse(document.getElementById('snippetData').textContent);
var activeSnippetId = null;
var activeSnippetTab = 'form';

var FLOW_FORM = [
  {label:'Form Load',    icon:'👁', color:'#2563eb'},
  {label:'Form Submit',  icon:'📤', color:'#d97706'},
  {label:'Thanks Page',  icon:'✅', color:'#16a34a'},
];
var FLOW_WA = [
  {label:'Link Load',    icon:'👁', color:'#2563eb'},
  {label:'Link Clicked', icon:'👆', color:'#d97706'},
  {label:'Thanks Page',  icon:'✅', color:'#16a34a'},
];

function renderFlowBar(type) {
  var steps = (type === 'wa_link') ? FLOW_WA : FLOW_FORM;
  var bar = document.getElementById('snippetFlowBar');
  var html = '';
  steps.forEach(function(s, i) {
    if (i > 0) html += '<div style="flex:1;height:2px;background:hsl(214.3 31.8% 88%);margin:0 4px;"></div>';
    html += '<div style="display:flex;flex-direction:column;align-items:center;gap:3px;">'
          + '<div style="width:32px;height:32px;border-radius:50%;background:'+s.color+';display:flex;align-items:center;justify-content:center;font-size:14px;">'+s.icon+'</div>'
          + '<div style="font-size:10px;font-weight:600;color:'+s.color+';white-space:nowrap;">'+s.label+'</div>'
          + '</div>';
  });
  bar.innerHTML = html;
}

function openSnippet(id) {
  activeSnippetId = id;
  var s = SNIPPETS[id];
  if (!s) return;

  var isWa = (s.type === 'wa_link');

  document.getElementById('snippetCampName').textContent = s.name;
  document.getElementById('snippetTypeBadge').textContent = isWa ? 'Link' : 'Form Lead';
  document.getElementById('snippetFlowDesc').textContent = isWa
    ? 'Alur: Link Load → Link Clicked → Thanks Page'
    : 'Alur: Form Load → Form Submit → Thanks Page';

  renderFlowBar(s.type);

  // Kode HTML: form_code untuk form, btn_code untuk link
  var htmlCode = isWa ? (s.btn_code || '') : (s.form_code || '');
  document.getElementById('code-html').value = htmlCode;
  document.getElementById('url-direct').value = s.url;
  document.getElementById('url-open').href    = s.url;

  // Tab label
  var htmlLabelEl = document.getElementById('stab-html-label');
  if (htmlLabelEl) htmlLabelEl.textContent = isWa ? 'Kode HTML Link' : 'Kode HTML Form';

  // Pane description
  var htmlDescEl = document.getElementById('spane-html-desc');
  if (htmlDescEl) htmlDescEl.textContent = isWa
    ? 'Kode tombol HTML yang bisa dipasang di website, WordPress, atau landing page Anda. Klik tombol langsung ke CS.'
    : 'Kode HTML form lengkap yang bisa dipasang di website, WordPress, atau landing page Anda. Pixel tracking sudah terintegrasi.';

  // Hint
  var htmlHintEl = document.getElementById('spane-html-hint');
  if (htmlHintEl) htmlHintEl.innerHTML = isWa
    ? '<?= icon('info','',13) ?> Paste kode ini di dalam <code>&lt;body&gt;</code>.'
    : '<?= icon('info','',13) ?> Paste kode ini di dalam <code>&lt;body&gt;</code>. Untuk embed parsial, ubah <code>.knk-wrap{min-height:auto}</code>.';

  // Reset demo frame
  var demoUrl   = s.demo_url || s.url;
  var demoFrame = document.getElementById('demo-frame');
  var demoBar   = document.getElementById('demo-url-bar');
  var demoLink  = document.getElementById('demo-open-link');
  if (demoFrame) demoFrame.src = 'about:blank';
  if (demoBar)   demoBar.textContent = demoUrl;
  if (demoLink)  demoLink.href = demoUrl;

  // URL pane description
  var urlDesc = document.getElementById('url-pane-desc');
  if (urlDesc) urlDesc.textContent = isWa
    ? 'URL halaman Link. Bagikan ke iklan atau bio link — pengunjung langsung diarahkan ke CS setelah klik tombol.'
    : 'URL langsung ke halaman form. Gunakan untuk iklan, bio link, atau QR code.';

  switchSnippetTab('html');
  document.getElementById('snippetDialog').style.display = 'flex';
}

function closeSnippet() {
  document.getElementById('snippetDialog').style.display = 'none';
  activeSnippetId = null;
}

function switchSnippetTab(tab) {
  activeSnippetTab = tab;
  ['html','url','demo'].forEach(function(t) {
    var pane = document.getElementById('spane-'+t);
    if (pane) pane.style.display = t === tab ? 'block' : 'none';
    var tabBtn = document.getElementById('stab-'+t);
    if (!tabBtn) return;
    var isActive = t === tab;
    tabBtn.style.borderBottomColor = isActive ? 'hsl(var(--primary))' : 'transparent';
    tabBtn.style.color = isActive ? 'hsl(var(--primary))' : 'hsl(var(--muted-foreground))';
    tabBtn.style.fontWeight = isActive ? '600' : '500';
  });
  // Load demo iframe on demand
  if (tab === 'demo' && activeSnippetId) {
    var sd = SNIPPETS[activeSnippetId];
    var frame = document.getElementById('demo-frame');
    if (frame && sd && frame.src === 'about:blank') {
      frame.src = sd.demo_url || sd.url;
    }
  }
}

function copySnippet(type) {
  var ta = document.getElementById('code-'+type);
  var btn = document.getElementById('copy-'+type);
  if (!ta || !btn) return;
  navigator.clipboard.writeText(ta.value).then(function() {
    var orig = btn.innerHTML;
    btn.innerHTML = '<?= addslashes(icon('check')) ?> Tersalin!';
    btn.style.background = 'hsl(142 76% 36%)';
    btn.style.color = '#fff';
    setTimeout(function() {
      btn.innerHTML = orig;
      btn.style.background = '';
      btn.style.color = '';
    }, 2000);
  });
}

function copyText(text, btnEl) {
  navigator.clipboard.writeText(text).then(function() {
    if (!btnEl) return;
    var orig = btnEl.innerHTML;
    btnEl.innerHTML = '<?= addslashes(icon('check')) ?>';
    setTimeout(function() { btnEl.innerHTML = orig; }, 1500);
  });
}

// ESC to close snippet or type picker
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') { closeSnippet(); closePicker(); } });

// Type picker
function openPicker() {
    var el = document.getElementById('typePickerOverlay');
    el.style.display = 'flex';
    setTimeout(function() { el.style.opacity = '1'; }, 10);
}
function closePicker() {
    document.getElementById('typePickerOverlay').style.display = 'none';
}
</script>

<style>
.type-pick-card {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 20px 18px;
    border: 1.5px solid hsl(var(--border));
    border-radius: 12px;
    background: hsl(var(--card));
    cursor: pointer;
    transition: border-color .15s, box-shadow .15s, transform .1s;
}
.type-pick-card:hover {
    border-color: hsl(var(--primary));
    box-shadow: 0 4px 16px rgba(37,99,235,.12);
    transform: translateY(-1px);
}
.type-pick-icon {
    width: 52px; height: 52px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
}
.type-pick-title {
    font-size: 15px;
    font-weight: 700;
    color: hsl(var(--foreground));
}
.type-pick-desc {
    font-size: 12px;
    color: hsl(var(--muted-foreground));
    line-height: 1.55;
}
.type-pick-badge {
    display: inline-block;
    font-size: 10px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 20px;
    background: hsl(217 91% 60%/0.1);
    color: hsl(217 91% 50%);
    width: fit-content;
}
</style>

<?php include dirname(__DIR__, 2) . '/inc/foot.php'; ?>

<?php
require_once dirname(__DIR__, 3) . '/admin/inc/bootstrap.php';
$pageTitle = 'Test Pixel';

$campaigns = Campaign::all(['status' => 'active']);
$testLogs  = Logger::getLogs(20, 0);

// ── Handle POST — run test server-side directly ───────────────────────────
$results     = [];
$lastPayload = null;
$lastCampId  = 0;
$lastEvent   = 'form_submit';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['campaign_id'])) {
    $campId    = (int)$_POST['campaign_id'];
    $eventType = isset($_POST['event_type']) ? $_POST['event_type'] : 'form_submit';
    $platforms = isset($_POST['platforms']) && is_array($_POST['platforms']) ? $_POST['platforms'] : [];

    $lastCampId = $campId;
    $lastEvent  = $eventType;

    $campaign = Campaign::find($campId);
    if ($campaign && !empty($platforms)) {
        $leadData = [
            'name'         => strip_tags(isset($_POST['td_name'])   ? $_POST['td_name']   : 'Test User'),
            'phone'        => Helper::sanitizePhone(isset($_POST['td_phone']) ? $_POST['td_phone'] : '081234567890'),
            'email'        => filter_var(isset($_POST['td_email'])  ? $_POST['td_email']  : 'test@example.com', FILTER_SANITIZE_EMAIL),
            'source_url'   => strip_tags(isset($_POST['td_url'])    ? $_POST['td_url']    : Helper::siteUrl()),
            'product_name' => isset($campaign->product_name) ? $campaign->product_name : '',
            'ip'           => Helper::getClientIp(),
            'user_agent'   => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'referrer'     => '',
            'click_id'     => '',
        ];

        $lastPayload = [
            'campaign'   => $campaign->name,
            'event_type' => $eventType,
            'platforms'  => $platforms,
            'lead'       => $leadData,
        ];

        foreach ($platforms as $plat) {
            switch ($plat) {
                case 'meta':
                    $cfg = MetaApi::getConfig($campaign);
                    if (empty($cfg['pixel_id']) || empty($cfg['token'])) {
                        $results['meta'] = ['success'=>false,'message'=>'Pixel ID atau Token tidak dikonfigurasi.','response'=>null];
                        break;
                    }
                    $evName = isset($cfg[$eventType.'_event']) ? $cfg[$eventType.'_event'] : 'PageView';
                    $resp   = MetaApi::sendEvent($evName, $leadData, $cfg);
                    $code   = isset($resp['code']) ? (int)$resp['code'] : 0;
                    $body   = isset($resp['body']) ? (is_string($resp['body']) ? json_decode($resp['body'], true) : $resp['body']) : null;
                    $results['meta'] = [
                        'success'  => ($code >= 200 && $code < 300),
                        'message'  => 'Event: '.$evName.' — HTTP '.$code,
                        'response' => $body,
                    ];
                    Logger::log('Meta CAPI', $evName, 'graph.facebook.com/events', $leadData, $resp);
                    break;

                case 'tiktok':
                    $cfg = TiktokApi::getConfig($campaign);
                    if (empty($cfg['pixel_id']) || empty($cfg['access_token'])) {
                        $results['tiktok'] = ['success'=>false,'message'=>'Pixel ID atau Access Token tidak dikonfigurasi.','response'=>null];
                        break;
                    }
                    $resp = TiktokApi::sendEvent($eventType, $leadData, $cfg);
                    $code = isset($resp['code']) ? (int)$resp['code'] : 0;
                    $body = isset($resp['body']) ? (is_string($resp['body']) ? json_decode($resp['body'], true) : $resp['body']) : null;
                    $ok   = ($code >= 200 && $code < 300) && (!isset($body['code']) || $body['code'] == 0);
                    $results['tiktok'] = [
                        'success'  => $ok,
                        'message'  => 'HTTP '.$code.(isset($body['message']) ? ' — '.$body['message'] : ''),
                        'response' => $body,
                    ];
                    Logger::log('TikTok Events API', $eventType, 'business-api.tiktok.com/open_api/v1.3/event/track/', $leadData, $resp);
                    break;

                case 'snack':
                    $cfg = SnackApi::getConfig($campaign);
                    if (empty($cfg['pixel_id']) || empty($cfg['access_token'])) {
                        $results['snack'] = ['success'=>false,'message'=>'Pixel ID atau Access Token tidak dikonfigurasi.','response'=>null];
                        break;
                    }
                    $resp = SnackApi::sendEvent($eventType, $leadData, $cfg);
                    $code = isset($resp['code']) ? (int)$resp['code'] : 0;
                    $body = isset($resp['body']) ? (is_string($resp['body']) ? json_decode($resp['body'], true) : $resp['body']) : null;
                    $results['snack'] = [
                        'success'  => ($code >= 200 && $code < 300),
                        'message'  => 'HTTP '.$code,
                        'response' => $body,
                    ];
                    Logger::log('SnackVideo/Kwai API', $eventType, 'adsnebula.com/log/common/api', $leadData, $resp);
                    break;

                case 'google':
                    $cfg = GoogleApi::getConfig($campaign);
                    if (empty($cfg['conversion_id'])) {
                        $results['google'] = ['success'=>false,'message'=>'Conversion ID tidak dikonfigurasi.','response'=>null];
                        break;
                    }
                    // Google server-side requires gclid; simulate with test gclid if missing
                    $testLead = array_merge($leadData, ['click_id' => 'TESTGCLID123']);
                    $resp = GoogleApi::sendConversion($eventType, $testLead, $cfg);
                    $results['google'] = [
                        'success'  => true,
                        'message'  => 'Conversion payload siap (gclid: TESTGCLID123). Upload memerlukan OAuth2.',
                        'response' => $resp,
                    ];
                    Logger::log('Google Ads CAPI', $eventType, 'google_ads_conversion_upload', $testLead, ['code'=>200,'body'=>json_encode(['note'=>'Test payload. OAuth2 required for live upload.'])]);
                    break;
            }
        }
    }
}

// ── Build pixel availability map ──────────────────────────────────────────
$campPixels = [];
foreach ($campaigns as $c) {
    $px = $c->pixel_config ? json_decode($c->pixel_config, true) : [];
    if (!is_array($px)) $px = [];
    $campPixels[$c->id] = [
        'meta'   => !empty($px['meta']['pixel_id'])   && !empty($px['meta']['token']),
        'tiktok' => !empty($px['tiktok']['pixel_id']) && !empty($px['tiktok']['access_token']),
        'snack'  => !empty($px['snack']['pixel_id'])  && !empty($px['snack']['access_token']),
        'google' => !empty($px['google']['conversion_id']) || !empty($px['google']['gtm_id']) || !empty($px['google']['ga4_id']),
        'type'   => $c->type,
        'name'   => $c->name,
    ];
}

include dirname(__DIR__, 2) . '/inc/head.php';
include dirname(__DIR__, 2) . '/inc/sidebar.php';
?>

<div class="page-wrapper">

  <div class="page-header">
    <div>
      <h1 class="page-title">Test Pixel &amp; Tracking</h1>
      <p class="page-desc">Kirim test event server-side dan preview kode pixel browser-side</p>
    </div>
  </div>

  <?php include dirname(__DIR__, 2) . '/inc/flash.php'; ?>

  <form method="POST" id="ptForm" onsubmit="return PT.beforeSubmit()">
  <div style="display:grid;grid-template-columns:340px 1fr;gap:20px;align-items:start;">

    <!-- ── Left panel ── -->
    <div style="display:flex;flex-direction:column;gap:16px;">

      <!-- Campaign -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Pilih Kampanye</h3>
        </div>
        <div class="card-content">
          <?php if (empty($campaigns)): ?>
          <p style="color:hsl(var(--muted-foreground));font-size:13px;">Belum ada kampanye aktif.</p>
          <?php else: ?>
          <select name="campaign_id" id="sel_campaign" class="input" onchange="PT.onCampaignChange(this.value)">
            <option value="">-- Pilih Kampanye --</option>
            <?php foreach ($campaigns as $c): ?>
            <option value="<?= (int)$c->id ?>" <?= $lastCampId === $c->id ? 'selected' : '' ?>>
              <?= ae($c->name) ?> (<?= $c->type === 'wa_link' ? 'Link' : 'Form' ?>)
            </option>
            <?php endforeach; ?>
          </select>
          <div id="camp_info" style="margin-top:8px;font-size:11px;color:hsl(var(--muted-foreground));display:none;padding:6px 10px;background:hsl(var(--muted));border-radius:var(--radius);"></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Event type -->
      <div class="card">
        <div class="card-header"><h3 class="card-title">Jenis Event</h3></div>
        <div class="card-content" style="display:flex;flex-direction:column;gap:8px;">
          <?php
          $events = [
            'page_load'   => ['Page Load',   'Simulasi kunjungan halaman'],
            'form_submit' => ['Form Submit',  'Simulasi pengisian / klik tombol'],
            'thanks_page' => ['Thanks Page',  'Simulasi halaman terima kasih'],
          ];
          foreach ($events as $ev => [$evLbl, $evDesc]):
          ?>
          <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid hsl(var(--border));border-radius:var(--radius);cursor:pointer;transition:background .15s;"
            onmouseenter="this.style.background='hsl(var(--muted))'" onmouseleave="this.style.background=''">
            <input type="radio" name="event_type" value="<?= $ev ?>"
              <?= ($lastEvent === $ev || ($lastCampId === 0 && $ev === 'form_submit')) ? 'checked' : '' ?>
              onchange="PT.onEventChange(this.value)"
              style="accent-color:hsl(var(--primary));">
            <div>
              <div id="ev_lbl_<?= $ev ?>" style="font-size:13px;font-weight:500;"><?= ae($evLbl) ?></div>
              <div id="ev_desc_<?= $ev ?>" style="font-size:11px;color:hsl(var(--muted-foreground));"><?= ae($evDesc) ?></div>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Platform checkboxes -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Platform</h3>
          <p class="card-desc" id="plat_desc">Pilih kampanye untuk melihat platform yang aktif</p>
        </div>
        <div class="card-content" style="display:flex;flex-direction:column;gap:8px;">
          <?php foreach (['meta'=>'Meta / Facebook CAPI','tiktok'=>'TikTok Events API','snack'=>'SnackVideo / Kwai','google'=>'Google Ads / GTM / GA4'] as $plat=>$platLabel): ?>
          <label id="plat_row_<?= $plat ?>" style="display:flex;align-items:center;gap:10px;padding:10px 12px;border:1px solid hsl(var(--border));border-radius:var(--radius);opacity:.5;cursor:not-allowed;">
            <input type="checkbox" name="platforms[]" value="<?= $plat ?>" id="chk_<?= $plat ?>"
              style="accent-color:hsl(var(--primary));" disabled
              <?php if (!empty($_POST['platforms']) && in_array($plat, $_POST['platforms'])) echo 'checked'; ?>>
            <div style="flex:1;">
              <div style="font-size:13px;font-weight:500;"><?= ae($platLabel) ?></div>
              <div id="plat_status_<?= $plat ?>" style="font-size:11px;color:hsl(var(--muted-foreground));">Pilih kampanye dulu</div>
            </div>
            <span id="plat_badge_<?= $plat ?>" class="badge badge-secondary" style="font-size:10px;">—</span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Test data -->
      <div class="card">
        <div class="card-header"><h3 class="card-title">Data Test</h3></div>
        <div class="card-content" style="display:flex;flex-direction:column;gap:10px;">
          <div>
            <label class="label" style="font-size:11px;">Nama</label>
            <input name="td_name" class="input" value="<?= ae(isset($_POST['td_name']) ? $_POST['td_name'] : 'Test User') ?>" style="font-size:12px;padding:6px 10px;">
          </div>
          <div>
            <label class="label" style="font-size:11px;">No HP</label>
            <input name="td_phone" class="input" value="<?= ae(isset($_POST['td_phone']) ? $_POST['td_phone'] : '081234567890') ?>" style="font-size:12px;padding:6px 10px;">
          </div>
          <div>
            <label class="label" style="font-size:11px;">Email</label>
            <input name="td_email" class="input" value="<?= ae(isset($_POST['td_email']) ? $_POST['td_email'] : 'test@example.com') ?>" style="font-size:12px;padding:6px 10px;">
          </div>
          <div>
            <label class="label" style="font-size:11px;">URL Sumber</label>
            <input name="td_url" class="input" value="<?= ae(isset($_POST['td_url']) ? $_POST['td_url'] : Helper::siteUrl()) ?>" style="font-size:12px;padding:6px 10px;">
          </div>
        </div>
      </div>

      <button type="submit" id="btnFire" class="btn btn-default" style="width:100%;" disabled>
        <?= icon('send-horizontal') ?> Kirim Test Event
      </button>

    </div><!-- /left -->

    <!-- ── Right panel ── -->
    <div style="display:flex;flex-direction:column;gap:16px;">

      <!-- Results -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Hasil Test</h3>
          <p class="card-desc">Response dari setiap platform</p>
        </div>
        <div class="card-content" style="display:flex;flex-direction:column;gap:12px;min-height:200px;">

          <?php if (empty($results) && $lastCampId === 0): ?>
          <div style="text-align:center;padding:40px 20px;color:hsl(var(--muted-foreground));">
            <?= icon('test-tube-2','',32) ?>
            <p style="margin-top:12px;font-size:14px;">Pilih kampanye dan klik "Kirim Test Event"</p>
          </div>

          <?php elseif (empty($results) && $lastCampId > 0): ?>
          <div style="text-align:center;padding:40px 20px;color:hsl(var(--muted-foreground));">
            <?= icon('alert-circle','',24) ?>
            <p style="margin-top:8px;font-size:13px;">Tidak ada platform yang dipilih atau kampanye tidak ditemukan.</p>
          </div>

          <?php else: ?>
          <?php foreach ($results as $plat => $res):
            $platNames = ['meta'=>'Meta / Facebook CAPI','tiktok'=>'TikTok Events API','snack'=>'SnackVideo / Kwai','google'=>'Google Ads / GTM / GA4'];
            $platName  = isset($platNames[$plat]) ? $platNames[$plat] : $plat;
            $ok        = !empty($res['success']);
          ?>
          <div style="padding:16px;border-radius:var(--radius);border:1px solid <?= $ok ? 'hsl(142 76% 36%/.3)' : 'hsl(0 84% 60%/.3)' ?>;background:<?= $ok ? 'hsl(142 76% 36%/.05)' : 'hsl(0 84% 60%/.05)' ?>;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
              <span style="color:<?= $ok ? 'hsl(142 76% 36%)' : 'hsl(0 72% 51%)' ?>;"><?= $ok ? icon('check-circle-2') : icon('x-circle') ?></span>
              <span style="font-size:14px;font-weight:600;"><?= ae($platName) ?></span>
              <span class="badge <?= $ok ? 'badge-success' : 'badge-destructive' ?>" style="margin-left:auto;"><?= $ok ? 'Berhasil' : 'Gagal' ?></span>
            </div>
            <p style="font-size:12px;color:hsl(var(--foreground));margin:0 0 <?= $res['response'] ? '10px' : '0' ?>;"><?= ae($res['message']) ?></p>
            <?php if ($res['response']): ?>
            <details style="margin-top:6px;">
              <summary style="font-size:11px;color:hsl(var(--muted-foreground));cursor:pointer;user-select:none;">Lihat response API</summary>
              <pre style="margin-top:6px;font-size:11px;background:hsl(var(--muted));padding:10px 12px;border-radius:6px;overflow-x:auto;white-space:pre-wrap;max-height:250px;overflow-y:auto;"><?= ae(json_encode($res['response'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            </details>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
          <?php endif; ?>

        </div>
      </div>

      <!-- Pixel Code Preview -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Preview Kode Pixel</h3>
          <p class="card-desc">Kode pixel browser-side yang akan diinject untuk event terpilih</p>
        </div>
        <div class="card-content" id="pixelPreviewBox" style="display:flex;flex-direction:column;gap:12px;min-height:180px;">
          <div style="text-align:center;padding:40px 20px;color:hsl(var(--muted-foreground));">
            <?= icon('code','',24) ?>
            <p style="margin-top:8px;font-size:13px;">Pilih kampanye dan event untuk melihat kode pixel</p>
          </div>
        </div>
      </div>

      <!-- Payload summary -->
      <?php if ($lastPayload): ?>
      <div class="card">
        <div class="card-header"><h3 class="card-title">Payload yang Dikirim</h3></div>
        <div class="card-content">
          <pre style="background:hsl(var(--muted));padding:12px 14px;border-radius:var(--radius);font-size:11px;overflow-x:auto;white-space:pre-wrap;margin:0;max-height:280px;overflow-y:auto;"><?= ae(json_encode($lastPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
        </div>
      </div>
      <?php endif; ?>

      <!-- Riwayat Test & Log API -->
      <div class="card">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
          <div>
            <h3 class="card-title">Riwayat Test &amp; Log API</h3>
            <p class="card-desc">Log API 20 terakhir (test + produksi)</p>
          </div>
        </div>
        <div class="card-content" style="padding:0;">
          <?php if (empty($testLogs)): ?>
            <p style="padding:20px;text-align:center;color:hsl(var(--muted-foreground));font-size:13px;">Belum ada log.</p>
          <?php else: ?>
          <div class="table-wrapper" style="margin:0;">
            <table style="font-size:12px;">
              <thead>
                <tr><th>Sumber</th><th>Event</th><th>Status</th><th>Waktu</th></tr>
              </thead>
              <tbody>
                <?php foreach ($testLogs as $log): ?>
                <tr>
                  <td style="font-weight:500;"><?= ae($log->source) ?></td>
                  <td class="td-muted"><?= ae($log->event_name) ?></td>
                  <td>
                    <?php if ($log->success): ?>
                    <span class="badge badge-success"><?= icon('check','',10) ?> <?= (int)$log->status_code ?></span>
                    <?php else: ?>
                    <span class="badge badge-destructive"><?= icon('x','',10) ?> <?= (int)$log->status_code ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="td-muted"><?= date('d/m H:i', strtotime($log->created_at)) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div><!-- /right -->

  </div>
  </form>

</div>

<script>
var CAMP_PIXELS = <?= json_encode($campPixels, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>;

var PT = {
  onCampaignChange: function(id) {
    id = parseInt(id) || 0;
    var plats = ['meta','tiktok','snack','google'];
    var btn   = document.getElementById('btnFire');
    var info  = document.getElementById('camp_info');
    var pdesc = document.getElementById('plat_desc');

    if (!id) {
      plats.forEach(function(p) {
        var row = document.getElementById('plat_row_'+p);
        var chk = document.getElementById('chk_'+p);
        var st  = document.getElementById('plat_status_'+p);
        var bg  = document.getElementById('plat_badge_'+p);
        row.style.opacity = '.5'; row.style.cursor = 'not-allowed';
        chk.disabled = true; chk.checked = false;
        st.textContent = 'Pilih kampanye dulu';
        bg.textContent = '—'; bg.className = 'badge badge-secondary';
      });
      btn.disabled = true;
      if (info) info.style.display = 'none';
      PT.loadPreview(0, '');
      return;
    }

    var data = CAMP_PIXELS[id] || {};
    var isWa = (data.type === 'wa_link');
    var anyOk = false;

    // Update event label step 2
    var lbl2  = document.getElementById('ev_lbl_form_submit');
    var desc2 = document.getElementById('ev_desc_form_submit');
    if (lbl2)  lbl2.textContent  = isWa ? 'Link Clicked' : 'Form Submit';
    if (desc2) desc2.textContent = isWa ? 'Simulasi klik tombol link ke CS' : 'Simulasi pengisian form';

    // Campaign type info
    if (info) {
      info.textContent = (isWa ? 'Tipe: Link — ' : 'Tipe: Form Lead — ') + (data.name || '');
      info.style.display = 'block';
    }

    plats.forEach(function(p) {
      var ok  = !!data[p];
      var row = document.getElementById('plat_row_'+p);
      var chk = document.getElementById('chk_'+p);
      var st  = document.getElementById('plat_status_'+p);
      var bg  = document.getElementById('plat_badge_'+p);
      row.style.opacity = ok ? '1' : '.5';
      row.style.cursor  = ok ? 'pointer' : 'not-allowed';
      chk.disabled = !ok;
      chk.checked  = ok;
      st.textContent = ok ? 'Terkonfigurasi — siap dikirim' : 'Pixel belum dikonfigurasi';
      bg.textContent = ok ? 'Aktif' : 'Tidak Aktif';
      bg.className   = ok ? 'badge badge-success' : 'badge badge-secondary';
      if (ok) anyOk = true;
    });

    if (pdesc) pdesc.textContent = anyOk ? 'Pilih platform yang ingin diuji' : 'Tidak ada pixel yang dikonfigurasi di kampanye ini';
    btn.disabled = !anyOk;

    // Load pixel preview
    var ev = document.querySelector('input[name="event_type"]:checked');
    PT.loadPreview(id, ev ? ev.value : 'form_submit');
  },

  onEventChange: function(ev) {
    var camp = document.getElementById('sel_campaign').value;
    PT.loadPreview(parseInt(camp) || 0, ev);
  },

  loadPreview: function(campId, eventType) {
    var box = document.getElementById('pixelPreviewBox');
    if (!campId || !eventType) {
      box.innerHTML = '<div style="text-align:center;padding:40px 20px;color:hsl(var(--muted-foreground));">' +
        '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>' +
        '<p style="margin-top:8px;font-size:13px;">Pilih kampanye dan event untuk melihat kode pixel</p></div>';
      return;
    }
    box.innerHTML = '<div style="text-align:center;padding:30px;color:hsl(var(--muted-foreground));"><p style="font-size:13px;">Memuat preview...</p></div>';
    fetch('pixel-preview-ajax.php?campaign_id=' + campId + '&event_type=' + encodeURIComponent(eventType))
      .then(function(r){ return r.json(); })
      .then(function(res){
        box.innerHTML = res.html || '<p style="text-align:center;color:hsl(var(--muted-foreground));font-size:13px;padding:20px;">Tidak ada pixel yang dikonfigurasi.</p>';
      })
      .catch(function(){
        box.innerHTML = '<p style="text-align:center;color:hsl(var(--destructive));font-size:13px;padding:20px;">Gagal memuat preview.</p>';
      });
  },

  beforeSubmit: function() {
    var checked = document.querySelectorAll('input[name="platforms[]"]:checked');
    if (!checked.length) { alert('Pilih minimal satu platform.'); return false; }
    var camp = document.getElementById('sel_campaign').value;
    if (!camp) { alert('Pilih kampanye terlebih dahulu.'); return false; }
    document.getElementById('btnFire').disabled = true;
    document.getElementById('btnFire').innerHTML = '<?= addslashes(icon('loader-2')) ?> Mengirim...';
    return true;
  }
};

// Init if campaign pre-selected (after POST)
<?php if ($lastCampId): ?>
PT.onCampaignChange(<?= $lastCampId ?>);
<?php endif; ?>
</script>

<?php include dirname(__DIR__, 2) . '/inc/foot.php'; ?>

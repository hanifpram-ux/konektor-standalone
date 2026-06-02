<?php
require_once dirname(__DIR__, 3) . '/admin/inc/bootstrap.php';
$pageTitle = 'Pengaturan';

function currentSiteUrl() {
    $scheme = knk_is_https() ? 'https' : 'http';
    $host   = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    return rtrim($scheme . '://' . $host, '/');
}

function fetchTelegramWebhookInfo($token) {
    if (!$token) return null;
    $ch = curl_init("https://api.telegram.org/bot{$token}/getWebhookInfo");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true]);
    $body = curl_exec($ch); curl_close($ch);
    return $body ? json_decode($body, true) : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['_register_tg_webhook'])) {
        $tgToken = Settings::get('telegram_bot_token');
        if (!$tgToken) {
            flashSet('error', 'Token Telegram belum diisi.');
        } else {
            $resp = Helper::httpPost("https://api.telegram.org/bot{$tgToken}/setWebhook", ['url' => currentSiteUrl() . '/telegram/callback']);
            $body = $resp['body'] ? json_decode($resp['body'], true) : [];
            flashSet(!empty($body['ok']) ? 'success' : 'error', !empty($body['ok']) ? 'Webhook Telegram berhasil didaftarkan.' : 'Gagal: ' . htmlspecialchars($body['description'] ?? 'Unknown error'));
        }
        header('Location: index.php#telegram'); exit;
    }
    if (isset($_POST['_save'])) {
        Settings::set('site_url', currentSiteUrl());
        $allowed = ['app_name','cs_panel_slug','telegram_bot_token','encrypt_lead_data',
                    'webhook_url','webhook_secret','timezone','double_lead_scope','cron_secret'];
        foreach ($allowed as $k) {
            if (isset($_POST[$k])) Settings::set($k, strip_tags(trim($_POST[$k])));
        }
        if (!empty($_POST['timezone'])) @date_default_timezone_set($_POST['timezone']);
        flashSet('success', 'Pengaturan disimpan.');
        header('Location: index.php'); exit;
    }
    if (isset($_POST['_pw'])) {
        $user = DB::row("SELECT * FROM " . DB::t('users') . " WHERE id = ?", [Auth::id()]);
        if (!$user || !password_verify($_POST['old_pass'] ?? '', $user->password_hash)) {
            flashSet('error', 'Password lama salah.');
        } elseif (strlen($_POST['new_pass'] ?? '') < 8) {
            flashSet('error', 'Password baru minimal 8 karakter.');
        } elseif (($_POST['new_pass'] ?? '') !== ($_POST['new_pass2'] ?? '')) {
            flashSet('error', 'Konfirmasi password tidak cocok.');
        } else {
            Auth::changePassword(Auth::id(), $_POST['new_pass']);
            flashSet('success', 'Password berhasil diubah.');
        }
        header('Location: index.php#akun'); exit;
    }
    header('Location: index.php'); exit;
}

$s          = Settings::all();
$currentUrl = currentSiteUrl();
$tgWebhookInfo = !empty($s['telegram_bot_token']) ? fetchTelegramWebhookInfo($s['telegram_bot_token']) : null;
$tgActive   = !empty($tgWebhookInfo['result']['url']);

$tz_current = $s['timezone'] ?? 'Asia/Jakarta';
$tz_groups  = [
    'Indonesia'       => ['Asia/Jakarta'=>'WIB — Indonesia Barat (UTC+7)','Asia/Makassar'=>'WITA — Indonesia Tengah (UTC+8)','Asia/Jayapura'=>'WIT — Indonesia Timur (UTC+9)'],
    'Asia'            => ['Asia/Singapore'=>'Singapore (UTC+8)','Asia/Kuala_Lumpur'=>'Kuala Lumpur (UTC+8)','Asia/Bangkok'=>'Bangkok (UTC+7)','Asia/Ho_Chi_Minh'=>'Ho Chi Minh (UTC+7)','Asia/Manila'=>'Manila (UTC+8)','Asia/Tokyo'=>'Tokyo (UTC+9)','Asia/Seoul'=>'Seoul (UTC+9)','Asia/Shanghai'=>'Shanghai (UTC+8)','Asia/Kolkata'=>'India (UTC+5:30)','Asia/Dubai'=>'Dubai (UTC+4)','Asia/Riyadh'=>'Riyadh (UTC+3)'],
    'Eropa & Amerika' => ['UTC'=>'UTC (UTC+0)','Europe/London'=>'London (UTC+0/+1)','Europe/Paris'=>'Paris (UTC+1/+2)','America/New_York'=>'New York (UTC-5/-4)','America/Los_Angeles'=>'Los Angeles (UTC-8/-7)'],
];
$dls = $s['double_lead_scope'] ?? 'campaign';

include dirname(__DIR__, 2) . '/inc/head.php';
include dirname(__DIR__, 2) . '/inc/sidebar.php';
?>

<style>
/* ── Settings layout ─────────────────────────────── */
.settings-layout {
  display: grid;
  grid-template-columns: 200px 1fr;
  gap: 28px;
  align-items: start;
  max-width: 900px;
}
.settings-nav {
  position: sticky;
  top: 28px;
}
.settings-nav a {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 7px 10px;
  border-radius: var(--radius);
  font-size: 13px;
  font-weight: 500;
  color: hsl(var(--muted-foreground));
  text-decoration: none;
  transition: background .12s, color .12s;
  margin-bottom: 2px;
}
.settings-nav a:hover { background: hsl(var(--muted)); color: hsl(var(--foreground)); }
.settings-nav a.active { background: hsl(var(--primary)/0.08); color: hsl(var(--primary)); font-weight: 600; }
.settings-nav a svg { width: 15px; height: 15px; flex-shrink: 0; }
.settings-nav-label {
  font-size: 10px;
  font-weight: 600;
  color: hsl(var(--muted-foreground));
  text-transform: uppercase;
  letter-spacing: .06em;
  padding: 12px 10px 4px;
}

/* ── Field row ───────────────────────────────────── */
.field-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}
.field-row.full { grid-template-columns: 1fr; }

/* ── Toggle switch ───────────────────────────────── */
.sw-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 14px 0;
  border-bottom: 1px solid hsl(var(--border));
}
.sw-row:last-child { border-bottom: none; padding-bottom: 0; }
.sw-row:first-child { padding-top: 0; }
.sw-info {}
.sw-info strong { display: block; font-size: 13px; font-weight: 500; color: hsl(var(--foreground)); margin-bottom: 2px; }
.sw-info span { font-size: 12px; color: hsl(var(--muted-foreground)); line-height: 1.5; }
.sw-toggle { position: relative; width: 44px; height: 24px; flex-shrink: 0; }
.sw-toggle input { opacity: 0; width: 0; height: 0; position: absolute; }
.sw-track {
  position: absolute; inset: 0;
  background: hsl(var(--border));
  border-radius: 999px;
  cursor: pointer;
  transition: background .2s;
}
.sw-toggle input:checked + .sw-track { background: hsl(var(--primary)); }
.sw-thumb {
  position: absolute;
  top: 3px; left: 3px;
  width: 18px; height: 18px;
  background: #fff;
  border-radius: 50%;
  box-shadow: 0 1px 4px rgba(0,0,0,.2);
  transition: left .2s;
  pointer-events: none;
}
.sw-toggle input:checked ~ .sw-thumb { left: 23px; }

/* ── Password input with toggle ──────────────────── */
.pw-wrap { position: relative; }
.pw-wrap .input { padding-right: 42px; }
.pw-toggle {
  position: absolute; right: 1px; top: 1px; bottom: 1px;
  width: 38px;
  display: flex; align-items: center; justify-content: center;
  background: none; border: none; cursor: pointer;
  color: hsl(var(--muted-foreground));
  border-radius: 0 calc(var(--radius) - 1px) calc(var(--radius) - 1px) 0;
  transition: color .12s;
}
.pw-toggle:hover { color: hsl(var(--foreground)); }

/* ── Info box ────────────────────────────────────── */
.info-box {
  padding: 12px 14px;
  border-radius: var(--radius);
  font-size: 12px;
  line-height: 1.7;
  border: 1px solid;
}
.info-box-muted  { background: hsl(var(--muted)); border-color: hsl(var(--border)); color: hsl(var(--muted-foreground)); }
.info-box-green  { background: hsl(142 76% 36%/0.07); border-color: hsl(142 76% 36%/0.25); color: hsl(142 76% 28%); }
.info-box-yellow { background: hsl(38 92% 50%/0.08); border-color: hsl(38 92% 50%/0.25); color: hsl(38 60% 40%); }

/* ── Section anchor offset ───────────────────────── */
.section-anchor { scroll-margin-top: 28px; }

/* ── Sys table ───────────────────────────────────── */
.sys-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.sys-table tr { border-bottom: 1px solid hsl(var(--border)); }
.sys-table tr:last-child { border-bottom: none; }
.sys-table td { padding: 9px 0; }
.sys-table td:last-child { text-align: right; font-weight: 500; word-break: break-all; }
</style>

<div class="page-wrapper">
  <div class="page-header">
    <div>
      <h1 class="page-title">Pengaturan</h1>
      <p class="page-desc">Konfigurasi sistem Konektor</p>
    </div>
  </div>

  <?php include dirname(__DIR__, 2) . '/inc/flash.php'; ?>

  <div class="settings-layout">

    <!-- ── Sticky sidebar nav ──────────────────────── -->
    <nav class="settings-nav">
      <div class="settings-nav-label">Pengaturan</div>
      <a href="#umum"     class="active" data-sec="umum">    <?= icon('settings','',15) ?>   Umum</a>
      <a href="#keamanan" data-sec="keamanan">               <?= icon('shield','',15) ?>     Keamanan &amp; Data</a>
      <a href="#telegram" data-sec="telegram">               <?= icon('send','',15) ?>       Telegram Bot
        <?php if ($tgActive): ?><span class="badge badge-success" style="font-size:9px;padding:1px 6px;">ON</span><?php endif; ?>
      </a>
      <a href="#webhook"  data-sec="webhook">                <?= icon('zap','',15) ?>        Webhook</a>
      <div class="settings-nav-label" style="margin-top:8px;">Akun</div>
      <a href="#akun"     data-sec="akun">                   <?= icon('lock','',15) ?>       Password</a>
      <div class="settings-nav-label" style="margin-top:8px;">Sistem</div>
      <a href="#sistem"   data-sec="sistem">                 <?= icon('info','',15) ?>       Info Sistem</a>
    </nav>

    <!-- ── Main content ────────────────────────────── -->
    <div style="display:flex;flex-direction:column;gap:20px;">

      <!-- ① Umum ───────────────────────────────────── -->
      <section id="umum" class="section-anchor">
        <form method="POST">
          <input type="hidden" name="_save" value="1">
          <div class="card">
            <div class="card-header" style="padding-bottom:16px;border-bottom:1px solid hsl(var(--border));">
              <h3 class="card-title"><?= icon('settings','',15) ?> Umum</h3>
            </div>
            <div class="card-content" style="display:flex;flex-direction:column;gap:16px;">

              <div class="field-row">
                <div>
                  <label class="label">Nama Aplikasi</label>
                  <input name="app_name" value="<?= ae($s['app_name'] ?? 'Konektor') ?>" class="input" placeholder="Konektor">
                </div>
                <div>
                  <label class="label">CS Panel Slug</label>
                  <input name="cs_panel_slug" id="cs_slug_inp" value="<?= ae($s['cs_panel_slug'] ?? 'cs-panel') ?>" class="input" placeholder="cs-panel" oninput="document.getElementById('cs_slug_preview').textContent=this.value||'cs-panel'">
                  <p class="form-description"><?= ae($currentUrl) ?>/<strong id="cs_slug_preview"><?= ae($s['cs_panel_slug'] ?? 'cs-panel') ?></strong>/TOKEN</p>
                </div>
              </div>

              <div>
                <label class="label">Zona Waktu</label>
                <select name="timezone" class="input">
                  <?php foreach ($tz_groups as $group => $zones): ?>
                  <optgroup label="<?= ae($group) ?>">
                    <?php foreach ($zones as $tz => $label): ?>
                    <option value="<?= ae($tz) ?>" <?= $tz_current === $tz ? 'selected' : '' ?>><?= ae($label) ?></option>
                    <?php endforeach; ?>
                  </optgroup>
                  <?php endforeach; ?>
                </select>
                <p class="form-description">Server: <strong><?= ae(date_default_timezone_get()) ?></strong> &mdash; sekarang <?= date('d/m/Y H:i:s') ?></p>
              </div>

              <div>
                <label class="label">URL Kampanye</label>
                <div class="input" style="background:hsl(var(--muted));color:hsl(var(--muted-foreground));font-family:monospace;cursor:default;user-select:all;">
                  <?= ae($currentUrl) ?>/k/<em style="opacity:.6;">nama-kampanye</em>
                </div>
                <p class="form-description">Diambil otomatis dari domain aktif.</p>
              </div>

            </div>
            <div class="card-footer" style="justify-content:flex-end;border-top:1px solid hsl(var(--border));padding-top:16px;">
              <button type="submit" class="btn btn-default"><?= icon('save','',14) ?> Simpan</button>
            </div>
          </div>
        </form>
      </section>

      <!-- ② Keamanan & Data ─────────────────────────── -->
      <section id="keamanan" class="section-anchor">
        <form method="POST">
          <input type="hidden" name="_save" value="1">
          <div class="card">
            <div class="card-header" style="padding-bottom:16px;border-bottom:1px solid hsl(var(--border));">
              <h3 class="card-title"><?= icon('shield','',15) ?> Keamanan &amp; Data</h3>
            </div>
            <div class="card-content">

              <div class="sw-row">
                <div class="sw-info">
                  <strong>Enkripsi Data Lead</strong>
                  <span>Nama, HP, email, dan alamat dienkripsi AES-256-CBC di database.</span>
                </div>
                <?php $encOn = ($s['encrypt_lead_data'] ?? '1') === '1'; ?>
                <input type="hidden" name="encrypt_lead_data" id="enc_val" value="<?= $encOn ? '1' : '0' ?>">
                <label class="sw-toggle" onclick="var h=document.getElementById('enc_val');var cb=this.querySelector('input');setTimeout(function(){h.value=cb.checked?'1':'0';},0);">
                  <input type="checkbox" <?= $encOn ? 'checked' : '' ?>>
                  <span class="sw-track"></span>
                  <span class="sw-thumb"></span>
                </label>
              </div>

              <div style="margin-top:16px;">
                <label class="label">Deteksi Double Lead</label>
                <div style="display:flex;flex-direction:column;gap:6px;margin-top:4px;">
                  <?php
                  $dlsOpts = [
                    'campaign' => ['Per Kampanye',  'Setiap kampanye dicek sendiri — paling longgar.'],
                    'domain'   => ['Per Domain',    'Satu submit di domain ini = double untuk semua kampanye di domain yang sama.'],
                    'page'     => ['Per Halaman',   'Satu submit di halaman ini = double untuk semua kampanye di URL yang sama.'],
                  ];
                  foreach ($dlsOpts as $v => [$title, $desc]):
                  ?>
                  <label style="display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border:1.5px solid <?= $dls===$v?'hsl(var(--primary))':'hsl(var(--border))' ?>;border-radius:var(--radius);cursor:pointer;transition:border-color .15s;" class="dls-opt" data-val="<?= $v ?>">
                    <input type="radio" name="double_lead_scope" value="<?= $v ?>" <?= $dls===$v?'checked':'' ?> style="margin-top:1px;accent-color:hsl(var(--primary));flex-shrink:0;">
                    <div>
                      <div style="font-size:13px;font-weight:500;"><?= $title ?></div>
                      <div style="font-size:11px;color:hsl(var(--muted-foreground));margin-top:1px;"><?= $desc ?></div>
                    </div>
                  </label>
                  <?php endforeach; ?>
                </div>
              </div>

            </div>
            <div class="card-footer" style="justify-content:flex-end;border-top:1px solid hsl(var(--border));padding-top:16px;">
              <button type="submit" class="btn btn-default"><?= icon('save','',14) ?> Simpan</button>
            </div>
          </div>
        </form>
      </section>

      <!-- ③ Telegram Bot ────────────────────────────── -->
      <section id="telegram" class="section-anchor">
        <form method="POST">
          <input type="hidden" name="_save" value="1">
          <div class="card">
            <div class="card-header" style="padding-bottom:16px;border-bottom:1px solid hsl(var(--border));">
              <div style="display:flex;align-items:center;gap:10px;">
                <h3 class="card-title"><?= icon('send','',15) ?> Telegram Bot</h3>
                <?php if ($tgActive): ?>
                <span class="badge badge-success">Aktif</span>
                <?php elseif (!empty($s['telegram_bot_token'])): ?>
                <span class="badge badge-warning">Belum didaftarkan</span>
                <?php else: ?>
                <span class="badge badge-secondary">Belum setup</span>
                <?php endif; ?>
              </div>
              <p class="card-desc">Notifikasi lead baru ke operator via Telegram</p>
            </div>
            <div class="card-content" style="display:flex;flex-direction:column;gap:14px;">

              <div>
                <label class="label">Bot Token</label>
                <div class="pw-wrap">
                  <input name="telegram_bot_token" type="password" id="tg_token" value="<?= ae($s['telegram_bot_token'] ?? '') ?>" class="input" placeholder="1234567890:ABCDef...">
                  <button type="button" class="pw-toggle" onclick="togglePw('tg_token',this)" tabindex="-1"><?= icon('eye','',15) ?></button>
                </div>
              </div>

              <?php if ($tgActive): ?>
              <div class="info-box info-box-green">
                <div style="display:flex;align-items:center;gap:6px;font-weight:600;margin-bottom:4px;"><?= icon('check-circle','',13) ?> Webhook aktif</div>
                <code style="font-size:11px;word-break:break-all;opacity:.8;"><?= ae($tgWebhookInfo['result']['url'] ?? '') ?></code>
                <?php if (!empty($tgWebhookInfo['result']['last_error_message'])): ?>
                <div style="color:hsl(0 72% 50%);margin-top:6px;font-size:11px;"><?= icon('alert-circle','',12) ?> <?= ae($tgWebhookInfo['result']['last_error_message']) ?></div>
                <?php endif; ?>
              </div>
              <?php elseif (!empty($s['telegram_bot_token'])): ?>
              <div class="info-box info-box-yellow">
                Simpan token terlebih dahulu, lalu klik <strong>Daftarkan Webhook</strong> di bawah.
              </div>
              <?php else: ?>
              <div class="info-box info-box-muted">
                <strong style="color:hsl(var(--foreground));">Cara setup:</strong><br>
                1. Buka Telegram &rarr; cari <strong>@BotFather</strong> &rarr; ketik <code>/newbot</code><br>
                2. Salin Bot Token dan tempel di sini, lalu <strong>Simpan</strong><br>
                3. Klik <strong>Daftarkan Webhook</strong><br>
                4. Isi <strong>Telegram Chat ID</strong> di profil masing-masing operator<br>
                <span style="opacity:.7;">(gunakan <strong>@userinfobot</strong> untuk tahu Chat ID Anda)</span>
              </div>
              <?php endif; ?>

            </div>
              <div style="border-top:1px solid hsl(var(--border));padding-top:14px;margin-top:2px;">
                <label class="label">Cron Secret <span style="font-weight:400;color:hsl(var(--muted-foreground));">(untuk rekap harian)</span></label>
                <div class="pw-wrap">
                  <input name="cron_secret" type="password" id="cron_secret" value="<?= ae($s['cron_secret'] ?? '') ?>" class="input" placeholder="Isi token rahasia sembarang, min 16 karakter">
                  <button type="button" class="pw-toggle" onclick="togglePw('cron_secret',this)" tabindex="-1"><?= icon('eye','',15) ?></button>
                </div>
                <p class="form-description">
                  Panggil endpoint ini setiap hari jam 14.00 WIB via cron server atau layanan penjadwal:<br>
                  <code style="font-size:11px;word-break:break-all;"><?= ae(currentSiteUrl()) ?>/api/cron/daily-recap?secret=<strong><?= ae($s['cron_secret'] ?? 'YOUR_SECRET') ?></strong></code>
                </p>
              </div>

            </div>
            <div class="card-footer" style="justify-content:space-between;border-top:1px solid hsl(var(--border));padding-top:16px;">
              <button type="submit" name="_register_tg_webhook" value="1" class="btn btn-outline btn-sm"><?= icon('send','',13) ?> Daftarkan Webhook</button>
              <button type="submit" class="btn btn-default"><?= icon('save','',14) ?> Simpan</button>
            </div>
          </div>
        </form>
      </section>

      <!-- ④ Webhook Eksternal ──────────────────────── -->
      <section id="webhook" class="section-anchor">
        <form method="POST">
          <input type="hidden" name="_save" value="1">
          <div class="card">
            <div class="card-header" style="padding-bottom:16px;border-bottom:1px solid hsl(var(--border));">
              <h3 class="card-title"><?= icon('zap','',15) ?> Webhook Eksternal</h3>
              <p class="card-desc">Push data lead baru ke CRM, Zapier, Make.com, atau sistem lainnya secara real-time</p>
            </div>
            <div class="card-content" style="display:flex;flex-direction:column;gap:14px;">

              <div>
                <label class="label">Webhook URL</label>
                <input name="webhook_url" value="<?= ae($s['webhook_url'] ?? '') ?>" class="input" placeholder="https://hooks.zapier.com/hooks/catch/...">
                <p class="form-description">POST JSON dikirim setiap kali ada lead baru masuk. Kosongkan untuk menonaktifkan.</p>
              </div>

              <div>
                <label class="label">Secret <span style="font-weight:400;color:hsl(var(--muted-foreground));">(opsional)</span></label>
                <div class="pw-wrap">
                  <input name="webhook_secret" type="password" id="wh_secret" value="<?= ae($s['webhook_secret'] ?? '') ?>" class="input" placeholder="sk_xxxxxxxxxxxxxxxx">
                  <button type="button" class="pw-toggle" onclick="togglePw('wh_secret',this)" tabindex="-1"><?= icon('eye','',15) ?></button>
                </div>
                <p class="form-description">Jika diisi, header <code>X-Konektor-Signature: sha256=HMAC</code> dikirim untuk verifikasi.</p>
              </div>

            </div>
            <div class="card-footer" style="justify-content:flex-end;border-top:1px solid hsl(var(--border));padding-top:16px;">
              <button type="submit" class="btn btn-default"><?= icon('save','',14) ?> Simpan</button>
            </div>
          </div>
        </form>
      </section>

      <!-- ⑤ Password ───────────────────────────────── -->
      <section id="akun" class="section-anchor">
        <form method="POST">
          <input type="hidden" name="_pw" value="1">
          <div class="card">
            <div class="card-header" style="padding-bottom:16px;border-bottom:1px solid hsl(var(--border));">
              <h3 class="card-title"><?= icon('lock','',15) ?> Ganti Password Admin</h3>
            </div>
            <div class="card-content" style="display:flex;flex-direction:column;gap:14px;">

              <div>
                <label class="label">Password Saat Ini</label>
                <div class="pw-wrap">
                  <input type="password" name="old_pass" id="pw_old" class="input" required autocomplete="current-password" placeholder="••••••••">
                  <button type="button" class="pw-toggle" onclick="togglePw('pw_old',this)" tabindex="-1"><?= icon('eye','',15) ?></button>
                </div>
              </div>

              <div class="field-row">
                <div>
                  <label class="label">Password Baru</label>
                  <div class="pw-wrap">
                    <input type="password" name="new_pass" id="pw_new" class="input" minlength="8" required autocomplete="new-password" placeholder="Min. 8 karakter">
                    <button type="button" class="pw-toggle" onclick="togglePw('pw_new',this)" tabindex="-1"><?= icon('eye','',15) ?></button>
                  </div>
                </div>
                <div>
                  <label class="label">Konfirmasi Password Baru</label>
                  <div class="pw-wrap">
                    <input type="password" name="new_pass2" id="pw_new2" class="input" required autocomplete="new-password" placeholder="Ulangi password baru">
                    <button type="button" class="pw-toggle" onclick="togglePw('pw_new2',this)" tabindex="-1"><?= icon('eye','',15) ?></button>
                  </div>
                </div>
              </div>

            </div>
            <div class="card-footer" style="justify-content:flex-end;border-top:1px solid hsl(var(--border));padding-top:16px;">
              <button type="submit" class="btn btn-default"><?= icon('lock','',14) ?> Ganti Password</button>
            </div>
          </div>
        </form>
      </section>

      <!-- ⑥ Info Sistem ────────────────────────────── -->
      <section id="sistem" class="section-anchor">
        <div class="card">
          <div class="card-header" style="padding-bottom:16px;border-bottom:1px solid hsl(var(--border));">
            <h3 class="card-title"><?= icon('info','',15) ?> Informasi Sistem</h3>
          </div>
          <div class="card-content">
            <table class="sys-table">
              <?php
              $sysInfo = [
                ['Versi Konektor', KONEKTOR_VERSION],
                ['PHP',            PHP_VERSION],
                ['Database',       KONEKTOR_DB_NAME . '@' . KONEKTOR_DB_HOST],
                ['Table Prefix',   KONEKTOR_DB_PREFIX],
                ['URL Aktif',      $currentUrl],
                ['OpenSSL',        extension_loaded('openssl') ? '✓ Aktif' : '✗ Tidak tersedia'],
                ['cURL',           extension_loaded('curl')    ? '✓ Aktif' : '✗ Tidak tersedia'],
                ['Zona Waktu',     date_default_timezone_get() . ' — ' . date('d/m/Y H:i:s')],
                ['Total Lead',     number_format(Lead::count())],
                ['Total Kampanye', number_format(DB::count('campaigns'))],
                ['Total Operator', number_format(DB::count('operators'))],
              ];
              $mono = ['Database','Table Prefix','URL Aktif'];
              foreach ($sysInfo as [$lbl, $val]):
              ?>
              <tr>
                <td style="color:hsl(var(--muted-foreground));width:46%;"><?= ae($lbl) ?></td>
                <td style="font-family:<?= in_array($lbl,$mono)?'monospace':'inherit' ?>;"><?= ae($val) ?></td>
              </tr>
              <?php endforeach; ?>
            </table>
          </div>
        </div>
      </section>

    </div><!-- /main -->
  </div><!-- /settings-layout -->
</div><!-- /page-wrapper -->

<script>
// ── Password show/hide ────────────────────────────────
function togglePw(id, btn) {
  var el = document.getElementById(id);
  if (!el) return;
  var show = el.type === 'password';
  el.type  = show ? 'text' : 'password';
  btn.innerHTML = show
    ? '<?= addslashes(icon('eye-off','',15)) ?>'
    : '<?= addslashes(icon('eye','',15)) ?>';
  el.focus();
}

// ── Double lead scope: highlight selected card ────────
document.querySelectorAll('input[name=double_lead_scope]').forEach(function(r) {
  r.addEventListener('change', function() {
    document.querySelectorAll('.dls-opt').forEach(function(lbl) {
      lbl.style.borderColor = lbl.dataset.val === r.value
        ? 'hsl(var(--primary))'
        : 'hsl(var(--border))';
    });
  });
});

// ── Sidebar active link on scroll ────────────────────
(function() {
  var links = document.querySelectorAll('.settings-nav a[data-sec]');
  var secs  = Array.from(links).map(function(a) {
    return document.getElementById(a.dataset.sec);
  });
  function updateActive() {
    var scrollY = window.scrollY + 80;
    var cur = secs[0];
    secs.forEach(function(s) { if (s && s.offsetTop <= scrollY) cur = s; });
    links.forEach(function(a) {
      a.classList.toggle('active', a.dataset.sec === (cur ? cur.id : ''));
    });
  }
  window.addEventListener('scroll', updateActive, { passive: true });
  updateActive();
})();
</script>

<?php include dirname(__DIR__, 2) . '/inc/foot.php'; ?>

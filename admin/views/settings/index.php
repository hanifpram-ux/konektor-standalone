<?php
require_once dirname(__DIR__, 3) . '/admin/inc/bootstrap.php';
$pageTitle = 'Pengaturan';

// Auto-detect current site URL dynamically
function currentSiteUrl() {
    $scheme = knk_is_https() ? 'https' : 'http';
    $host   = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    return rtrim($scheme . '://' . $host, '/');
}

function fetchTelegramWebhookInfo($token)
{
    if (!$token) return null;
    $endpoint = "https://api.telegram.org/bot{$token}/getWebhookInfo";
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    return $body ? json_decode($body, true) : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['_register_tg_webhook'])) {
        $tgToken = Settings::get('telegram_bot_token');
        if (!$tgToken) {
            flashSet('error', 'Token Telegram belum diisi.');
        } else {
            $webhookUrl = currentSiteUrl() . '/telegram/callback';
            $resp = Helper::httpPost(
                "https://api.telegram.org/bot{$tgToken}/setWebhook",
                ['url' => $webhookUrl]
            );
            $body = $resp['body'] ? json_decode($resp['body'], true) : [];
            if (!empty($body['ok'])) {
                flashSet('success', 'Webhook Telegram berhasil didaftarkan.');
            } else {
                $desc = $body['description'] ?? 'Unknown error';
                flashSet('error', 'Gagal daftarkan webhook: ' . htmlspecialchars($desc));
            }
        }
        header('Location: index.php'); exit;
    }
    if (isset($_POST['_save'])) {
        // Always update site_url from current request dynamically
        Settings::set('site_url', currentSiteUrl());
        $allowed = ['app_name','cs_panel_slug','telegram_bot_token','encrypt_lead_data',
                    'webhook_url','webhook_secret','timezone','double_lead_scope'];
        foreach ($allowed as $k) {
            if (isset($_POST[$k])) Settings::set($k, strip_tags(trim($_POST[$k])));
        }
        // Apply timezone immediately for this request
        if (!empty($_POST['timezone'])) {
            @date_default_timezone_set($_POST['timezone']);
        }
        flashSet('success', 'Pengaturan disimpan.');
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
    }
    header('Location: index.php'); exit;
}

$s = Settings::all();
$currentUrl = currentSiteUrl();
$kampanyeUrl = $currentUrl . '/k/nama-kampanye';
$tgWebhookInfo = null;
if (!empty($s['telegram_bot_token'])) {
    $tgWebhookInfo = fetchTelegramWebhookInfo($s['telegram_bot_token']);
}

include dirname(__DIR__, 2) . '/inc/head.php';
include dirname(__DIR__, 2) . '/inc/sidebar.php';
?>
<div class="page-wrapper">
  <div class="page-header">
    <div>
      <h1 class="page-title">Pengaturan</h1>
      <p class="page-desc">Konfigurasi sistem Konektor</p>
    </div>
  </div>
  <?php include dirname(__DIR__, 2) . '/inc/flash.php'; ?>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">

    <!-- Left column -->
    <div style="display:flex;flex-direction:column;gap:16px;">

      <!-- General -->
      <form method="POST">
        <input type="hidden" name="_save" value="1">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Umum</h3></div>
          <div class="card-content" style="display:flex;flex-direction:column;gap:16px;">
            <div>
              <label class="label">Nama Aplikasi</label>
              <input name="app_name" value="<?= ae($s['app_name'] ?? 'Konektor') ?>" class="input" placeholder="Konektor">
            </div>
            <div>
              <label class="label">URL Kampanye (auto-detect)</label>
              <div style="padding:9px 12px;background:hsl(var(--muted));border-radius:var(--radius);font-size:13px;font-family:monospace;color:hsl(var(--muted-foreground));word-break:break-all;">
                <?= ae($kampanyeUrl) ?>
              </div>
              <p class="form-description">URL diambil otomatis dari domain yang sedang diakses. Tidak perlu konfigurasi manual.</p>
            </div>
            <div>
              <label class="label">CS Panel Slug</label>
              <input name="cs_panel_slug" value="<?= ae($s['cs_panel_slug'] ?? 'cs-panel') ?>" class="input" placeholder="cs-panel">
              <p class="form-description">URL panel CS: <?= ae($currentUrl) ?>/<span style="font-weight:600;"><?= ae($s['cs_panel_slug'] ?? 'cs-panel') ?></span>/TOKEN</p>
            </div>
            <div>
              <label class="label">Zona Waktu (Timezone)</label>
              <?php
              $tz_current = $s['timezone'] ?? 'Asia/Jakarta';
              $tz_groups = [
                'Indonesia' => [
                  'Asia/Jakarta'    => 'WIB — Waktu Indonesia Barat (UTC+7)',
                  'Asia/Makassar'   => 'WITA — Waktu Indonesia Tengah (UTC+8)',
                  'Asia/Jayapura'   => 'WIT — Waktu Indonesia Timur (UTC+9)',
                ],
                'Asia' => [
                  'Asia/Singapore'  => 'Singapore (UTC+8)',
                  'Asia/Kuala_Lumpur' => 'Kuala Lumpur (UTC+8)',
                  'Asia/Bangkok'    => 'Bangkok (UTC+7)',
                  'Asia/Ho_Chi_Minh'=> 'Ho Chi Minh (UTC+7)',
                  'Asia/Manila'     => 'Manila (UTC+8)',
                  'Asia/Tokyo'      => 'Tokyo (UTC+9)',
                  'Asia/Seoul'      => 'Seoul (UTC+9)',
                  'Asia/Shanghai'   => 'Shanghai (UTC+8)',
                  'Asia/Kolkata'    => 'India (UTC+5:30)',
                  'Asia/Dubai'      => 'Dubai (UTC+4)',
                  'Asia/Riyadh'     => 'Riyadh (UTC+3)',
                ],
                'Eropa & Amerika' => [
                  'UTC'             => 'UTC (UTC+0)',
                  'Europe/London'   => 'London (UTC+0/+1)',
                  'Europe/Paris'    => 'Paris (UTC+1/+2)',
                  'America/New_York'=> 'New York (UTC-5/-4)',
                  'America/Los_Angeles' => 'Los Angeles (UTC-8/-7)',
                ],
              ];
              ?>
              <select name="timezone" class="input">
                <?php foreach ($tz_groups as $group => $zones): ?>
                <optgroup label="<?= ae($group) ?>">
                  <?php foreach ($zones as $tz => $label): ?>
                  <option value="<?= ae($tz) ?>" <?= $tz_current === $tz ? 'selected' : '' ?>><?= ae($label) ?></option>
                  <?php endforeach; ?>
                </optgroup>
                <?php endforeach; ?>
              </select>
              <p class="form-description">Zona waktu untuk tampilan tanggal/jam lead, analitik, dan log. Saat ini server: <strong><?= ae(date_default_timezone_get()) ?></strong> — Waktu sekarang: <strong><?= date('d/m/Y H:i:s') ?></strong></p>
            </div>
          </div>
          <div class="card-footer" style="justify-content:flex-end;">
            <button type="submit" class="btn btn-default"><?= icon('save') ?> Simpan</button>
          </div>
        </div>
      </form>

      <!-- Keamanan -->
      <form method="POST">
        <input type="hidden" name="_save" value="1">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Keamanan &amp; Data</h3></div>
          <div class="card-content" style="display:flex;flex-direction:column;gap:16px;">
            <div>
              <label class="label">Enkripsi Data Lead</label>
              <select name="encrypt_lead_data" class="input">
                <option value="1" <?= ($s['encrypt_lead_data'] ?? '1') === '1' ? 'selected' : '' ?>>Aktif — AES-256-CBC (direkomendasikan)</option>
                <option value="0" <?= ($s['encrypt_lead_data'] ?? '1') === '0' ? 'selected' : '' ?>>Nonaktif</option>
              </select>
              <p class="form-description">Data nama, HP, email, dan alamat lead dienkripsi di database.</p>
            </div>

            <div>
              <label class="label">Lingkup Deteksi Double Lead</label>
              <?php $dls = $s['double_lead_scope'] ?? 'campaign'; ?>
              <select name="double_lead_scope" class="input">
                <option value="campaign" <?= $dls === 'campaign' ? 'selected' : '' ?>>Per Kampanye — deteksi hanya dalam kampanye yang sama</option>
                <option value="domain"   <?= $dls === 'domain'   ? 'selected' : '' ?>>Per Domain — deteksi semua kampanye dari domain/website yang sama</option>
                <option value="page"     <?= $dls === 'page'     ? 'selected' : '' ?>>Per Halaman (Slug) — deteksi semua kampanye dari URL halaman yang persis sama</option>
              </select>
              <div style="margin-top:10px;display:flex;flex-direction:column;gap:6px;font-size:12px;color:hsl(var(--muted-foreground));">
                <div style="padding:8px 10px;background:hsl(var(--muted));border-radius:6px;">
                  <strong style="color:hsl(var(--foreground));">Per Kampanye:</strong> Jika 1 halaman ada 2 kampanye (form + link), keduanya dihitung terpisah. Pengunjung bisa submit form dan klik link tanpa terdeteksi double.
                </div>
                <div style="padding:8px 10px;background:hsl(var(--muted));border-radius:6px;">
                  <strong style="color:hsl(var(--foreground));">Per Domain:</strong> Jika pengunjung sudah submit/klik di domain <code>domainmu.com</code>, semua kampanye di domain itu terdeteksi double — termasuk form dan link di halaman berbeda.
                </div>
                <div style="padding:8px 10px;background:hsl(var(--muted));border-radius:6px;">
                  <strong style="color:hsl(var(--foreground));">Per Halaman:</strong> Jika 1 halaman ada form + link, submit salah satu = double untuk keduanya. Tapi halaman lain di domain yang sama tidak terpengaruh.
                </div>
              </div>
            </div>

          </div>
          <div class="card-footer" style="justify-content:flex-end;">
            <button type="submit" class="btn btn-default"><?= icon('save') ?> Simpan</button>
          </div>
        </div>
      </form>

      <!-- Ganti Password -->
      <form method="POST">
        <input type="hidden" name="_pw" value="1">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Ganti Password Admin</h3></div>
          <div class="card-content" style="display:flex;flex-direction:column;gap:14px;">
            <div>
              <label class="label">Password Lama</label>
              <input type="password" name="old_pass" class="input" required>
            </div>
            <div>
              <label class="label">Password Baru</label>
              <input type="password" name="new_pass" class="input" minlength="8" required>
              <p class="form-description">Minimal 8 karakter</p>
            </div>
            <div>
              <label class="label">Konfirmasi Password Baru</label>
              <input type="password" name="new_pass2" class="input" required>
            </div>
          </div>
          <div class="card-footer" style="justify-content:flex-end;">
            <button type="submit" class="btn btn-default"><?= icon('lock') ?> Ganti Password</button>
          </div>
        </div>
      </form>

    </div><!-- /left -->

    <!-- Right column -->
    <div style="display:flex;flex-direction:column;gap:16px;">

      <!-- Telegram -->
      <form method="POST">
        <input type="hidden" name="_save" value="1">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Telegram Bot</h3>
            <p class="card-desc">Notifikasi lead baru ke operator via Telegram</p>
          </div>
          <div class="card-content" style="display:flex;flex-direction:column;gap:16px;">
            <div>
              <label class="label">Bot Token</label>
              <input name="telegram_bot_token" type="password" value="<?= ae($s['telegram_bot_token'] ?? '') ?>" class="input" placeholder="1234567890:ABCDef...">
              <p class="form-description">Dapatkan dari <strong>@BotFather</strong> di Telegram dengan perintah /newbot</p>
            </div>
            <?php $tgWebhookUrl = $currentUrl . '/telegram/callback'; ?>
            <div style="padding:12px 14px;background:hsl(var(--muted));border-radius:var(--radius);font-size:12px;color:hsl(var(--muted-foreground));line-height:1.6;">
              <strong style="color:hsl(var(--foreground));">Cara setup:</strong><br>
              1. Buka Telegram, cari <strong>@BotFather</strong><br>
              2. Ketik <code>/newbot</code> lalu ikuti instruksi, beri nama bot<br>
              3. Salin <strong>Bot Token</strong> dan tempel di sini, lalu klik <strong>Simpan</strong><br>
              4. Klik <strong>Daftarkan Webhook</strong> untuk mengaktifkan callback Telegram<br>
              5. Isi <strong>Telegram Chat ID</strong> di profil operator<br>
              6. Cari <strong>@userinfobot</strong> untuk mendapatkan Chat ID Anda<br><br>
              <strong style="color:hsl(var(--foreground));">URL Webhook Telegram:</strong><br>
              <code style="word-break:break-all;"><?= ae($tgWebhookUrl) ?></code>
            </div>
            <?php if (!empty($tgWebhookInfo) && !empty($tgWebhookInfo['ok'])): ?>
            <div style="padding:12px;background:hsl(142 76% 36%/0.08);border:1px solid hsl(142 76% 36%/0.2);border-radius:var(--radius);font-size:12px;color:hsl(142 76% 36%);">
              <strong>Status Webhook:</strong> Aktif<br>
              URL: <code style="word-break:break-all;"><?= ae($tgWebhookInfo['result']['url'] ?? '') ?></code><br>
              Pending update: <?= (int)($tgWebhookInfo['result']['pending_update_count'] ?? 0) ?><br>
              <?php if (!empty($tgWebhookInfo['result']['last_error_message'])): ?>
              Error terakhir: <?= ae($tgWebhookInfo['result']['last_error_message']) ?>
              <?php endif; ?>
            </div>
            <?php elseif (!empty($s['telegram_bot_token'])): ?>
            <div style="padding:12px;background:hsl(38 92% 50%/0.08);border:1px solid hsl(38 92% 50%/0.2);border-radius:var(--radius);font-size:12px;color:hsl(38 92% 40%);">
              Status Webhook Telegram belum didaftarkan atau token belum valid. Klik <strong>Daftarkan Webhook</strong> untuk mengecek.
            </div>
            <?php endif; ?>
          </div>
          <div class="card-footer" style="justify-content:space-between;align-items:center;">
              <button class="btn btn-outline btn-sm" type="submit" name="_register_tg_webhook" value="1"><?= icon('send','',13) ?> Daftarkan Webhook</button>
              <button type="submit" class="btn btn-default"><?= icon('save') ?> Simpan</button>
          </div>
        </div>
      </form>

      <!-- Webhook -->
      <form method="POST">
        <input type="hidden" name="_save" value="1">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Webhook</h3>
            <p class="card-desc">Kirim data lead baru ke endpoint eksternal secara otomatis</p>
          </div>
          <div class="card-content" style="display:flex;flex-direction:column;gap:16px;">
            <div>
              <label class="label">Webhook URL</label>
              <input name="webhook_url" value="<?= ae($s['webhook_url'] ?? '') ?>" class="input" placeholder="https://example.com/webhook">
              <p class="form-description">Konektor akan mengirim POST JSON ke URL ini setiap ada lead baru.</p>
            </div>
            <div>
              <label class="label">Webhook Secret <span style="color:hsl(var(--muted-foreground));font-weight:400;">(opsional)</span></label>
              <input name="webhook_secret" type="password" value="<?= ae($s['webhook_secret'] ?? '') ?>" class="input" placeholder="Secret untuk verifikasi signature">
              <p class="form-description">Header <code>X-Konektor-Signature: sha256=HMAC</code> dikirim jika secret diisi.</p>
            </div>
          </div>
          <div class="card-footer" style="justify-content:flex-end;">
            <button type="submit" class="btn btn-default"><?= icon('save') ?> Simpan</button>
          </div>
        </div>
      </form>

      <!-- System info -->
      <div class="card">
        <div class="card-header"><h3 class="card-title">Informasi Sistem</h3></div>
        <div class="card-content">
          <div style="display:flex;flex-direction:column;gap:0;">
            <?php
            $sysInfo = [
              ['Versi Konektor',  KONEKTOR_VERSION],
              ['PHP',             PHP_VERSION],
              ['Database',        KONEKTOR_DB_NAME . '@' . KONEKTOR_DB_HOST],
              ['Table Prefix',    KONEKTOR_DB_PREFIX],
              ['URL Aktif',       $currentUrl],
              ['Base Slug',       'k (fixed)'],
              ['OpenSSL',         extension_loaded('openssl') ? 'Aktif' : 'Tidak tersedia'],
              ['cURL',            extension_loaded('curl')    ? 'Aktif' : 'Tidak tersedia'],
              ['Zona Waktu',       date_default_timezone_get() . ' — ' . date('d/m/Y H:i:s')],
              ['Total Lead',      number_format(Lead::count())],
              ['Total Kampanye',  number_format(DB::count('campaigns'))],
              ['Total Operator',  number_format(DB::count('operators'))],
            ];
            foreach ($sysInfo as [$lbl, $val]): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid hsl(var(--border));">
              <span class="text-muted text-sm"><?= ae($lbl) ?></span>
              <span style="font-size:13px;font-weight:500;font-family:<?= in_array($lbl,['Database','Table Prefix','URL Aktif','Base Slug']) ? 'monospace' : 'inherit' ?>;max-width:55%;text-align:right;word-break:break-all;"><?= ae($val) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

    </div><!-- /right -->

  </div><!-- /grid -->
</div>
<?php include dirname(__DIR__, 2) . '/inc/foot.php'; ?>

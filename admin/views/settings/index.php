<?php
require_once dirname(__DIR__, 3) . '/admin/inc/bootstrap.php';
$pageTitle = 'Pengaturan';

// Auto-detect current site URL dynamically
function currentSiteUrl() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    return rtrim($scheme . '://' . $host, '/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['_save'])) {
        // Always update site_url from current request dynamically
        Settings::set('site_url', currentSiteUrl());
        $allowed = ['app_name','cs_panel_slug','telegram_bot_token','encrypt_lead_data'];
        foreach ($allowed as $k) {
            if (isset($_POST[$k])) Settings::set($k, strip_tags(trim($_POST[$k])));
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
            <div style="padding:12px 14px;background:hsl(var(--muted));border-radius:var(--radius);font-size:12px;color:hsl(var(--muted-foreground));line-height:1.6;">
              <strong style="color:hsl(var(--foreground));">Cara setup:</strong><br>
              1. Buka Telegram, cari <strong>@BotFather</strong><br>
              2. Ketik <code>/newbot</code> lalu ikuti instruksi<br>
              3. Salin token dan tempel di sini<br>
              4. Isi <strong>Telegram Chat ID</strong> di profil operator<br>
              5. Cari <strong>@userinfobot</strong> untuk mendapatkan Chat ID Anda
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

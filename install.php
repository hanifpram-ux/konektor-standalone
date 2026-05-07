<?php
/**
 * Konektor Standalone — Installer
 * PHP 7.1+ compatible.
 *
 * Dibuat Oleh  : Hanif Pramono
 * Website      : https://hanifprm.my.id
 */

define('KONEKTOR_ROOT', __DIR__);
define('KONEKTOR_VERSION', '1.0.0');

$config_file = KONEKTOR_ROOT . '/config/config.php';
$lock_file   = KONEKTOR_ROOT . '/config/installed.lock';

if (file_exists($lock_file) && file_exists($config_file)) {
    header('Location: admin/'); exit;
}

function knk_is_https() {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') return true;
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') return true;
    if (!empty($_SERVER['HTTP_CLOUDFRONT_FORWARDED_PROTO']) && $_SERVER['HTTP_CLOUDFRONT_FORWARDED_PROTO'] === 'https') return true;
    if (!empty($_SERVER['HTTP_CF_VISITOR'])) {
        $cf = json_decode($_SERVER['HTTP_CF_VISITOR'], true);
        if (!empty($cf['scheme']) && $cf['scheme'] === 'https') return true;
    }
    return false;
}

function detect_site_url() {
    $scheme = knk_is_https() ? 'https' : 'http';
    $host   = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $script = dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/');
    $script = rtrim(str_replace('\\','/',$script), '/');
    return rtrim($scheme . '://' . $host . $script, '/');
}

$step   = (int)(isset($_GET['step']) ? $_GET['step'] : 1);
$errors = [];

// ── Step 1: Test DB connection ────────────────────────────────────────────
if ($step === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host = trim(isset($_POST['db_host']) ? $_POST['db_host'] : 'localhost');
    $db_port = (int)(isset($_POST['db_port']) ? $_POST['db_port'] : 3306);
    $db_name = trim(isset($_POST['db_name']) ? $_POST['db_name'] : '');
    $db_user = trim(isset($_POST['db_user']) ? $_POST['db_user'] : '');
    $db_pass = isset($_POST['db_pass']) ? $_POST['db_pass'] : '';

    if (empty($db_name)) $errors[] = 'Nama database wajib diisi.';
    if (empty($db_user)) $errors[] = 'Username database wajib diisi.';

    if (empty($errors)) {
        try {
            $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
            new PDO($dsn, $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            if (session_status() === PHP_SESSION_NONE) session_start();
            $_SESSION['install'] = compact('db_host','db_port','db_name','db_user','db_pass');
            header('Location: install.php?step=2'); exit;
        } catch (PDOException $e) {
            $errors[] = 'Koneksi database gagal: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// ── Step 2: Create admin + run installer ──────────────────────────────────
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $install = isset($_SESSION['install']) ? $_SESSION['install'] : null;
    if (!$install) { header('Location: install.php?step=1'); exit; }

    $admin_user  = trim(isset($_POST['admin_user'])  ? $_POST['admin_user']  : '');
    $admin_pass  = isset($_POST['admin_pass'])        ? $_POST['admin_pass']  : '';
    $admin_pass2 = isset($_POST['admin_pass2'])       ? $_POST['admin_pass2'] : '';

    if (empty($admin_user))           $errors[] = 'Username admin wajib diisi.';
    if (strlen($admin_pass) < 8)      $errors[] = 'Password minimal 8 karakter.';
    if ($admin_pass !== $admin_pass2) $errors[] = 'Konfirmasi password tidak cocok.';

    if (empty($errors)) {
        try {
            $dsn = "mysql:host={$install['db_host']};port={$install['db_port']};dbname={$install['db_name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $install['db_user'], $install['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            $siteUrl = detect_site_url();
            run_installer($pdo, $install, $admin_user, $admin_pass, $siteUrl);

            $secret = bin2hex(random_bytes(32));
            $encKey = bin2hex(random_bytes(16));
            if (!is_dir(KONEKTOR_ROOT . '/config')) mkdir(KONEKTOR_ROOT . '/config', 0755, true);
            file_put_contents($config_file, generate_config($install, $secret, $encKey, $siteUrl));
            file_put_contents($lock_file, date('Y-m-d H:i:s') . "\n");

            unset($_SESSION['install']);
            header('Location: install.php?step=3'); exit;
        } catch (Exception $e) {
            $errors[] = 'Instalasi gagal: ' . htmlspecialchars($e->getMessage());
        }
    }
}

// ── Installer SQL ─────────────────────────────────────────────────────────
function run_installer($pdo, $install, $adminUser, $adminPass, $siteUrl)
{
    $pfx     = 'knk_';
    $charset = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';

    $sqls = [
        "CREATE TABLE IF NOT EXISTS `{$pfx}campaigns` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(191) NOT NULL,
            type ENUM('form','wa_link') NOT NULL DEFAULT 'form',
            store_name VARCHAR(255) DEFAULT NULL,
            product_name VARCHAR(255) DEFAULT NULL,
            form_config LONGTEXT DEFAULT NULL,
            thanks_page_config LONGTEXT DEFAULT NULL,
            pixel_config LONGTEXT DEFAULT NULL,
            double_lead_enabled TINYINT(1) NOT NULL DEFAULT 1,
            double_lead_message TEXT DEFAULT NULL,
            followup_message TEXT DEFAULT NULL,
            allowed_domains TEXT DEFAULT NULL,
            block_enabled TINYINT(1) NOT NULL DEFAULT 1,
            block_message TEXT DEFAULT NULL,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id), UNIQUE KEY uq_slug (slug)
        ) {$charset}",

        "CREATE TABLE IF NOT EXISTS `{$pfx}operators` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            type ENUM('whatsapp','email','telegram','line') NOT NULL DEFAULT 'whatsapp',
            value VARCHAR(500) NOT NULL,
            status ENUM('on','off') NOT NULL DEFAULT 'on',
            work_hours_enabled TINYINT(1) NOT NULL DEFAULT 0,
            work_hours LONGTEXT DEFAULT NULL,
            telegram_chat_id VARCHAR(100) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) {$charset}",

        "CREATE TABLE IF NOT EXISTS `{$pfx}campaign_operators` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id BIGINT UNSIGNED NOT NULL,
            operator_id BIGINT UNSIGNED NOT NULL,
            weight TINYINT UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY (id), UNIQUE KEY uq_camp_op (campaign_id, operator_id)
        ) {$charset}",

        "CREATE TABLE IF NOT EXISTS `{$pfx}leads` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id BIGINT UNSIGNED NOT NULL,
            operator_id BIGINT UNSIGNED DEFAULT NULL,
            name VARCHAR(255) DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            phone VARCHAR(255) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            quantity VARCHAR(100) DEFAULT NULL,
            custom_message TEXT DEFAULT NULL,
            extra_data LONGTEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            cookie_id VARCHAR(128) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            referrer TEXT DEFAULT NULL,
            fingerprint VARCHAR(64) DEFAULT NULL,
            status ENUM('new','contacted','purchased','cancelled','blocked') NOT NULL DEFAULT 'new',
            status_note TEXT DEFAULT NULL,
            followed_up_at DATETIME DEFAULT NULL,
            is_double TINYINT(1) NOT NULL DEFAULT 0,
            source_url TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_campaign (campaign_id), KEY idx_operator (operator_id),
            KEY idx_fingerprint (fingerprint), KEY idx_status (status), KEY idx_cookie (cookie_id)
        ) {$charset}",

        "CREATE TABLE IF NOT EXISTS `{$pfx}blocked` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id BIGINT UNSIGNED DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            fingerprint VARCHAR(64) DEFAULT NULL,
            cookie_id VARCHAR(128) DEFAULT NULL,
            phone VARCHAR(255) DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            reason TEXT DEFAULT NULL,
            block_type ENUM('ip','cookie','both') NOT NULL DEFAULT 'both',
            blocked_by BIGINT UNSIGNED DEFAULT NULL,
            operator_name VARCHAR(255) DEFAULT NULL,
            blocked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_ip (ip_address), KEY idx_fingerprint (fingerprint), KEY idx_cookie (cookie_id)
        ) {$charset}",

        "CREATE TABLE IF NOT EXISTS `{$pfx}events` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id BIGINT UNSIGNED NOT NULL,
            event_type ENUM('page_load','form_view','form_submit','thanks_page','wa_click') NOT NULL,
            lead_id BIGINT UNSIGNED DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            referrer TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_campaign_event (campaign_id, event_type), KEY idx_created (created_at)
        ) {$charset}",

        "CREATE TABLE IF NOT EXISTS `{$pfx}settings` (
            setting_key VARCHAR(191) NOT NULL,
            setting_value LONGTEXT DEFAULT NULL,
            PRIMARY KEY (setting_key)
        ) {$charset}",

        "CREATE TABLE IF NOT EXISTS `{$pfx}operator_tokens` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            operator_id BIGINT UNSIGNED NOT NULL,
            token VARCHAR(128) NOT NULL,
            expires_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_token (token), KEY idx_operator (operator_id)
        ) {$charset}",

        "CREATE TABLE IF NOT EXISTS `{$pfx}api_logs` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source VARCHAR(100) DEFAULT NULL,
            event_name VARCHAR(100) DEFAULT NULL,
            endpoint TEXT DEFAULT NULL,
            payload LONGTEXT DEFAULT NULL,
            response LONGTEXT DEFAULT NULL,
            status_code INT DEFAULT NULL,
            success TINYINT(1) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_source (source), KEY idx_created (created_at)
        ) {$charset}",

        "CREATE TABLE IF NOT EXISTS `{$pfx}users` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            username VARCHAR(100) NOT NULL,
            email VARCHAR(255) DEFAULT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin','manager') NOT NULL DEFAULT 'admin',
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            last_login DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id), UNIQUE KEY uq_username (username)
        ) {$charset}",
    ];

    foreach ($sqls as $sql) $pdo->exec($sql);

    // Upgrade existing tables — safe to run on fresh install too (IF NOT EXISTS / IGNORE)
    $upgrades = [
        "ALTER TABLE `{$pfx}blocked` ADD COLUMN IF NOT EXISTS `block_type` ENUM('ip','cookie','both') NOT NULL DEFAULT 'both' AFTER `reason`",
    ];
    foreach ($upgrades as $sql) {
        try { $pdo->exec($sql); } catch (PDOException $e) { /* column may already exist */ }
    }

    $defaults = [
        'telegram_bot_token'     => '',
        'allowed_domains_global' => '[]',
        'cs_panel_slug'          => 'cs-panel',
        'encrypt_lead_data'      => '1',
        'base_slug'              => 'k',
        'site_url'               => $siteUrl,
        'app_name'               => 'Konektor',
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO `{$pfx}settings` (setting_key, setting_value) VALUES (?, ?)");
    foreach ($defaults as $k => $v) $stmt->execute([$k, $v]);

    $hash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);
    $pdo->prepare("INSERT INTO `{$pfx}users` (username, password_hash, role) VALUES (?, ?, 'admin')")
        ->execute([$adminUser, $hash]);
}

function generate_config($install, $secret, $encKey, $siteUrl)
{
    $host = addslashes($install['db_host']);
    $port = (int)$install['db_port'];
    $name = addslashes($install['db_name']);
    $user = addslashes($install['db_user']);
    $pass = addslashes($install['db_pass']);
    $url  = addslashes($siteUrl);

    return <<<PHP
<?php
// Konektor Standalone — auto-generated config. Do not edit manually.
define('KONEKTOR_DB_HOST',    '{$host}');
define('KONEKTOR_DB_PORT',    {$port});
define('KONEKTOR_DB_NAME',    '{$name}');
define('KONEKTOR_DB_USER',    '{$user}');
define('KONEKTOR_DB_PASS',    '{$pass}');
define('KONEKTOR_DB_PREFIX',  'knk_');
define('KONEKTOR_SITE_URL',   '{$url}');
define('KONEKTOR_BASE_SLUG',  'k');
define('KONEKTOR_SECRET_KEY', '{$secret}');
define('KONEKTOR_ENC_KEY',    '{$encKey}');
define('KONEKTOR_ENV',        'production');
PHP;
}

// ── Read back old values for form pre-fill ────────────────────────────────
if ($step === 2 && session_status() === PHP_SESSION_NONE) session_start();
$prevPost = array_map('htmlspecialchars', $_POST);

?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Konektor — Setup</title>
<style>
/* shadcn/ui design tokens */
:root {
  --background: 0 0% 100%;
  --foreground: 222.2 84% 4.9%;
  --card: 0 0% 100%;
  --card-foreground: 222.2 84% 4.9%;
  --muted: 210 40% 96.1%;
  --muted-foreground: 215.4 16.3% 46.9%;
  --border: 214.3 31.8% 91.4%;
  --input: 214.3 31.8% 91.4%;
  --primary: 217.2 91.2% 59.8%;
  --primary-foreground: 222.2 47.4% 11.2%;
  --destructive: 0 84.2% 60.2%;
  --ring: 217.2 91.2% 59.8%;
  --radius: 8px;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Inter', sans-serif;
  background: hsl(210 40% 96.1%);
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 24px;
  color: hsl(var(--foreground));
}

/* Card */
.install-card {
  background: hsl(var(--card));
  border-radius: 16px;
  box-shadow: 0 4px 6px -1px rgba(0,0,0,.07), 0 20px 48px -8px rgba(0,0,0,.12);
  width: 100%;
  max-width: 480px;
  overflow: hidden;
  border: 1px solid hsl(var(--border));
}

/* Header */
.inst-header {
  padding: 28px 32px 24px;
  border-bottom: 1px solid hsl(var(--border));
  display: flex;
  align-items: center;
  gap: 14px;
}
.inst-logo {
  width: 44px; height: 44px;
  background: hsl(var(--primary));
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  color: #fff; flex-shrink: 0;
}
.inst-logo svg { display: block; }
.inst-header-text h1 { font-size: 18px; font-weight: 700; color: hsl(var(--foreground)); }
.inst-header-text p  { font-size: 12px; color: hsl(var(--muted-foreground)); margin-top: 2px; }

/* Steps bar */
.steps-bar {
  display: flex;
  background: hsl(var(--muted));
  border-bottom: 1px solid hsl(var(--border));
  padding: 0 24px;
}
.step-item {
  display: flex; align-items: center; gap: 8px;
  padding: 12px 0; flex: 1;
  font-size: 12px; font-weight: 500;
  color: hsl(var(--muted-foreground));
  border-bottom: 2px solid transparent;
  transition: color .15s, border-color .15s;
}
.step-item.active { color: hsl(var(--primary)); border-color: hsl(var(--primary)); }
.step-item.done   { color: hsl(142.1 76.2% 36.3%); border-color: hsl(142.1 76.2% 36.3%); }
.step-num {
  width: 22px; height: 22px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; font-weight: 700; flex-shrink: 0;
  background: hsl(var(--border));
  color: hsl(var(--muted-foreground));
}
.step-item.active .step-num { background: hsl(var(--primary)); color: #fff; }
.step-item.done .step-num   { background: hsl(142.1 76.2% 36.3%); color: #fff; }

/* Body */
.inst-body { padding: 28px 32px 32px; }
.inst-title { font-size: 17px; font-weight: 700; color: hsl(var(--foreground)); margin-bottom: 4px; }
.inst-desc  { font-size: 13px; color: hsl(var(--muted-foreground)); margin-bottom: 22px; line-height: 1.55; }

/* Form elements */
.field { margin-bottom: 14px; }
.field label {
  display: block;
  font-size: 12px; font-weight: 600;
  color: hsl(var(--foreground));
  margin-bottom: 5px;
}
.field .hint { font-size: 11px; color: hsl(var(--muted-foreground)); margin-top: 3px; }
input[type="text"],
input[type="password"],
input[type="number"] {
  width: 100%;
  padding: 9px 12px;
  border: 1px solid hsl(var(--input));
  border-radius: var(--radius);
  font-size: 14px;
  color: hsl(var(--foreground));
  background: hsl(var(--background));
  outline: none;
  font-family: inherit;
  transition: border-color .15s, box-shadow .15s;
  -webkit-appearance: none;
}
input:focus {
  border-color: hsl(var(--primary));
  box-shadow: 0 0 0 3px hsl(var(--primary) / .15);
}
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

/* Button */
.btn-install {
  width: 100%;
  padding: 11px 20px;
  background: hsl(var(--primary));
  color: #fff;
  border: none;
  border-radius: var(--radius);
  font-size: 14px; font-weight: 600;
  cursor: pointer;
  margin-top: 8px;
  font-family: inherit;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  transition: opacity .15s;
}
.btn-install:hover { opacity: .88; }
.btn-install:disabled { opacity: .6; cursor: not-allowed; }

/* Alert boxes */
.alert {
  padding: 12px 14px;
  border-radius: var(--radius);
  margin-bottom: 18px;
  font-size: 12px;
  line-height: 1.6;
}
.alert-error {
  background: hsl(0 84.2% 60.2% / .08);
  border: 1px solid hsl(0 84.2% 60.2% / .3);
  color: hsl(0 72.2% 40.6%);
}
.alert-error li { list-style: disc inside; line-height: 1.9; }
.alert-info {
  background: hsl(var(--primary) / .06);
  border: 1px solid hsl(var(--primary) / .2);
  color: hsl(217.2 91.2% 35%);
}
.alert-info ul { padding-left: 14px; margin-top: 4px; }
.alert-warn {
  background: hsl(38 92% 50% / .08);
  border: 1px solid hsl(38 92% 50% / .3);
  color: hsl(25 90% 40%);
}

/* Separator */
.sep { height: 1px; background: hsl(var(--border)); margin: 18px 0; }

/* Success */
.success-wrap { text-align: center; padding: 8px 0; }
.success-icon {
  width: 72px; height: 72px;
  background: hsl(142.1 76.2% 36.3% / .1);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 18px;
  color: hsl(142.1 76.2% 36.3%);
}
.success-wrap h2 { font-size: 20px; font-weight: 700; margin-bottom: 8px; }
.success-wrap p  { font-size: 13px; color: hsl(var(--muted-foreground)); line-height: 1.6; margin-bottom: 8px; }
.link-group { display: flex; gap: 8px; margin-top: 22px; justify-content: center; }
.link-btn {
  padding: 10px 24px;
  border-radius: var(--radius);
  font-size: 13px; font-weight: 600;
  text-decoration: none;
  display: inline-flex; align-items: center; gap: 6px;
  transition: opacity .15s;
}
.link-btn:hover { opacity: .85; }
.link-primary { background: hsl(var(--primary)); color: #fff; }
.link-outline  { background: transparent; border: 1px solid hsl(var(--border)); color: hsl(var(--foreground)); }

/* Checklist items */
.check-item {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 8px 0;
  font-size: 12px;
  border-bottom: 1px solid hsl(var(--border));
}
.check-item:last-child { border-bottom: none; }
.check-icon { color: hsl(142.1 76.2% 36.3%); flex-shrink: 0; margin-top: 1px; }
.check-title { font-weight: 600; color: hsl(var(--foreground)); }
.check-desc  { color: hsl(var(--muted-foreground)); }
</style>
</head>
<body>
<div class="install-card">

  <!-- Header -->
  <div class="inst-header">
    <div class="inst-logo">
      <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/></svg>
    </div>
    <div class="inst-header-text">
      <h1>Konektor</h1>
      <p>Lead Management System &mdash; Setup</p>
    </div>
  </div>

  <!-- Steps bar -->
  <div class="steps-bar">
    <?php
    $steps = ['Database', 'Akun Admin', 'Selesai'];
    foreach ($steps as $i => $sl):
      $n = $i + 1;
      $cls = '';
      if ($n < $step) $cls = 'done';
      elseif ($n === $step) $cls = 'active';
      $checkSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
    ?>
    <div class="step-item <?= $cls ?>">
      <div class="step-num"><?= $cls==='done' ? $checkSvg : $n ?></div>
      <?= htmlspecialchars($sl) ?>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Body -->
  <div class="inst-body">

  <?php if (!empty($errors)): ?>
  <ul class="alert alert-error">
    <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <?php if ($step === 1): ?>

  <h2 class="inst-title">Koneksi Database</h2>
  <p class="inst-desc">Masukkan kredensial database MySQL/MariaDB. Pastikan database sudah dibuat terlebih dahulu.</p>

  <div class="alert alert-info">
    <div class="check-item">
      <span class="check-icon"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
      <div><div class="check-title">Database MySQL/MariaDB</div><div class="check-desc">Buat database kosong di cPanel / Plesk sebelum lanjut</div></div>
    </div>
    <div class="check-item">
      <span class="check-icon"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
      <div><div class="check-title">PHP 7.1+ dengan PDO &amp; OpenSSL</div><div class="check-desc">Ekstensi PDO MySQL, OpenSSL, dan cURL harus aktif</div></div>
    </div>
    <div class="check-item">
      <span class="check-icon"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
      <div><div class="check-title">Folder config/ bisa ditulis</div><div class="check-desc">Chmod 755 atau 777 pada folder config/</div></div>
    </div>
  </div>

  <form method="POST" action="install.php?step=1" onsubmit="this.querySelector('.btn-install').disabled=true">
    <div class="grid-2">
      <div class="field">
        <label>DB Host</label>
        <input type="text" name="db_host" value="<?= htmlspecialchars(isset($prevPost['db_host']) ? $prevPost['db_host'] : 'localhost') ?>" required>
      </div>
      <div class="field">
        <label>DB Port</label>
        <input type="number" name="db_port" value="<?= htmlspecialchars(isset($prevPost['db_port']) ? $prevPost['db_port'] : '3306') ?>" required>
      </div>
    </div>
    <div class="field">
      <label>Nama Database <span style="color:hsl(var(--destructive))">*</span></label>
      <input type="text" name="db_name" value="<?= htmlspecialchars(isset($prevPost['db_name']) ? $prevPost['db_name'] : '') ?>" placeholder="nama_database" required>
    </div>
    <div class="grid-2">
      <div class="field">
        <label>Username DB <span style="color:hsl(var(--destructive))">*</span></label>
        <input type="text" name="db_user" value="<?= htmlspecialchars(isset($prevPost['db_user']) ? $prevPost['db_user'] : '') ?>" placeholder="db_user" required autocomplete="off">
      </div>
      <div class="field">
        <label>Password DB</label>
        <input type="password" name="db_pass" placeholder="password" autocomplete="new-password">
      </div>
    </div>
    <button type="submit" class="btn-install">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/></svg>
      Tes Koneksi &amp; Lanjutkan
    </button>
  </form>

  <?php elseif ($step === 2): ?>
  <?php if (session_status() === PHP_SESSION_NONE) session_start(); if (empty($_SESSION['install'])) { header('Location: install.php?step=1'); exit; } ?>

  <h2 class="inst-title">Buat Akun Admin</h2>
  <p class="inst-desc">Akun ini digunakan untuk masuk ke panel admin Konektor. Simpan username dan password dengan aman.</p>

  <form method="POST" action="install.php?step=2" onsubmit="this.querySelector('.btn-install').disabled=true">
    <div class="field">
      <label>Username <span style="color:hsl(var(--destructive))">*</span></label>
      <input type="text" name="admin_user" value="<?= htmlspecialchars(isset($prevPost['admin_user']) ? $prevPost['admin_user'] : 'admin') ?>"
        placeholder="admin" required autocomplete="off">
      <p class="hint">Tanpa spasi. Digunakan untuk login ke admin panel.</p>
    </div>
    <div class="grid-2">
      <div class="field">
        <label>Password <span style="color:hsl(var(--destructive))">*</span></label>
        <input type="password" name="admin_pass" required minlength="8" placeholder="Min. 8 karakter" autocomplete="new-password">
      </div>
      <div class="field">
        <label>Konfirmasi Password <span style="color:hsl(var(--destructive))">*</span></label>
        <input type="password" name="admin_pass2" required placeholder="Ulangi password" autocomplete="new-password">
      </div>
    </div>

    <div class="sep"></div>

    <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:hsl(var(--muted));border-radius:var(--radius);font-size:12px;color:hsl(var(--muted-foreground));margin-bottom:16px;">
      <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
      URL situs akan otomatis terdeteksi dari domain yang Anda akses saat ini. Tidak perlu konfigurasi manual.
    </div>

    <button type="submit" class="btn-install">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"/><path d="M7 3v4a1 1 0 0 0 1 1h7"/></svg>
      Install Konektor
    </button>
  </form>

  <?php elseif ($step === 3): ?>

  <div class="success-wrap">
    <div class="success-icon">
      <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
    </div>
    <h2>Instalasi Berhasil!</h2>
    <p>Konektor sudah terpasang dan siap digunakan.<br>URL kampanye otomatis menggunakan domain Anda.</p>

    <div class="alert alert-warn" style="text-align:left;margin-top:16px;">
      <strong>Keamanan:</strong> Hapus atau rename file <code>install.php</code> setelah berhasil login untuk mencegah reinstalasi.
    </div>

    <div class="link-group">
      <a href="admin/" class="link-btn link-primary">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        Masuk ke Admin Panel
      </a>
      <a href="admin/views/guide/index.php" class="link-btn link-outline">Lihat Panduan</a>
    </div>
  </div>

  <?php endif; ?>

  </div><!-- /inst-body -->

  <div style="text-align:center;padding:14px 0 0;font-size:12px;color:hsl(var(--muted-foreground));border-top:1px solid hsl(var(--border));margin-top:12px;">
    Dibuat Oleh <a href="https://hanifprm.my.id" target="_blank" style="color:hsl(217.2 91.2% 59.8%);text-decoration:none;font-weight:600">Hanif Pramono</a>
  </div>
</div><!-- /install-card -->
</body>
</html>

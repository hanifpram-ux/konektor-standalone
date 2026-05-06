<?php
/**
 * Konektor Standalone — Root entry point.
 *
 * Dibuat Oleh  : Hanif Pramono
 * Website      : https://hanifprm.my.id
 */

define('KONEKTOR_ROOT', __DIR__);

$lock_file   = KONEKTOR_ROOT . '/config/installed.lock';
$config_file = KONEKTOR_ROOT . '/config/config.php';

// Sudah di-install → arahkan ke login admin
if (file_exists($lock_file) && file_exists($config_file)) {
    header('Location: admin/login.php');
    exit;
}

// Belum di-install → tampilkan panduan instalasi + credit
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Instalasi — Konektor</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px}
.container{max-width:480px;width:100%;text-align:center}
.logo{width:56px;height:56px;background:#2563eb;border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;color:#fff;font-size:28px;font-weight:700}
h1{font-size:28px;font-weight:700;margin-bottom:6px;color:#fff}
.subtitle{font-size:14px;color:#94a3b8;margin-bottom:32px}
.card{background:#1e293b;border:1px solid #334155;border-radius:16px;padding:28px;text-align:left;margin-bottom:24px}
.card h2{font-size:18px;font-weight:600;margin-bottom:10px;color:#fff}
.card p{font-size:13px;color:#94a3b8;line-height:1.6;margin-bottom:20px}
.btn{display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:12px 24px;border-radius:10px;font-size:14px;font-weight:500;transition:background .15s}
.btn:hover{background:#1d4ed8}
.steps{margin-top:20px}
.step{display:flex;align-items:flex-start;gap:12px;margin-bottom:14px}
.step-num{width:28px;height:28px;background:#334155;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;color:#fff;flex-shrink:0}
.step div{font-size:13px;color:#cbd5e1;line-height:1.5}
.step div strong{color:#fff;font-weight:500}
.credit{font-size:12px;color:#64748b}
.credit a{color:#60a5fa;text-decoration:none}
.credit a:hover{text-decoration:underline}
</style>
</head>
<body>
<div class="container">
  <div class="logo">K</div>
  <h1>Konektor</h1>
  <p class="subtitle">Standalone Marketing Connector</p>

  <div class="card">
    <h2>Belum Terinstal</h2>
    <p>Aplikasi ini memerlukan instalasi database sebelum dapat digunakan. Ikuti langkah-langkah berikut:</p>
    <a href="install.php" class="btn">Mulai Instalasi</a>

    <div class="steps">
      <div class="step">
        <div class="step-num">1</div>
        <div><strong>Buat database</strong> MySQL/MariaDB kosong di hosting atau local server Anda.</div>
      </div>
      <div class="step">
        <div class="step-num">2</div>
        <div><strong>Jalankan installer</strong> dengan mengklik tombol di atas dan masukkan kredensial database.</div>
      </div>
      <div class="step">
        <div class="step-num">3</div>
        <div><strong>Buat akun admin</strong> pada langkah terakhir installer untuk masuk ke panel admin.</div>
      </div>
    </div>
  </div>

  <div class="credit">
    Dibuat Oleh <a href="https://hanifprm.my.id" target="_blank" rel="noopener">Hanif Pramono</a> — https://hanifprm.my.id
  </div>
</div>
</body>
</html>

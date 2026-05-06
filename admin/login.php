<?php
require_once __DIR__ . '/inc/bootstrap.php';
if (Auth::check()) { header('Location: dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (Auth::attempt(trim($_POST['username'] ?? ''), $_POST['password'] ?? '')) {
        header('Location: dashboard.php'); exit;
    }
    $error = 'Username atau password salah.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — Konektor Admin</title>
<link rel="stylesheet" href="<?= adminPath('inc/shadcn.css') ?>">
</head>
<body style="background:hsl(210 40% 96.1%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;">
<?php include __DIR__ . '/inc/icons.php'; ?>

<div style="width:100%;max-width:380px;">

  <!-- Logo -->
  <div style="text-align:center;margin-bottom:32px;">
    <div style="width:44px;height:44px;background:hsl(222.2 47.4% 11.2%);border-radius:10px;display:inline-flex;align-items:center;justify-content:center;color:white;margin-bottom:16px;">
      <?= icon('zap','',20) ?>
    </div>
    <h1 style="font-size:20px;font-weight:700;color:hsl(222.2 84% 4.9%);letter-spacing:-0.3px;">Konektor Admin</h1>
    <p style="font-size:13px;color:hsl(215.4 16.3% 46.9%);margin-top:4px;">Masuk ke panel manajemen lead</p>
  </div>

  <div class="card">
    <div class="card-header" style="padding:20px 24px 0;">
      <h2 class="card-title">Masuk</h2>
      <p class="card-desc">Gunakan username dan password yang dibuat saat instalasi.</p>
    </div>
    <div class="card-content" style="padding:20px 24px;">
      <?php if ($error): ?>
      <div class="alert alert-destructive" style="margin-bottom:16px;">
        <?= icon('alert-circle') ?>
        <div>
          <div class="alert-title">Gagal masuk</div>
          <div class="alert-desc"><?= ae($error) ?></div>
        </div>
      </div>
      <?php endif; ?>

      <form method="POST" style="display:flex;flex-direction:column;gap:16px;">
        <div class="form-item" style="margin-bottom:0;">
          <label class="label" for="username">Username atau Email</label>
          <input id="username" name="username" type="text" class="input"
            value="<?= ae($_POST['username'] ?? '') ?>"
            placeholder="admin" required autofocus>
        </div>
        <div class="form-item" style="margin-bottom:0;">
          <label class="label" for="password">Password</label>
          <input id="password" name="password" type="password" class="input"
            placeholder="Masukkan password" required>
        </div>
        <button type="submit" class="btn btn-default w-full" style="margin-top:4px;">
          Masuk ke Admin Panel
        </button>
      </form>
    </div>
  </div>

  <div style="text-align:center;margin-top:20px;margin-bottom:20px;font-size:12px;color:hsl(215.4 16.3% 46.9%);">
    Dibuat Oleh <a href="https://hanifprm.my.id" target="_blank" style="color:hsl(217.2 91.2% 59.8%);text-decoration:none;font-weight:600">Hanif Pramono</a>
  </div>

</div>
</body>
</html>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Akses Dibatasi</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; background: #fef2f2; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
  .card { background: #fff; max-width: 420px; width: 100%; border-radius: 16px; padding: 40px 32px; text-align: center; box-shadow: 0 4px 24px rgba(220,38,38,.08); }
  .icon { font-size: 64px; margin-bottom: 20px; }
  h1 { font-size: 22px; font-weight: 800; color: #0f172a; margin-bottom: 12px; }
  p { font-size: 14px; color: #64748b; line-height: 1.6; }
</style>
</head>
<body>
<div class="card">
  <div class="icon">🚫</div>
  <h1>Akses Dibatasi</h1>
  <p><?= isset($campaign) ? Helper::e($campaign->block_message ?: 'Maaf, akses Anda telah dibatasi.') : 'Maaf, akses Anda telah dibatasi.' ?></p>
</div>
</body>
</html>

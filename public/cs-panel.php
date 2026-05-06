<?php
/**
 * CS Panel — rendered by CsController::panel()
 * Variables: $operator, $leads (decrypted), $total, $page
 */
$csSlug = Settings::get('cs_panel_slug', 'cs-panel');
$panelUrl = Helper::siteUrl($csSlug . '/' . $operator->token);
$perPage  = 30;
$totalPages = (int)ceil($total / $perPage);

$statusLabels = [
    'new'       => ['label'=>'Baru',      'color'=>'#2563eb','bg'=>'#eff6ff'],
    'contacted' => ['label'=>'Dihubungi', 'color'=>'#d97706','bg'=>'#fffbeb'],
    'purchased' => ['label'=>'Beli',      'color'=>'#16a34a','bg'=>'#f0fdf4'],
    'cancelled' => ['label'=>'Batal',     'color'=>'#6b7280','bg'=>'#f9fafb'],
    'blocked'   => ['label'=>'Blokir',    'color'=>'#dc2626','bg'=>'#fef2f2'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Panel CS — <?= Helper::e($operator->name) ?></title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; background: #f1f5f9; color: #0f172a; }
  .cs-header { background: #0f172a; color: #fff; padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 10; }
  .cs-header h1 { font-size: 16px; font-weight: 700; }
  .cs-header p { font-size: 12px; opacity: .6; }
  .cs-badge { background: #2563eb; color: #fff; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
  .cs-body { max-width: 800px; margin: 0 auto; padding: 16px; }
  .cs-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px; }
  .cs-stat { background: #fff; border-radius: 10px; padding: 14px; text-align: center; box-shadow: 0 1px 4px rgba(0,0,0,.05); }
  .cs-stat-num { font-size: 24px; font-weight: 800; }
  .cs-stat-label { font-size: 11px; color: #64748b; margin-top: 2px; }
  .cs-lead { background: #fff; border-radius: 12px; padding: 16px; margin-bottom: 12px; box-shadow: 0 1px 4px rgba(0,0,0,.05); }
  .cs-lead-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 10px; }
  .cs-lead-name { font-size: 15px; font-weight: 700; }
  .cs-lead-camp { font-size: 11px; color: #64748b; margin-top: 2px; }
  .cs-status { padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; cursor: pointer; border: none; }
  .cs-lead-info { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; font-size: 13px; color: #334155; margin-bottom: 12px; }
  .cs-info-item { display: flex; align-items: center; gap: 6px; }
  .cs-actions { display: flex; gap: 8px; flex-wrap: wrap; }
  .cs-btn { padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
  .cs-btn-wa  { background: #16a34a; color: #fff; }
  .cs-btn-tel { background: #0077b6; color: #fff; }
  .cs-btn-status { background: #f1f5f9; color: #334155; }
  .cs-date { font-size: 11px; color: #94a3b8; }
  .cs-status-form { display: none; margin-top: 10px; padding-top: 10px; border-top: 1px solid #f1f5f9; }
  .cs-status-form.open { display: block; }
  .cs-status-form select, .cs-status-form textarea { width: 100%; padding: 8px 10px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 13px; font-family: inherit; margin-bottom: 8px; }
  .cs-status-form textarea { min-height: 60px; resize: vertical; }
  .cs-status-form button { padding: 8px 18px; background: #2563eb; color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; }
  .cs-empty { text-align: center; padding: 48px 20px; color: #94a3b8; }
  .cs-pagination { display: flex; justify-content: center; gap: 6px; margin-top: 16px; }
  .cs-page-btn { padding: 6px 12px; border-radius: 6px; border: 1.5px solid #e2e8f0; background: #fff; font-size: 13px; cursor: pointer; text-decoration: none; color: #334155; }
  .cs-page-btn.active { background: #2563eb; color: #fff; border-color: #2563eb; }
</style>
</head>
<body>

<div class="cs-header">
  <div>
    <h1>Panel CS — <?= Helper::e($operator->name) ?></h1>
    <p>Konektor Lead Management</p>
  </div>
  <span class="cs-badge"><?= $total ?> Lead</span>
</div>

<div class="cs-body">

  <!-- Stats -->
  <?php
  $new       = count(array_filter($leads, function($l) { return $l->status === 'new'; }));
  $contacted = count(array_filter($leads, function($l) { return $l->status === 'contacted'; }));
  $purchased = count(array_filter($leads, function($l) { return $l->status === 'purchased'; }));
  ?>
  <div class="cs-stats">
    <div class="cs-stat"><div class="cs-stat-num" style="color:#2563eb"><?= $new ?></div><div class="cs-stat-label">Baru</div></div>
    <div class="cs-stat"><div class="cs-stat-num" style="color:#d97706"><?= $contacted ?></div><div class="cs-stat-label">Dihubungi</div></div>
    <div class="cs-stat"><div class="cs-stat-num" style="color:#16a34a"><?= $purchased ?></div><div class="cs-stat-label">Berhasil Beli</div></div>
  </div>

  <!-- Lead list -->
  <?php if (empty($leads)): ?>
  <div class="cs-empty">Belum ada lead yang ditugaskan ke Anda.</div>
  <?php else: ?>

  <?php foreach ($leads as $lead):
    $st    = $statusLabels[$lead->status] ?? $statusLabels['new'];
    $waUrl = Helper::waUrl($lead->phone ?? '');
    $camp  = $lead->campaign_name ?? '';
  ?>
  <div class="cs-lead" id="lead-<?= $lead->id ?>">
    <div class="cs-lead-header">
      <div>
        <div class="cs-lead-name"><?= Helper::e($lead->name ?? '-') ?></div>
        <div class="cs-lead-camp"><?= Helper::e($camp) ?></div>
      </div>
      <button class="cs-status" style="background:<?= $st['bg'] ?>;color:<?= $st['color'] ?>" onclick="toggleStatus(<?= $lead->id ?>)">
        <?= $st['label'] ?> ▾
      </button>
    </div>

    <div class="cs-lead-info">
      <?php if ($lead->phone): ?>
      <div class="cs-info-item">📞 <?= Helper::e($lead->phone) ?></div>
      <?php endif; ?>
      <?php if ($lead->email): ?>
      <div class="cs-info-item">📧 <?= Helper::e($lead->email) ?></div>
      <?php endif; ?>
      <?php if ($lead->product_name): ?>
      <div class="cs-info-item">📦 <?= Helper::e($lead->product_name) ?></div>
      <?php endif; ?>
      <?php if ($lead->quantity): ?>
      <div class="cs-info-item">🔢 <?= Helper::e($lead->quantity) ?></div>
      <?php endif; ?>
    </div>

    <?php if ($lead->address || $lead->custom_message): ?>
    <div style="font-size:13px;color:#64748b;margin-bottom:10px">
      <?php if ($lead->address): ?><div>📍 <?= Helper::e($lead->address) ?></div><?php endif; ?>
      <?php if ($lead->custom_message): ?><div>💬 <?= Helper::e($lead->custom_message) ?></div><?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="cs-actions">
      <?php if ($lead->phone): ?>
      <a href="<?= htmlspecialchars($waUrl) ?>" class="cs-btn cs-btn-wa" target="_blank">💬 WA</a>
      <?php endif; ?>
      <button class="cs-btn cs-btn-status" onclick="toggleStatus(<?= $lead->id ?>)">Ubah Status</button>
      <span class="cs-date"><?= date('d/m H:i', strtotime($lead->created_at)) ?></span>
    </div>

    <!-- Status form -->
    <div class="cs-status-form" id="sf-<?= $lead->id ?>">
      <select id="sel-<?= $lead->id ?>">
        <?php foreach ($statusLabels as $sv => $sl): ?>
        <option value="<?= $sv ?>" <?= $lead->status === $sv ? 'selected' : '' ?>><?= $sl['label'] ?></option>
        <?php endforeach; ?>
      </select>
      <textarea id="note-<?= $lead->id ?>" placeholder="Catatan (opsional)"><?= Helper::e($lead->status_note ?? '') ?></textarea>
      <button onclick="saveStatus(<?= $lead->id ?>, '<?= Helper::e($panelUrl) ?>')">Simpan</button>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
  <div class="cs-pagination">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
    <a href="?page=<?= $i ?>" class="cs-page-btn <?= $page === $i ? 'active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>

</div><!-- /cs-body -->

<script>
function toggleStatus(id) {
  var sf = document.getElementById('sf-' + id);
  sf.classList.toggle('open');
}

function saveStatus(leadId, panelUrl) {
  var status = document.getElementById('sel-' + leadId).value;
  var note   = document.getElementById('note-' + leadId).value;

  fetch(panelUrl + '/update', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ lead_id: leadId, status: status, note: note })
  })
  .then(function(r) { return r.json(); })
  .then(function(res) {
    if (res.success) {
      var card = document.getElementById('lead-' + leadId);
      if (card) {
        var badges = { new:'#2563eb', contacted:'#d97706', purchased:'#16a34a', cancelled:'#6b7280', blocked:'#dc2626' };
        var labels = { new:'Baru', contacted:'Dihubungi', purchased:'Beli', cancelled:'Batal', blocked:'Blokir' };
        var btn = card.querySelector('.cs-status');
        if (btn) { btn.textContent = (labels[status]||status) + ' ▾'; btn.style.color = badges[status]||'#334155'; }
        document.getElementById('sf-' + leadId).classList.remove('open');
      }
    }
  });
}
</script>
</body>
</html>

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
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title>Panel CS — <?= Helper::e($operator->name) ?></title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif; background: #f1f5f9; color: #0f172a; -webkit-font-smoothing: antialiased; }
  .cs-header { background: #0f172a; color: #fff; padding: 14px 16px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 10; }
  .cs-header h1 { font-size: 16px; font-weight: 700; }
  .cs-header p { font-size: 12px; opacity: .6; }
  .cs-badge { background: #2563eb; color: #fff; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; }
  .cs-body { max-width: 800px; margin: 0 auto; padding: 12px; }
  .cs-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 14px; }
  .cs-stat { background: #fff; border-radius: 10px; padding: 12px; text-align: center; box-shadow: 0 1px 4px rgba(0,0,0,.05); }
  .cs-stat-num { font-size: 22px; font-weight: 800; }
  .cs-stat-label { font-size: 11px; color: #64748b; margin-top: 2px; }

  /* Lead Card */
  .cs-lead { background: #fff; border-radius: 14px; padding: 16px; margin-bottom: 12px; box-shadow: 0 1px 4px rgba(0,0,0,.06); position: relative; }
  .cs-lead.is-blocked { border: 1.5px solid #fecaca; background: #fff7f7; }
  .cs-lead-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 10px; }
  .cs-lead-main { flex: 1; min-width: 0; }
  .cs-lead-name { font-size: 17px; font-weight: 800; color: #0f172a; line-height: 1.3; word-break: break-word; }
  .cs-lead-phone { font-size: 15px; font-weight: 700; color: #2563eb; margin-top: 4px; display: flex; align-items: center; gap: 6px; }
  .cs-lead-camp { font-size: 11px; color: #64748b; margin-top: 4px; }
  .cs-status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; cursor: pointer; border: none; white-space: nowrap; flex-shrink: 0; display: inline-flex; align-items: center; gap: 4px; }

  .cs-followup-badge { display: inline-flex; align-items: center; gap: 4px; font-size: 10px; font-weight: 700; padding: 3px 8px; border-radius: 20px; margin-top: 6px; }
  .cs-followup-done { background: #dcfce7; color: #166534; }
  .cs-followup-pending { background: #fee2e2; color: #b91c1c; }

  .cs-detail-grid { display: grid; grid-template-columns: 1fr; gap: 6px; margin: 10px 0; }
  .cs-detail-item { font-size: 13px; color: #334155; display: flex; align-items: flex-start; gap: 8px; line-height: 1.4; }
  .cs-detail-item b { color: #0f172a; min-width: 70px; flex-shrink: 0; }
  .cs-detail-item span { word-break: break-word; }

  .cs-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; }
  .cs-btn { padding: 10px 16px; border-radius: 10px; font-size: 14px; font-weight: 700; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 6px; flex: 1; min-width: 100px; }
  .cs-btn-wa  { background: #16a34a; color: #fff; }
  .cs-btn-tel { background: #0077b6; color: #fff; }
  .cs-btn-followup { background: #f0fdf4; color: #166534; border: 1.5px solid #86efac; }
  .cs-btn-followup.done { background: #f8fafc; color: #94a3b8; border-color: #e2e8f0; }
  .cs-btn-status { background: #f1f5f9; color: #334155; flex: 0 0 auto; }
  .cs-btn-block  { background: #fef2f2; color: #dc2626; border: 1.5px solid #fecaca; flex: 0 0 auto; }
  .cs-btn-unblock{ background: #f0fdf4; color: #166534; border: 1.5px solid #86efac; flex: 0 0 auto; }
  .cs-date { font-size: 11px; color: #94a3b8; display: block; margin-top: 10px; }

  .cs-empty { text-align: center; padding: 48px 20px; color: #94a3b8; }
  .cs-pagination { display: flex; justify-content: center; gap: 6px; margin-top: 16px; flex-wrap: wrap; }
  .cs-page-btn { padding: 8px 14px; border-radius: 8px; border: 1.5px solid #e2e8f0; background: #fff; font-size: 14px; cursor: pointer; text-decoration: none; color: #334155; font-weight: 600; }
  .cs-page-btn.active { background: #2563eb; color: #fff; border-color: #2563eb; }

  /* Dialog/Modal */
  .cs-dialog-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.5); display: none; align-items: center; justify-content: center; z-index: 100; padding: 16px; }
  .cs-dialog-overlay.open { display: flex; }
  .cs-dialog { background: #fff; border-radius: 16px; width: 100%; max-width: 420px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,.2); }
  .cs-dialog-head { padding: 18px 20px 12px; border-bottom: 1px solid #f1f5f9; }
  .cs-dialog-head h3 { font-size: 16px; font-weight: 800; }
  .cs-dialog-head p { font-size: 12px; color: #64748b; margin-top: 2px; }
  .cs-dialog-body { padding: 16px 20px 20px; }
  .cs-opt { display: flex; align-items: center; gap: 12px; padding: 10px 12px; border: 1.5px solid #e2e8f0; border-radius: 10px; cursor: pointer; margin-bottom: 8px; transition: border-color .15s; }
  .cs-opt.selected, .cs-opt:has(input:checked) { border-color: #2563eb; }
  .cs-opt input { accent-color: #2563eb; }
  .cs-opt-label { font-size: 13px; font-weight: 600; }
  .cs-opt-desc { font-size: 11px; color: #64748b; margin-top: 1px; }
  .cs-field { width: 100%; padding: 10px 12px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 14px; font-family: inherit; margin-top: 12px; }
  .cs-field:focus { outline: none; border-color: #2563eb; }
  .cs-dialog-footer { display: flex; gap: 8px; justify-content: flex-end; margin-top: 16px; }
  .cs-dbtn { padding: 10px 18px; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; border: none; }
  .cs-dbtn-cancel { background: #f1f5f9; color: #334155; }
  .cs-dbtn-save { background: #2563eb; color: #fff; }
  .cs-dbtn-block { background: #dc2626; color: #fff; }

  @media (min-width: 640px) {
    .cs-detail-grid { grid-template-columns: 1fr 1fr; }
    .cs-actions .cs-btn { flex: 0 0 auto; }
  }
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
    $isFollowedUp = !empty($lead->followed_up_at);
    $isBlocked    = $lead->status === 'blocked';
  ?>
  <div class="cs-lead <?= $isBlocked ? 'is-blocked' : '' ?>" id="lead-<?= $lead->id ?>">
    <div class="cs-lead-top">
      <div class="cs-lead-main">
        <div class="cs-lead-name"><?= Helper::e($lead->name ?: 'Tanpa Nama') ?></div>
        <?php if ($lead->phone): ?>
        <div class="cs-lead-phone">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          <?= Helper::e($lead->phone) ?>
        </div>
        <?php endif; ?>
        <div class="cs-lead-camp"><?= Helper::e($camp) ?> <?= $lead->product_name ? '• ' . Helper::e($lead->product_name) : '' ?></div>

        <!-- Follow-up badge -->
        <?php if ($isFollowedUp): ?>
          <div class="cs-followup-badge cs-followup-done">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
            Sudah Follow-Up <?= date('d/m H:i', strtotime($lead->followed_up_at)) ?>
          </div>
        <?php else: ?>
          <div class="cs-followup-badge cs-followup-pending">⏱ Belum Follow-Up</div>
        <?php endif; ?>
      </div>

      <!-- Status badge (clickable jika tidak blocked) -->
      <?php if (!$isBlocked): ?>
      <button class="cs-status-badge" id="status-badge-<?= $lead->id ?>"
        style="background:<?= $st['bg'] ?>;color:<?= $st['color'] ?>"
        onclick="openStatusDialog(<?= $lead->id ?>, '<?= $lead->status ?>')">
        <?= $st['label'] ?> ▾
      </button>
      <?php else: ?>
      <span class="cs-status-badge" style="background:<?= $st['bg'] ?>;color:<?= $st['color'] ?>;cursor:default;">
        🚫 <?= $st['label'] ?>
      </span>
      <?php endif; ?>
    </div>

    <div class="cs-detail-grid">
      <?php if ($lead->email): ?>
      <div class="cs-detail-item"><b>Email</b> <span><?= Helper::e($lead->email) ?></span></div>
      <?php endif; ?>
      <?php if ($lead->quantity): ?>
      <div class="cs-detail-item"><b>Jumlah</b> <span><?= Helper::e($lead->quantity) ?></span></div>
      <?php endif; ?>
      <?php if ($lead->address): ?>
      <div class="cs-detail-item"><b>Alamat</b> <span><?= Helper::e($lead->address) ?></span></div>
      <?php endif; ?>
      <?php if ($lead->custom_message): ?>
      <div class="cs-detail-item"><b>Pesan</b> <span><?= Helper::e($lead->custom_message) ?></span></div>
      <?php endif; ?>
      <?php if (!empty($lead->extra_data)): ?>
        <?php $extra = json_decode($lead->extra_data, true); ?>
        <?php if ($extra && is_array($extra)): ?>
          <?php foreach ($extra as $k => $v): ?>
            <div class="cs-detail-item"><b><?= Helper::e(ucfirst($k)) ?></b> <span><?= Helper::e($v) ?></span></div>
          <?php endforeach; ?>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="cs-actions">
      <?php if (!$isBlocked && $lead->phone): ?>
      <a href="<?= htmlspecialchars($waUrl) ?>" class="cs-btn cs-btn-wa" target="_blank">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
        WA
      </a>
      <?php endif; ?>
      <?php if (!$isBlocked && ($lead->phone || $lead->email)): ?>
      <button class="cs-btn cs-btn-followup <?= $isFollowedUp ? 'done' : '' ?>" id="fu-<?= $lead->id ?>" onclick="doFollowUp(<?= $lead->id ?>)" <?= $isFollowedUp ? 'disabled' : '' ?>>
        <?= $isFollowedUp ? '✓ Sudah Follow-Up' : ($lead->phone ? 'Follow-Up WA' : 'Follow-Up Email') ?>
      </button>
      <?php endif; ?>
      <?php if (!$isBlocked): ?>
      <button class="cs-btn cs-btn-status" onclick="openStatusDialog(<?= $lead->id ?>, '<?= $lead->status ?>')">Ubah Status</button>
      <button class="cs-btn cs-btn-block" onclick="openBlockDialog(<?= $lead->id ?>)">🚫 Blokir</button>
      <?php else: ?>
      <button class="cs-btn cs-btn-unblock" onclick="doUnblock(<?= $lead->id ?>)">✓ Unblock</button>
      <?php endif; ?>
    </div>

    <span class="cs-date">🕒 <?= date('d/m/Y H:i', strtotime($lead->created_at)) ?></span>
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

<!-- ── Status Dialog ── -->
<div class="cs-dialog-overlay" id="statusDialog">
  <div class="cs-dialog">
    <div class="cs-dialog-head">
      <h3>Ubah Status</h3>
      <p>Pilih status baru untuk lead ini</p>
    </div>
    <div class="cs-dialog-body">
      <div id="statusOpts">
        <?php foreach ($statusLabels as $sv => $sl):
          if ($sv === 'blocked') continue;
        ?>
        <label class="cs-opt" data-val="<?= $sv ?>">
          <input type="radio" name="cs_status" value="<?= $sv ?>">
          <div>
            <div class="cs-opt-label" style="color:<?= $sl['color'] ?>;"><?= $sl['label'] ?></div>
          </div>
        </label>
        <?php endforeach; ?>
      </div>
      <input type="text" class="cs-field" id="statusNoteInput" placeholder="Catatan (opsional)">
      <div class="cs-dialog-footer">
        <button class="cs-dbtn cs-dbtn-cancel" onclick="closeDialog('statusDialog')">Batal</button>
        <button class="cs-dbtn cs-dbtn-save" onclick="doStatusSave()">Simpan</button>
      </div>
    </div>
  </div>
</div>

<!-- ── Block Dialog ── -->
<div class="cs-dialog-overlay" id="blockDialog">
  <div class="cs-dialog">
    <div class="cs-dialog-head">
      <h3>Blokir Lead</h3>
      <p>Tentukan metode pemblokiran</p>
    </div>
    <div class="cs-dialog-body">
      <label class="cs-opt selected" id="blk-both" onclick="selectBlock('both')">
        <input type="radio" name="cs_block_by" value="both" checked>
        <div>
          <div class="cs-opt-label">IP + Cookie (Keduanya)</div>
          <div class="cs-opt-desc">Paling kuat — blokir IP dan browser cookie</div>
        </div>
      </label>
      <label class="cs-opt" id="blk-ip" onclick="selectBlock('ip')">
        <input type="radio" name="cs_block_by" value="ip">
        <div>
          <div class="cs-opt-label">IP Address saja</div>
          <div class="cs-opt-desc">Blokir semua user dari IP yang sama</div>
        </div>
      </label>
      <label class="cs-opt" id="blk-cookie" onclick="selectBlock('cookie')">
        <input type="radio" name="cs_block_by" value="cookie">
        <div>
          <div class="cs-opt-label">Cookie / Browser saja</div>
          <div class="cs-opt-desc">Blokir browser spesifik via cookie ID</div>
        </div>
      </label>
      <input type="text" class="cs-field" id="blockReasonInput" placeholder="Alasan pemblokiran (opsional)">
      <div class="cs-dialog-footer">
        <button class="cs-dbtn cs-dbtn-cancel" onclick="closeDialog('blockDialog')">Batal</button>
        <button class="cs-dbtn cs-dbtn-block" onclick="doBlock()">🚫 Blokir</button>
      </div>
    </div>
  </div>
</div>

<script>
var PANEL_URL = <?= json_encode($panelUrl) ?>;
var _activeLeadId = null;

var statusMeta = <?= json_encode(array_map(function($s){ return ['label'=>$s['label'],'color'=>$s['color'],'bg'=>$s['bg']]; }, $statusLabels)) ?>;

// ─── Dialog helpers ───────────────────────────────────────────────────────
function closeDialog(id) {
  document.getElementById(id).classList.remove('open');
}
document.querySelectorAll('.cs-dialog-overlay').forEach(function(el) {
  el.addEventListener('click', function(e) { if (e.target === el) el.classList.remove('open'); });
});

// ─── Status dialog ────────────────────────────────────────────────────────
function openStatusDialog(leadId, currentStatus) {
  _activeLeadId = leadId;
  var radios = document.querySelectorAll('input[name=cs_status]');
  radios.forEach(function(r) { r.checked = (r.value === currentStatus); });
  document.querySelectorAll('#statusOpts .cs-opt').forEach(function(lbl) {
    lbl.classList.toggle('selected', lbl.dataset.val === currentStatus);
    lbl.onclick = function() {
      document.querySelectorAll('#statusOpts .cs-opt').forEach(function(x){ x.classList.remove('selected'); });
      lbl.classList.add('selected');
      lbl.querySelector('input').checked = true;
    };
  });
  document.getElementById('statusNoteInput').value = '';
  document.getElementById('statusDialog').classList.add('open');
}

function doStatusSave() {
  var checked = document.querySelector('input[name=cs_status]:checked');
  if (!checked) return;
  var status = checked.value;
  var note   = document.getElementById('statusNoteInput').value;

  fetch(PANEL_URL + '/update', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ lead_id: _activeLeadId, status: status, note: note })
  })
  .then(function(r) { return r.json(); })
  .then(function(res) {
    if (res.success) {
      var sm = statusMeta[status];
      var badge = document.getElementById('status-badge-' + _activeLeadId);
      if (badge && sm) {
        badge.textContent = sm.label + ' ▾';
        badge.style.background = sm.bg;
        badge.style.color = sm.color;
        badge.setAttribute('onclick', 'openStatusDialog(' + _activeLeadId + ', \'' + status + '\')');
      }
      closeDialog('statusDialog');
    }
  });
}

// ─── Block dialog ─────────────────────────────────────────────────────────
function selectBlock(val) {
  ['both','ip','cookie'].forEach(function(v) {
    var el = document.getElementById('blk-' + v);
    if (el) el.classList.toggle('selected', v === val);
    var r = el ? el.querySelector('input') : null;
    if (r) r.checked = (v === val);
  });
}

function openBlockDialog(leadId) {
  _activeLeadId = leadId;
  selectBlock('both');
  document.getElementById('blockReasonInput').value = '';
  document.getElementById('blockDialog').classList.add('open');
}

function doBlock() {
  var checked = document.querySelector('input[name=cs_block_by]:checked');
  var blockBy = checked ? checked.value : 'both';
  var reason  = document.getElementById('blockReasonInput').value;

  fetch(PANEL_URL + '/block', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ lead_id: _activeLeadId, block_by: blockBy, reason: reason })
  })
  .then(function(r) { return r.json(); })
  .then(function(res) {
    if (res.success) {
      var card = document.getElementById('lead-' + _activeLeadId);
      if (card) {
        card.classList.add('is-blocked');
        // Replace badge
        var badge = document.getElementById('status-badge-' + _activeLeadId);
        if (badge) {
          var span = document.createElement('span');
          span.className = 'cs-status-badge';
          span.style.background = '#fef2f2';
          span.style.color = '#dc2626';
          span.style.cursor = 'default';
          span.textContent = '🚫 Blokir';
          badge.replaceWith(span);
        }
        // Replace actions
        var actions = card.querySelector('.cs-actions');
        if (actions) {
          actions.innerHTML = '<button class="cs-btn cs-btn-unblock" onclick="doUnblock(' + _activeLeadId + ')">✓ Unblock</button>';
        }
      }
      closeDialog('blockDialog');
    } else {
      alert(res.message || 'Gagal memblokir lead.');
    }
  });
}

function doUnblock(leadId) {
  if (!confirm('Cabut blokir lead ini?')) return;
  fetch(PANEL_URL + '/unblock', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ lead_id: leadId })
  })
  .then(function(r) { return r.json(); })
  .then(function(res) {
    if (res.success) {
      // Reload page to show updated state
      location.reload();
    } else {
      alert(res.message || 'Gagal unblock lead.');
    }
  });
}

// ─── Follow-up ────────────────────────────────────────────────────────────
function doFollowUp(leadId) {
  var btn = document.getElementById('fu-' + leadId);
  if (!btn || btn.disabled) return;
  btn.disabled = true;
  btn.textContent = 'Mengirim...';

  fetch(PANEL_URL + '/followup', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ lead_id: leadId })
  })
  .then(function(r) { return r.json(); })
  .then(function(res) {
    if (res.success) {
      btn.classList.add('done');
      btn.textContent = '✓ Sudah Follow-Up';
      var card = document.getElementById('lead-' + leadId);
      if (card) {
        var oldBadges = card.querySelectorAll('.cs-followup-badge');
        oldBadges.forEach(function(b) { b.remove(); });
        var badge = document.createElement('div');
        badge.className = 'cs-followup-badge cs-followup-done';
        var now = new Date();
        var ts = String(now.getDate()).padStart(2,'0') + '/' + String(now.getMonth()+1).padStart(2,'0') + ' ' + String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0');
        badge.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Sudah Follow-Up ' + ts;
        var main = card.querySelector('.cs-lead-main');
        if (main) main.appendChild(badge);
      }
      if (res.url) window.open(res.url, '_blank');
    } else {
      btn.disabled = false;
      btn.textContent = 'Follow-Up';
      alert(res.message || 'Gagal melakukan follow-up.');
    }
  })
  .catch(function() {
    btn.disabled = false;
    btn.textContent = 'Follow-Up';
    alert('Koneksi gagal, coba lagi.');
  });
}
</script>
</body>
</html>

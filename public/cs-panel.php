<?php
/**
 * CS Panel — rendered by CsController::panel()
 * Variables: $operator, $leads (decrypted), $total, $page, $status, $perPage
 */
$csSlug    = Settings::get('cs_panel_slug', 'cs-panel');
$panelUrl  = Helper::siteUrl($csSlug . '/' . $operator->token);
$perPage   = isset($perPage) ? $perPage : 20;
$totalPages = (int)ceil($total / $perPage);
$status    = isset($status) ? $status : '';

$statusMeta = [
    'new'       => ['label'=>'Baru',      'color'=>'#3b82f6','bg'=>'#eff6ff', 'dot'=>'#3b82f6', 'icon'=>'🆕'],
    'contacted' => ['label'=>'Dihubungi', 'color'=>'#f59e0b','bg'=>'#fffbeb', 'dot'=>'#f59e0b', 'icon'=>'📞'],
    'purchased' => ['label'=>'Beli',      'color'=>'#10b981','bg'=>'#ecfdf5', 'dot'=>'#10b981', 'icon'=>'✅'],
    'cancelled' => ['label'=>'Batal',     'color'=>'#6b7280','bg'=>'#f9fafb', 'dot'=>'#9ca3af', 'icon'=>'❌'],
    'blocked'   => ['label'=>'Blokir',    'color'=>'#ef4444','bg'=>'#fef2f2', 'dot'=>'#ef4444', 'icon'=>'🚫'],
];

// Stats dari semua lead operator (tanpa filter status)
$allTotal  = Lead::count(['operator_id' => $operator->operator_id, 'camp_type' => 'form']);
$statNew   = Lead::count(['operator_id' => $operator->operator_id, 'camp_type' => 'form', 'status' => 'new']);
$statCont  = Lead::count(['operator_id' => $operator->operator_id, 'camp_type' => 'form', 'status' => 'contacted']);
$statBuy   = Lead::count(['operator_id' => $operator->operator_id, 'camp_type' => 'form', 'status' => 'purchased']);

$filterTabs = [
    ''          => ['label'=>'Semua',     'count'=>$allTotal],
    'new'       => ['label'=>'Baru',      'count'=>$statNew],
    'contacted' => ['label'=>'Dihubungi', 'count'=>$statCont],
    'purchased' => ['label'=>'Beli',      'count'=>$statBuy],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<meta name="theme-color" content="#1e293b">
<title>CS Panel — <?= Helper::e($operator->name) ?></title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --blue:#3b82f6;--green:#10b981;--amber:#f59e0b;--red:#ef4444;--purple:#8b5cf6;
  --slate:#0f172a;--slate2:#1e293b;--muted:#64748b;--border:#e2e8f0;
  --bg:#f8fafc;--white:#fff;--radius:16px;--shadow:0 2px 8px rgba(0,0,0,.08);
}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg);color:var(--slate);-webkit-font-smoothing:antialiased;min-height:100dvh}

/* ── HEADER ─────────────────────────── */
.hdr{
  background:linear-gradient(135deg,#1e293b 0%,#0f172a 100%);
  color:#fff;padding:14px 16px 12px;
  position:sticky;top:0;z-index:30;
}
.hdr-inner{display:flex;align-items:center;gap:10px}
.hdr-av{width:38px;height:38px;border-radius:50%;background:rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;flex-shrink:0}
.hdr-info{flex:1;min-width:0}
.hdr-name{font-size:15px;font-weight:700;line-height:1.2}
.hdr-sub{font-size:11px;opacity:.5;margin-top:1px}
.hdr-total{background:var(--blue);color:#fff;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700}

/* ── STATS ──────────────────────────── */
.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;padding:14px 14px 0}
.stat{
  background:var(--white);border-radius:14px;padding:13px 8px;
  text-align:center;box-shadow:var(--shadow);position:relative;overflow:hidden;
}
.stat::before{content:'';position:absolute;top:0;left:0;right:0;height:3px}
.stat.s-new::before{background:var(--blue)}
.stat.s-cont::before{background:var(--amber)}
.stat.s-buy::before{background:var(--green)}
.stat-n{font-size:26px;font-weight:900;line-height:1}
.stat-l{font-size:10px;color:var(--muted);margin-top:4px;font-weight:600;text-transform:uppercase;letter-spacing:.4px}

/* ── TABS ───────────────────────────── */
.tabs-wrap{padding:12px 14px 4px;overflow-x:auto;-webkit-overflow-scrolling:touch;scrollbar-width:none}
.tabs-wrap::-webkit-scrollbar{display:none}
.tabs{display:flex;gap:6px;width:max-content}
.tab{
  padding:7px 14px;border-radius:20px;font-size:13px;font-weight:600;
  white-space:nowrap;text-decoration:none;border:1.5px solid var(--border);
  background:var(--white);color:var(--muted);display:inline-flex;align-items:center;gap:5px;
  transition:all .15s;
}
.tab.active{background:var(--blue);color:#fff;border-color:var(--blue)}
.tab-cnt{font-size:11px;background:rgba(0,0,0,.1);padding:1px 7px;border-radius:10px;font-weight:700}
.tab.active .tab-cnt{background:rgba(255,255,255,.25)}

/* ── LEAD LIST ──────────────────────── */
.list{padding:8px 14px 100px}
.empty{text-align:center;padding:60px 20px;color:var(--muted)}
.empty-icon{font-size:52px;margin-bottom:12px;display:block}

/* ── LEAD CARD ──────────────────────── */
.lcard{
  background:var(--white);border-radius:var(--radius);
  margin-bottom:10px;box-shadow:var(--shadow);
  overflow:hidden;border:1px solid var(--border);
  transition:transform .1s;
}
.lcard:active{transform:scale(.99)}
.lcard-head{padding:14px 14px 10px;display:flex;align-items:flex-start;gap:11px}
.lav{
  width:46px;height:46px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-size:19px;font-weight:800;color:#fff;flex-shrink:0;
  position:relative;
}
.lav-dot{
  position:absolute;bottom:1px;right:1px;
  width:12px;height:12px;border-radius:50%;border:2px solid var(--white);
}
.linfo{flex:1;min-width:0}
.lname{font-size:16px;font-weight:800;line-height:1.25;word-break:break-word}
.lphone{
  font-size:14px;font-weight:600;color:var(--blue);margin-top:4px;
  display:inline-flex;align-items:center;gap:5px;text-decoration:none;
}
.lcamp{font-size:11px;color:var(--muted);margin-top:3px;display:flex;align-items:center;gap:4px}

/* FU badge */
.fu-badge{
  display:inline-flex;align-items:center;gap:4px;
  font-size:11px;font-weight:600;padding:3px 9px;border-radius:20px;margin-top:6px;
}
.fu-done{background:#dcfce7;color:#166534}
.fu-pending{background:#fef3c7;color:#92400e}

/* Status pill */
.spill{
  padding:5px 11px;border-radius:20px;font-size:11px;font-weight:700;
  cursor:pointer;border:none;white-space:nowrap;flex-shrink:0;
  display:inline-flex;align-items:center;gap:4px;
  -webkit-tap-highlight-color:transparent;
}

/* Detail */
.ldetail{padding:0 14px 10px;display:flex;flex-direction:column;gap:5px;border-top:1px solid #f8fafc}
.drow{font-size:13px;display:flex;gap:8px;align-items:flex-start}
.dkey{font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.3px;min-width:52px;flex-shrink:0;padding-top:1px}
.dval{color:var(--slate);word-break:break-word;line-height:1.5}

/* ── ACTION BAR ─────────────────────── */
.lactions{
  display:flex;border-top:1px solid var(--border);
}
.abtn{
  flex:1;padding:13px 4px;border:none;background:none;
  font-size:10px;font-weight:700;cursor:pointer;
  display:flex;flex-direction:column;align-items:center;gap:3px;
  color:var(--muted);border-right:1px solid var(--border);
  text-decoration:none;-webkit-tap-highlight-color:transparent;
  transition:background .12s;
}
.abtn:last-child{border-right:none}
.abtn:active{background:var(--bg)}
.abtn-icon{font-size:20px;line-height:1}
.abtn.wa{color:var(--green)}
.abtn.fu{color:var(--blue)}
.abtn.fu.done{color:var(--muted);opacity:.6}
.abtn.stat{color:var(--amber)}
.abtn.blk{color:var(--red)}
.abtn.unblk{color:var(--green)}

.lfoot{padding:6px 14px 10px;font-size:11px;color:#94a3b8;display:flex;align-items:center;gap:4px}

/* ── PAGINATION ─────────────────────── */
.pages{display:flex;justify-content:center;gap:6px;margin-top:4px;flex-wrap:wrap}
.pbtn{
  min-width:40px;padding:9px 12px;border-radius:10px;border:1.5px solid var(--border);
  background:var(--white);font-size:14px;font-weight:600;color:var(--slate);text-decoration:none;
  display:inline-flex;align-items:center;justify-content:center;
}
.pbtn.active{background:var(--blue);color:#fff;border-color:var(--blue)}
.pbtn.disabled{opacity:.4;pointer-events:none}
.page-info{text-align:center;font-size:12px;color:var(--muted);margin-bottom:10px}

/* ── BOTTOM SHEET ───────────────────── */
.overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:flex-end;justify-content:center;z-index:100;backdrop-filter:blur(2px)}
.overlay.open{display:flex}
.sheet{
  background:var(--white);border-radius:22px 22px 0 0;
  width:100%;max-width:540px;
  padding-bottom:max(env(safe-area-inset-bottom,0px),16px);
  max-height:88dvh;overflow-y:auto;
  animation:su .22s cubic-bezier(.34,1.56,.64,1);
}
@keyframes su{from{transform:translateY(50px);opacity:0}to{transform:none;opacity:1}}
.shdrag{width:44px;height:4px;background:#e2e8f0;border-radius:4px;margin:10px auto 0}
.shhead{padding:16px 20px 12px;border-bottom:1px solid #f1f5f9}
.shhead h3{font-size:17px;font-weight:800}
.shhead p{font-size:12px;color:var(--muted);margin-top:3px}
.shbody{padding:14px 16px}
.shopt{
  display:flex;align-items:center;gap:12px;padding:13px 14px;
  border:1.5px solid var(--border);border-radius:13px;cursor:pointer;margin-bottom:8px;
  transition:border-color .15s,background .15s;-webkit-tap-highlight-color:transparent;
}
.shopt.sel{border-color:var(--blue);background:#eff6ff}
.shopt-icon{font-size:22px;width:34px;text-align:center;flex-shrink:0}
.shopt-lbl{font-size:14px;font-weight:700}
.shopt-sub{font-size:11px;color:var(--muted);margin-top:1px}
.shinput{
  width:100%;padding:13px 14px;border:1.5px solid var(--border);
  border-radius:12px;font-size:14px;font-family:inherit;
  margin-top:10px;outline:none;
}
.shinput:focus{border-color:var(--blue)}
.shfoot{display:flex;gap:8px;padding:12px 16px 4px;border-top:1px solid var(--border)}
.sbtn{flex:1;padding:14px;border-radius:13px;font-size:15px;font-weight:700;cursor:pointer;border:none}
.sbtn-cancel{background:#f1f5f9;color:var(--slate)}
.sbtn-save{background:var(--blue);color:#fff}
.sbtn-red{background:var(--red);color:#fff}
</style>
</head>
<body>

<!-- Header -->
<div class="hdr">
  <div class="hdr-inner">
    <div class="hdr-av"><?= strtoupper(substr($operator->name ?? '?', 0, 1)) ?></div>
    <div class="hdr-info">
      <div class="hdr-name"><?= Helper::e($operator->name) ?></div>
      <div class="hdr-sub">Panel CS · Konektor</div>
    </div>
    <span class="hdr-total"><?= number_format($allTotal) ?> Lead</span>
  </div>
</div>

<!-- Stats -->
<div class="stats">
  <div class="stat s-new">
    <div class="stat-n" style="color:var(--blue)"><?= number_format($statNew) ?></div>
    <div class="stat-l">Baru</div>
  </div>
  <div class="stat s-cont">
    <div class="stat-n" style="color:var(--amber)"><?= number_format($statCont) ?></div>
    <div class="stat-l">Dihubungi</div>
  </div>
  <div class="stat s-buy">
    <div class="stat-n" style="color:var(--green)"><?= number_format($statBuy) ?></div>
    <div class="stat-l">Beli</div>
  </div>
</div>

<!-- Filter Tabs -->
<div class="tabs-wrap">
  <div class="tabs">
    <?php foreach ($filterTabs as $val => $tab):
      $href = '?' . http_build_query(array_filter(['status'=>$val,'page'=>1]));
    ?>
    <a href="<?= $href ?>" class="tab <?= $status === $val ? 'active' : '' ?>">
      <?= $tab['label'] ?>
      <span class="tab-cnt"><?= number_format($tab['count']) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Lead List -->
<div class="list">

<?php if ($totalPages > 1): ?>
<div class="page-info">Halaman <?= $page ?> dari <?= $totalPages ?> · <?= number_format($total) ?> lead</div>
<?php endif; ?>

<?php if (empty($leads)): ?>
<div class="empty">
  <span class="empty-icon">📭</span>
  <p style="font-weight:600;font-size:15px;margin-bottom:6px;">Tidak ada lead</p>
  <p style="font-size:13px;">Belum ada lead<?= $status ? ' dengan status "'.$statusMeta[$status]['label'].'"' : '' ?> yang ditugaskan ke Anda.</p>
</div>
<?php else: ?>

<?php foreach ($leads as $lead):
  $st = $statusMeta[$lead->status] ?? $statusMeta['new'];
  $waUrl = Helper::waUrl($lead->phone ?? '');
  $isBlocked    = $lead->status === 'blocked';
  $isFollowedUp = !empty($lead->followed_up_at);
  $hasWa = !$isBlocked && !empty($lead->phone);
  $initials = strtoupper(substr($lead->name ?: '?', 0, 1));
  $colors = ['#3b82f6','#10b981','#f59e0b','#8b5cf6','#06b6d4','#ec4899'];
  $avatarBg = $colors[abs(crc32($lead->name ?? '')) % count($colors)];
  $hasDetail = $lead->email || $lead->quantity || $lead->address || $lead->custom_message;
  $actionCols = ($hasWa ? 1 : 0) + (!$isBlocked ? 3 : 1);
?>
<div class="lcard" id="lead-<?= $lead->id ?>">

  <!-- Head -->
  <div class="lcard-head">
    <div class="lav" style="background:<?= $avatarBg ?>">
      <?= Helper::e($initials) ?>
      <div class="lav-dot" style="background:<?= $st['dot'] ?>"></div>
    </div>
    <div class="linfo">
      <div class="lname"><?= Helper::e($lead->name ?: 'Tanpa Nama') ?></div>
      <?php if ($lead->phone): ?>
      <a href="tel:<?= Helper::e(preg_replace('/[^0-9+]/','',$lead->phone)) ?>" class="lphone">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.13 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.04 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.09 8.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
        <?= Helper::e($lead->phone) ?>
      </a>
      <?php endif; ?>
      <div class="lcamp">
        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h18v18H3z"/><path d="M3 9h18M9 21V9"/></svg>
        <?= Helper::e($lead->campaign_name ?? '—') ?>
        <?php if (!empty($lead->product_name)): ?> · <?= Helper::e($lead->product_name) ?><?php endif; ?>
      </div>
      <?php if ($isFollowedUp): ?>
      <div class="fu-badge fu-done">✓ Follow-Up <?= date('d/m H:i', strtotime($lead->followed_up_at)) ?></div>
      <?php elseif (!$isBlocked): ?>
      <div class="fu-badge fu-pending">⏱ Belum Follow-Up</div>
      <?php endif; ?>
    </div>
    <!-- Status pill -->
    <?php if (!$isBlocked): ?>
    <button class="spill" id="spill-<?= $lead->id ?>"
      style="background:<?= $st['bg'] ?>;color:<?= $st['color'] ?>"
      onclick="openStatus(<?= $lead->id ?>,<?= json_encode($lead->status) ?>)">
      <?= $st['icon'] ?> <?= $st['label'] ?>
    </button>
    <?php else: ?>
    <span class="spill" style="background:<?= $st['bg'] ?>;color:<?= $st['color'] ?>;cursor:default">🚫 Blokir</span>
    <?php endif; ?>
  </div>

  <!-- Detail -->
  <?php if ($hasDetail): ?>
  <div class="ldetail">
    <?php if ($lead->email):          ?><div class="drow"><span class="dkey">Email</span> <span class="dval"><?= Helper::e($lead->email) ?></span></div><?php endif; ?>
    <?php if ($lead->quantity):       ?><div class="drow"><span class="dkey">Jumlah</span><span class="dval"><?= Helper::e($lead->quantity) ?></span></div><?php endif; ?>
    <?php if ($lead->address):        ?><div class="drow"><span class="dkey">Alamat</span><span class="dval"><?= Helper::e($lead->address) ?></span></div><?php endif; ?>
    <?php if ($lead->custom_message): ?><div class="drow"><span class="dkey">Pesan</span> <span class="dval"><?= Helper::e($lead->custom_message) ?></span></div><?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Actions -->
  <div class="lactions">
    <?php if ($hasWa): ?>
    <a href="<?= htmlspecialchars($waUrl) ?>" class="abtn wa" target="_blank">
      <span class="abtn-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg></span>
      <span>WA</span>
    </a>
    <?php endif; ?>
    <?php if (!$isBlocked && ($lead->phone || $lead->email)): ?>
    <button class="abtn fu <?= $isFollowedUp ? 'done' : '' ?>" id="fu-<?= $lead->id ?>" onclick="doFollowUp(<?= $lead->id ?>)" <?= $isFollowedUp ? 'disabled' : '' ?>>
      <span class="abtn-icon"><?= $isFollowedUp ? '✓' : '📲' ?></span>
      <span><?= $isFollowedUp ? 'Done' : 'Follow Up' ?></span>
    </button>
    <?php endif; ?>
    <?php if (!$isBlocked): ?>
    <button class="abtn stat" onclick="openStatus(<?= $lead->id ?>,<?= json_encode($lead->status) ?>)">
      <span class="abtn-icon">🏷️</span>
      <span>Status</span>
    </button>
    <button class="abtn blk" onclick="openBlock(<?= $lead->id ?>)">
      <span class="abtn-icon">🚫</span>
      <span>Blokir</span>
    </button>
    <?php else: ?>
    <button class="abtn unblk" onclick="doUnblock(<?= $lead->id ?>)" style="flex:4">
      <span class="abtn-icon">🔓</span>
      <span>Unblock</span>
    </button>
    <?php endif; ?>
  </div>

  <div class="lfoot">
    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    <?= date('d/m/Y H:i', strtotime($lead->created_at)) ?>
  </div>

</div>
<?php endforeach; ?>

<!-- Pagination -->
<?php if ($totalPages > 1):
  $qBase = array_filter(['status' => $status]);
?>
<div class="pages">
  <?php if ($page > 1): ?>
  <a href="?<?= http_build_query(array_merge($qBase, ['page'=>$page-1])) ?>" class="pbtn">‹</a>
  <?php endif; ?>
  <?php
  $from = max(1, $page - 2);
  $to   = min($totalPages, $page + 2);
  if ($from > 1): ?><a href="?<?= http_build_query(array_merge($qBase,['page'=>1])) ?>" class="pbtn">1</a><?php if ($from > 2): ?><span class="pbtn disabled">…</span><?php endif; endif;
  for ($i = $from; $i <= $to; $i++):
  ?><a href="?<?= http_build_query(array_merge($qBase,['page'=>$i])) ?>" class="pbtn <?= $i===$page?'active':'' ?>"><?= $i ?></a><?php
  endfor;
  if ($to < $totalPages): if ($to < $totalPages-1): ?><span class="pbtn disabled">…</span><?php endif; ?><a href="?<?= http_build_query(array_merge($qBase,['page'=>$totalPages])) ?>" class="pbtn"><?= $totalPages ?></a><?php endif; ?>
  <?php if ($page < $totalPages): ?>
  <a href="?<?= http_build_query(array_merge($qBase, ['page'=>$page+1])) ?>" class="pbtn">›</a>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php endif; ?>
</div><!-- /list -->

<!-- ── Status Sheet ── -->
<div class="overlay" id="statusSheet">
  <div class="sheet">
    <div class="shdrag"></div>
    <div class="shhead"><h3>Ubah Status Lead</h3><p>Pilih status baru untuk lead ini</p></div>
    <div class="shbody">
      <?php foreach ($statusMeta as $sv => $sm): if ($sv === 'blocked') continue; ?>
      <div class="shopt" id="sopt-<?= $sv ?>" data-val="<?= $sv ?>" onclick="selStatus('<?= $sv ?>')">
        <span class="shopt-icon"><?= $sm['icon'] ?></span>
        <div>
          <div class="shopt-lbl" style="color:<?= $sm['color'] ?>"><?= $sm['label'] ?></div>
        </div>
      </div>
      <?php endforeach; ?>
      <input type="text" class="shinput" id="sNote" placeholder="Catatan (opsional)">
    </div>
    <div class="shfoot">
      <button class="sbtn sbtn-cancel" onclick="closeSheet('statusSheet')">Batal</button>
      <button class="sbtn sbtn-save" onclick="doStatusSave()">Simpan</button>
    </div>
  </div>
</div>

<!-- ── Block Sheet ── -->
<div class="overlay" id="blockSheet">
  <div class="sheet">
    <div class="shdrag"></div>
    <div class="shhead"><h3>Blokir Lead</h3><p>Pilih metode pemblokiran</p></div>
    <div class="shbody">
      <div class="shopt sel" id="blk-both" onclick="selBlock('both')">
        <span class="shopt-icon">🔒</span>
        <div><div class="shopt-lbl">IP + Cookie</div><div class="shopt-sub">Paling kuat — blokir IP dan browser</div></div>
      </div>
      <div class="shopt" id="blk-ip" onclick="selBlock('ip')">
        <span class="shopt-icon">🌐</span>
        <div><div class="shopt-lbl">IP Address saja</div><div class="shopt-sub">Blokir semua user dari IP yang sama</div></div>
      </div>
      <div class="shopt" id="blk-cookie" onclick="selBlock('cookie')">
        <span class="shopt-icon">🍪</span>
        <div><div class="shopt-lbl">Cookie / Browser saja</div><div class="shopt-sub">Blokir browser ini saja via cookie</div></div>
      </div>
      <input type="text" class="shinput" id="bReason" placeholder="Alasan (opsional)">
    </div>
    <div class="shfoot">
      <button class="sbtn sbtn-cancel" onclick="closeSheet('blockSheet')">Batal</button>
      <button class="sbtn sbtn-red" onclick="doBlock()">🚫 Blokir</button>
    </div>
  </div>
</div>

<script>
var PANEL_URL  = <?= json_encode($panelUrl) ?>;
var statusMeta = <?= json_encode(array_map(function($s){ return ['label'=>$s['label'],'color'=>$s['color'],'bg'=>$s['bg'],'dot'=>$s['dot'],'icon'=>$s['icon']]; }, $statusMeta)) ?>;
var _lid = null, _selSt = null;

// Sheet
function closeSheet(id){ document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.overlay').forEach(function(el){
  el.addEventListener('click',function(e){ if(e.target===el) el.classList.remove('open'); });
});

// Status
function selStatus(v){
  _selSt = v;
  document.querySelectorAll('[id^=sopt-]').forEach(function(el){ el.classList.toggle('sel', el.dataset.val===v); });
}
function openStatus(id, cur){
  _lid=id; selStatus(cur);
  document.getElementById('sNote').value='';
  document.getElementById('statusSheet').classList.add('open');
}
function doStatusSave(){
  if(!_selSt) return;
  fetch(PANEL_URL+'/update',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({lead_id:_lid,status:_selSt,note:document.getElementById('sNote').value})})
  .then(function(r){return r.json();})
  .then(function(res){
    if(res.success){
      var sm=statusMeta[_selSt];
      var p=document.getElementById('spill-'+_lid);
      if(p&&sm){p.textContent=sm.icon+' '+sm.label;p.style.background=sm.bg;p.style.color=sm.color;p.setAttribute('onclick','openStatus('+_lid+',\''+_selSt+'\')');}
      var av=document.querySelector('#lead-'+_lid+' .lav-dot');
      if(av&&sm) av.style.background=sm.dot;
      closeSheet('statusSheet');
    }
  });
}

// Block
function selBlock(v){
  ['both','ip','cookie'].forEach(function(x){ var el=document.getElementById('blk-'+x); if(el) el.classList.toggle('sel',x===v); });
}
function openBlock(id){
  _lid=id; selBlock('both');
  document.getElementById('bReason').value='';
  document.getElementById('blockSheet').classList.add('open');
}
function doBlock(){
  var sel=document.querySelector('#blockSheet .shopt.sel');
  var by=sel?sel.id.replace('blk-',''):'both';
  fetch(PANEL_URL+'/block',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({lead_id:_lid,block_by:by,reason:document.getElementById('bReason').value})})
  .then(function(r){return r.json();})
  .then(function(res){
    if(res.success){
      var card=document.getElementById('lead-'+_lid);
      if(card){
        var p=document.getElementById('spill-'+_lid);
        if(p){var s=document.createElement('span');s.className='spill';s.style.cssText='background:#fef2f2;color:#ef4444;cursor:default';s.textContent='🚫 Blokir';p.replaceWith(s);}
        var dot=card.querySelector('.lav-dot');if(dot)dot.style.background='#ef4444';
        var acts=card.querySelector('.lactions');
        if(acts)acts.innerHTML='<button class="abtn unblk" onclick="doUnblock('+_lid+')" style="flex:4"><span class="abtn-icon">🔓</span><span>Unblock</span></button>';
      }
      closeSheet('blockSheet');
    }else{alert(res.message||'Gagal memblokir.');}
  });
}

function doUnblock(id){
  if(!confirm('Cabut blokir lead ini?')) return;
  fetch(PANEL_URL+'/unblock',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({lead_id:id})})
  .then(function(r){return r.json();})
  .then(function(res){ if(res.success) location.reload(); else alert(res.message||'Gagal.'); });
}

// Follow Up
function doFollowUp(id){
  var btn=document.getElementById('fu-'+id);
  if(!btn||btn.disabled) return;
  btn.disabled=true;
  var lbl=btn.querySelector('span:last-child'); if(lbl) lbl.textContent='...';
  fetch(PANEL_URL+'/followup',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({lead_id:id})})
  .then(function(r){return r.json();})
  .then(function(res){
    if(res.success){
      btn.classList.add('done');
      var ico=btn.querySelector('.abtn-icon'); if(ico) ico.textContent='✓';
      if(lbl) lbl.textContent='Done';
      var card=document.getElementById('lead-'+id);
      if(card){
        var old=card.querySelector('.fu-badge');
        if(old){old.className='fu-badge fu-done';var n=new Date();old.textContent='✓ Follow-Up '+String(n.getDate()).padStart(2,'0')+'/'+String(n.getMonth()+1).padStart(2,'0')+' '+String(n.getHours()).padStart(2,'0')+':'+String(n.getMinutes()).padStart(2,'0');}
      }
      if(res.url) window.open(res.url,'_blank');
    }else{btn.disabled=false;if(lbl)lbl.textContent='Follow Up';alert(res.message||'Gagal.');}
  })
  .catch(function(){btn.disabled=false;if(lbl)lbl.textContent='Follow Up';alert('Koneksi gagal.');});
}
</script>
</body>
</html>

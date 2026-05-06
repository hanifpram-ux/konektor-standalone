<?php
require_once dirname(__DIR__, 3) . '/admin/inc/bootstrap.php';
$pageTitle = 'Leads';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['_update_status'])) {
        Lead::updateStatus((int)$_POST['lead_id'], isset($_POST['status'])?$_POST['status']:'', isset($_POST['note'])?$_POST['note']:'');
        header('Content-Type: application/json');
        echo json_encode(['ok'=>true]); exit;
    }
    if (!empty($_POST['_delete_id'])) {
        Lead::delete((int)$_POST['_delete_id']);
        flashSet('success', 'Lead dihapus.');
        header('Location: index.php?'.http_build_query(array_filter([
            'campaign_id' => isset($_GET['campaign_id'])?$_GET['campaign_id']:'',
            'status'      => isset($_GET['status'])?$_GET['status']:'',
            'search'      => isset($_GET['search'])?$_GET['search']:'',
            'type'        => isset($_GET['type'])?$_GET['type']:'',
        ]))); exit;
    }
    if (!empty($_POST['_block_id'])) {
        $l = Lead::find((int)$_POST['_block_id']);
        if ($l) {
            $d = Lead::decrypt(clone $l);
            Blocker::block([
                'campaign_id'  => $l->campaign_id,
                'ip_address'   => $l->ip_address,
                'fingerprint'  => $l->fingerprint,
                'cookie_id'    => $l->cookie_id,
                'phone'        => $d->phone,
                'email'        => $d->email,
                'reason'       => strip_tags(isset($_POST['block_reason'])?$_POST['block_reason']:'Blocked by admin'),
                'blocked_by'   => Auth::id(),
                'operator_name'=> 'Admin',
            ]);
            Lead::updateStatus($l->id, 'blocked');
            flashSet('success', 'Lead diblokir.');
        }
        header('Location: index.php'); exit;
    }
    if (!empty($_POST['_export'])) {
        $fCampExp = (int)(isset($_POST['f_camp']) ? $_POST['f_camp'] : 0);
        $expType  = isset($_POST['f_type']) ? $_POST['f_type'] : '';
        $expArgs  = array_filter([
            'campaign_id' => $fCampExp ?: null,
            'status'      => isset($_POST['f_status']) ? $_POST['f_status'] : '',
            'camp_type'   => $expType === 'link' ? 'wa_link' : ($expType === 'form' ? 'form' : ''),
        ]);
        $rows = Lead::exportCsv($expArgs);
        $fname = ($expType === 'link' ? 'klik-link-' : 'leads-') . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.$fname.'"');
        $out = fopen('php://output','w'); fprintf($out,"\xEF\xBB\xBF");
        foreach ($rows as $r) fputcsv($out,$r); fclose($out); exit;
    }
}

$fCamp   = (int)(isset($_GET['campaign_id']) ? $_GET['campaign_id'] : 0);
$fStatus = isset($_GET['status']) ? $_GET['status'] : '';
$fSearch = isset($_GET['search']) ? $_GET['search'] : '';
$fType   = isset($_GET['type'])   ? $_GET['type']   : ''; // 'form' | 'link' | ''
$page    = max(1, (int)(isset($_GET['page']) ? $_GET['page'] : 1));
$perPage = 30;

$campTypeArg = $fType === 'link' ? 'wa_link' : ($fType === 'form' ? 'form' : '');
$args = array_filter([
    'campaign_id' => $fCamp ?: null,
    'status'      => $fStatus,
    'search'      => $fSearch,
    'camp_type'   => $campTypeArg ?: null,
    'page'        => $page,
    'per_page'    => $perPage,
]);
$leads = array_map(function($l){ return Lead::decrypt($l); }, Lead::all($args));
$total = Lead::count(array_filter([
    'campaign_id' => $fCamp ?: null,
    'status'      => $fStatus,
    'camp_type'   => $campTypeArg ?: null,
]));
$pages = (int)ceil($total / $perPage);
$camps = Campaign::all();

$totalForm = Lead::count(['camp_type' => 'form']);
$totalLink = Lead::count(['camp_type' => 'wa_link']);

$sb = ['new'=>'badge-blue','contacted'=>'badge-warning','purchased'=>'badge-success','cancelled'=>'badge-secondary','blocked'=>'badge-destructive'];
$sl = ['new'=>'Baru','contacted'=>'Dihubungi','purchased'=>'Beli','cancelled'=>'Batal','blocked'=>'Blokir'];

include dirname(__DIR__, 2) . '/inc/head.php';
include dirname(__DIR__, 2) . '/inc/sidebar.php';
?>
<div class="page-wrapper">

  <div class="page-header">
    <div>
      <h1 class="page-title">Leads</h1>
      <p class="page-desc"><?= number_format($total) ?> lead<?= $fType ? ' ('.$fType.')' : '' ?></p>
    </div>
    <form method="POST" style="display:inline;">
      <input type="hidden" name="_export" value="1">
      <input type="hidden" name="f_type"   value="<?= ae($fType) ?>">
      <input type="hidden" name="f_camp"   value="<?= $fCamp ?>">
      <input type="hidden" name="f_status" value="<?= ae($fStatus) ?>">
      <button class="btn btn-outline btn-sm"><?= icon('download') ?> Export CSV</button>
    </form>
  </div>

  <?php include dirname(__DIR__, 2) . '/inc/flash.php'; ?>

  <!-- Tab jenis kampanye -->
  <div class="tabs-list" style="margin-bottom:16px;">
    <a href="?<?= http_build_query(array_filter(['campaign_id'=>$fCamp,'status'=>$fStatus,'search'=>$fSearch])) ?>"
       class="tab-trigger <?= $fType===''?'active':'' ?>">
      Semua
      <span class="badge badge-secondary" style="margin-left:6px;font-size:10px;"><?= number_format($totalForm + $totalLink) ?></span>
    </a>
    <a href="?type=form&<?= http_build_query(array_filter(['campaign_id'=>$fCamp,'status'=>$fStatus,'search'=>$fSearch])) ?>"
       class="tab-trigger <?= $fType==='form'?'active':'' ?>">
      <?= icon('file-text','',13) ?> Form Lead
      <span class="badge <?= $fType==='form'?'badge-blue':'badge-secondary' ?>" style="margin-left:6px;font-size:10px;"><?= number_format($totalForm) ?></span>
    </a>
    <a href="?type=link&<?= http_build_query(array_filter(['campaign_id'=>$fCamp,'status'=>$fStatus,'search'=>$fSearch])) ?>"
       class="tab-trigger <?= $fType==='link'?'active':'' ?>">
      <?= icon('link','',13) ?> Klik Link
      <span class="badge <?= $fType==='link'?'badge-blue':'badge-secondary' ?>" style="margin-left:6px;font-size:10px;"><?= number_format($totalLink) ?></span>
    </a>
  </div>

  <!-- Filter -->
  <form method="GET" style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
    <?php if ($fType): ?><input type="hidden" name="type" value="<?= ae($fType) ?>"><?php endif; ?>
    <div style="position:relative;flex:1;min-width:180px;">
      <div style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:hsl(var(--muted-foreground));"><?= icon('search','',14) ?></div>
      <input name="search" value="<?= ae($fSearch) ?>" class="input" style="padding-left:34px;" placeholder="Cari nama, HP, email, IP...">
    </div>
    <select name="status" class="input" style="width:150px;">
      <option value="">Semua Status</option>
      <?php foreach ($sl as $v=>$l): ?><option value="<?= $v ?>" <?= $fStatus===$v?'selected':'' ?>><?= ae($l) ?></option><?php endforeach; ?>
    </select>
    <select name="campaign_id" class="input" style="width:190px;">
      <option value="">Semua Kampanye</option>
      <?php foreach ($camps as $c):
        if ($fType === 'form' && $c->type !== 'form') continue;
        if ($fType === 'link' && $c->type !== 'wa_link') continue;
      ?>
      <option value="<?= $c->id ?>" <?= $fCamp==$c->id?'selected':'' ?>><?= ae($c->name) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-default btn-sm"><?= icon('filter') ?> Filter</button>
    <a href="?<?= $fType?'type='.ae($fType):'' ?>" class="btn btn-outline btn-sm"><?= icon('x') ?></a>
  </form>

  <!-- Tabel -->
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th style="width:32px;"></th>
          <th>Identitas</th>
          <th>Kampanye &amp; CS</th>
          <th>Status</th>
          <th>Perangkat &amp; IP</th>
          <th>Waktu</th>
          <th style="width:88px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($leads)): ?>
        <tr><td colspan="7" style="text-align:center;padding:48px;color:hsl(var(--muted-foreground));">
          Tidak ada lead ditemukan.
        </td></tr>
        <?php else: ?>
        <?php foreach ($leads as $l):
          $ua       = isset($l->user_agent) ? $l->user_agent : '';
          $isMobile = (bool)preg_match('/Mobile|Android|iPhone|iPad/i', $ua);
          $browser  = 'Browser';
          if      (preg_match('/Edg\/(\d+)/i',     $ua, $bm)) $browser = 'Edge '.$bm[1];
          elseif  (preg_match('/OPR\/(\d+)/i',     $ua, $bm)) $browser = 'Opera '.$bm[1];
          elseif  (preg_match('/Chrome\/(\d+)/i',  $ua, $bm)) $browser = 'Chrome '.$bm[1];
          elseif  (preg_match('/Firefox\/(\d+)/i', $ua, $bm)) $browser = 'Firefox '.$bm[1];
          elseif  (preg_match('/Safari\//i',        $ua))      $browser = 'Safari';
          $campType = isset($l->campaign_type) ? $l->campaign_type : 'form';
          $isLink   = $campType === 'wa_link';
          $waUrl    = (!$isLink && $l->phone) ? Helper::waUrl($l->phone) : '';
          $src      = isset($l->source_url) ? $l->source_url : (isset($l->referrer) ? $l->referrer : '');
          $srcHost  = $src ? (parse_url($src, PHP_URL_HOST) ?: substr($src, 0, 30)) : '';
        ?>
        <tr style="<?= $l->is_double?'background:hsl(38 92% 50%/0.04);':'' ?>" id="lead-row-<?= $l->id ?>">

          <!-- Tipe icon -->
          <td style="padding-right:0;">
            <div title="<?= $isLink?'Klik Link':'Form Lead' ?>"
              style="width:28px;height:28px;border-radius:6px;background:<?= $isLink?'hsl(142 76% 36%/0.1)':'hsl(217 91% 60%/0.1)' ?>;display:flex;align-items:center;justify-content:center;color:<?= $isLink?'hsl(142 76% 36%)':'hsl(217 91% 60%)' ?>;">
              <?= icon($isLink?'link':'file-text','',13) ?>
            </div>
          </td>

          <!-- Identitas -->
          <td>
            <?php if ($isLink): ?>
              <!-- Link: tidak ada nama/HP, tampilkan IP sebagai identitas utama -->
              <div style="font-family:monospace;font-size:13px;font-weight:600;"><?= ae($l->ip_address?:'—') ?></div>
              <?php if ($l->cookie_id): ?>
              <div style="font-size:10px;color:hsl(var(--muted-foreground));font-family:monospace;margin-top:2px;" title="<?= ae($l->cookie_id) ?>">
                vid: <?= ae(substr($l->cookie_id, 0, 10)).'…' ?>
              </div>
              <?php endif; ?>
            <?php else: ?>
              <!-- Form: nama + HP/email -->
              <div style="font-weight:500;font-size:13px;"><?= ae($l->name ?: '—') ?></div>
              <?php if ($l->phone): ?>
              <div style="font-family:monospace;font-size:12px;color:hsl(var(--muted-foreground));margin-top:1px;"><?= ae($l->phone) ?></div>
              <?php endif; ?>
              <?php if ($l->email): ?>
              <div style="font-size:11px;color:hsl(var(--muted-foreground));margin-top:1px;"><?= ae($l->email) ?></div>
              <?php endif; ?>
            <?php endif; ?>
            <?php if ($l->is_double): ?>
            <span class="badge badge-warning" style="margin-top:3px;font-size:10px;"><?= $isLink?'Kunjungan Ulang':'Duplikat' ?></span>
            <?php endif; ?>
          </td>

          <!-- Kampanye & CS -->
          <td>
            <div style="font-size:12px;font-weight:500;"><?= ae(isset($l->campaign_name)?$l->campaign_name:'—') ?></div>
            <?php if (isset($l->operator_name) && $l->operator_name): ?>
            <div style="font-size:11px;color:hsl(var(--muted-foreground));margin-top:2px;display:flex;align-items:center;gap:3px;">
              <?= icon('user','',11) ?> <?= ae($l->operator_name) ?>
            </div>
            <?php endif; ?>
            <?php if ($waUrl): ?>
            <a href="<?= ae($waUrl) ?>" target="_blank" style="font-size:11px;color:hsl(142 76% 36%);text-decoration:none;display:inline-flex;align-items:center;gap:3px;margin-top:2px;">
              <?= icon('message-square','',11) ?> Chat WA
            </a>
            <?php endif; ?>
          </td>

          <!-- Status -->
          <td>
            <select onchange="updateLeadStatus(<?= $l->id ?>,this.value,this)"
              class="badge <?= isset($sb[$l->status])?$sb[$l->status]:'badge-secondary' ?>"
              style="border:none;outline:none;cursor:pointer;background:inherit;color:inherit;font-weight:inherit;font-size:inherit;padding:2px 8px;border-radius:9999px;appearance:none;">
              <?php foreach ($sl as $sv=>$slbl): ?>
              <option value="<?= $sv ?>" <?= $l->status===$sv?'selected':'' ?>><?= ae($slbl) ?></option>
              <?php endforeach; ?>
            </select>
          </td>

          <!-- Perangkat & IP -->
          <td>
            <div style="display:flex;align-items:center;gap:6px;">
              <span style="color:hsl(var(--muted-foreground));">
                <?= icon($isMobile?'phone':'monitor','',13) ?>
              </span>
              <div>
                <div style="font-size:12px;"><?= ae($browser) ?> &middot; <?= $isMobile?'Mobile':'Desktop' ?></div>
                <?php if ($l->ip_address && !$isLink): ?>
                <div style="font-size:11px;color:hsl(var(--muted-foreground));font-family:monospace;"><?= ae($l->ip_address) ?></div>
                <?php endif; ?>
                <?php if ($srcHost): ?>
                <div style="font-size:10px;color:hsl(var(--muted-foreground));margin-top:1px;" title="<?= ae($src) ?>"><?= ae($srcHost) ?></div>
                <?php endif; ?>
              </div>
            </div>
          </td>

          <!-- Waktu -->
          <td style="white-space:nowrap;">
            <div style="font-size:12px;"><?= date('d/m/Y', strtotime($l->created_at)) ?></div>
            <div style="font-size:11px;color:hsl(var(--muted-foreground));"><?= date('H:i:s', strtotime($l->created_at)) ?></div>
          </td>

          <!-- Aksi -->
          <td>
            <div style="display:flex;gap:3px;">
              <button onclick="toggleDetail(<?= $l->id ?>)" class="btn btn-ghost btn-sm btn-icon" title="Detail"><?= icon('eye') ?></button>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus lead ini?')">
                <input type="hidden" name="_delete_id" value="<?= $l->id ?>">
                <button class="btn btn-ghost btn-sm btn-icon" style="color:hsl(var(--destructive));"><?= icon('trash-2') ?></button>
              </form>
              <?php if (!$isLink && $l->status !== 'blocked'): ?>
              <button onclick="showBlock(<?= $l->id ?>)" class="btn btn-ghost btn-sm btn-icon" title="Blokir"><?= icon('shield-off') ?></button>
              <?php endif; ?>
            </div>
          </td>
        </tr>

        <!-- Detail row -->
        <tr id="row-detail-<?= $l->id ?>" class="hidden">
          <td colspan="7" style="background:hsl(var(--muted));padding:14px 16px 14px 52px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;margin-bottom:12px;">
              <?php
              $detailItems = $isLink ? [
                  ['IP Address',   $l->ip_address],
                  ['Perangkat',    ($isMobile?'Mobile':'Desktop').' · '.$browser],
                  ['Cookie ID',    isset($l->cookie_id)?$l->cookie_id:''],
                  ['Source URL',   $src],
                  ['Referrer',     isset($l->referrer)?$l->referrer:''],
                  ['User Agent',   $ua],
                  ['Fingerprint',  isset($l->fingerprint)?$l->fingerprint:''],
                  ['Click ID',     isset($l->extra_data)&&$l->extra_data ? (isset(json_decode($l->extra_data,true)['click_id'])?json_decode($l->extra_data,true)['click_id']:'') : ''],
              ] : [
                  ['Alamat',      isset($l->address)?$l->address:''],
                  ['Jumlah',      isset($l->quantity)?$l->quantity:''],
                  ['Pesan',       isset($l->custom_message)?$l->custom_message:''],
                  ['IP Address',  $l->ip_address],
                  ['Perangkat',   ($isMobile?'Mobile':'Desktop').' · '.$browser],
                  ['Source URL',  $src],
                  ['User Agent',  $ua],
                  ['Referrer',    isset($l->referrer)?$l->referrer:''],
              ];
              foreach ($detailItems as $item):
                  if (!$item[1]) continue;
                  $wide = in_array($item[0], ['User Agent','Source URL','Referrer','Click ID','Fingerprint']);
              ?>
              <div <?= $wide?'style="grid-column:span 2;"':'' ?>>
                <div style="font-size:10px;color:hsl(var(--muted-foreground));font-weight:600;text-transform:uppercase;letter-spacing:.4px;margin-bottom:2px;"><?= ae($item[0]) ?></div>
                <div style="font-size:12px;word-break:break-all;"><?= ae($item[1]) ?></div>
              </div>
              <?php endforeach; ?>
            </div>
            <?php if (!$isLink): ?>
            <div style="display:flex;gap:8px;align-items:center;">
              <input id="note-<?= $l->id ?>" type="text" value="<?= ae(isset($l->status_note)?$l->status_note:'') ?>"
                class="input" style="flex:1;padding:6px 12px;font-size:12px;" placeholder="Tambahkan catatan...">
              <button class="btn btn-default btn-sm knk-save-note"
                data-id="<?= $l->id ?>" data-status="<?= ae($l->status) ?>">
                <?= icon('save') ?> Simpan
              </button>
            </div>
            <?php endif; ?>
          </td>
        </tr>

        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php
    $q = array_filter(['type'=>$fType,'search'=>$fSearch,'status'=>$fStatus,'campaign_id'=>$fCamp?:null]);
    for ($p = max(1,$page-3); $p <= min($pages,$page+3); $p++):
    ?>
    <a href="?<?= http_build_query(array_merge($q,['page'=>$p])) ?>"
       class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>

</div><!-- /page-wrapper -->

<!-- Block modal -->
<div id="blockModal" class="dialog-overlay" style="display:none;">
  <div class="dialog-content" style="max-width:420px;">
    <div class="dialog-header">
      <h3 class="dialog-title">Blokir Lead</h3>
      <p class="dialog-desc">Lead ini tidak akan bisa submit form lagi.</p>
    </div>
    <div class="dialog-body">
      <form id="blockForm" method="POST">
        <input type="hidden" name="_block_id" id="blockLeadId">
        <label class="label">Alasan Pemblokiran</label>
        <input name="block_reason" class="input" placeholder="Spam, tidak valid, dll.">
        <div style="display:flex;gap:8px;margin-top:16px;justify-content:flex-end;">
          <button type="button" onclick="document.getElementById('blockModal').style.display='none'" class="btn btn-outline">Batal</button>
          <button type="submit" class="btn btn-destructive"><?= icon('shield-off') ?> Blokir</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
var sbMap = <?= json_encode($sb) ?>;

function toggleDetail(id) {
  var row = document.getElementById('row-detail-'+id);
  if (row) row.classList.toggle('hidden');
}

function showBlock(id) {
  document.getElementById('blockLeadId').value = id;
  document.getElementById('blockModal').style.display = 'flex';
}

document.getElementById('blockModal').addEventListener('click', function(e) {
  if (e.target === this) this.style.display = 'none';
});

function updateLeadStatus(id, status, el) {
  fetch('index.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: '_update_status=1&lead_id=' + id + '&status=' + status
  })
  .then(function(r){ return r.json(); })
  .then(function(res) {
    if (res.ok) {
      el.className = 'badge ' + (sbMap[status] || 'badge-secondary');
      el.style.cssText = 'border:none;outline:none;cursor:pointer;background:inherit;color:inherit;font-weight:inherit;font-size:inherit;padding:2px 8px;border-radius:9999px;appearance:none;';
    }
  });
}

// Save note buttons
document.addEventListener('click', function(e) {
  var btn = e.target.closest('.knk-save-note');
  if (!btn) return;
  var id     = btn.getAttribute('data-id');
  var status = btn.getAttribute('data-status');
  var note   = document.getElementById('note-'+id);
  if (!note) return;
  fetch('index.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: '_update_status=1&lead_id='+id+'&status='+encodeURIComponent(status)+'&note='+encodeURIComponent(note.value)
  })
  .then(function(r){ return r.json(); })
  .then(function(res){ if (res.ok) { btn.textContent = 'Tersimpan'; setTimeout(function(){ btn.innerHTML = '<?= addslashes(icon('save')) ?> Simpan'; }, 1500); } });
});
</script>

<?php include dirname(__DIR__, 2) . '/inc/foot.php'; ?>

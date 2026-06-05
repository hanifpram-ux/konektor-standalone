<?php
require_once dirname(__DIR__, 3) . '/admin/inc/bootstrap.php';
$pageTitle = 'Analitik';

$days      = (int)($_GET['days'] ?? 30);
$campId    = (int)($_GET['campaign_id'] ?? 0);
$dateFrom  = isset($_GET['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from']) ? $_GET['date_from'] : null;
$dateTo    = isset($_GET['date_to'])   && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to'])   ? $_GET['date_to']   : null;
$isCustom  = ($dateFrom || $dateTo);

// If custom range not set, derive from $days
if (!$isCustom) {
    $dateTo   = date('Y-m-d');
    $dateFrom = date('Y-m-d', strtotime("-{$days} days"));
}
[$dateFrom, $dateTo] = Analytics::resolveDateRange($days, $dateFrom, $dateTo);

$camps   = Campaign::all(['status'=>'active']);
$daily   = Analytics::getDailyLeads($campId, $days, $dateFrom, $dateTo);
$stats   = $campId ? Analytics::getCampaignStats($campId, $days, $dateFrom, $dateTo) : Analytics::getOverallStats();
$opStats = Analytics::getOperatorStats();
$dates   = array_column($daily,'date');
$counts  = array_column($daily,'leads');

// Label for header
$rangeLabel = ($dateFrom === $dateTo) ? $dateFrom : $dateFrom . ' s/d ' . $dateTo;

include dirname(__DIR__, 2) . '/inc/head.php';
include dirname(__DIR__, 2) . '/inc/sidebar.php';
?>
<style>
#customRange { display:none; align-items:center; gap:6px; }
#customRange.show { display:flex; }
.date-sep { color:hsl(var(--muted-foreground)); font-size:13px; }
</style>
<div class="page-wrapper">
  <div class="page-header">
    <div>
      <h1 class="page-title">Analitik</h1>
      <p class="page-desc">Periode: <?= ae($rangeLabel) ?></p>
    </div>
    <form method="GET" id="analyticsForm" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
      <select name="campaign_id" class="input" style="width:200px;">
        <option value="">Semua Kampanye</option>
        <?php foreach ($camps as $c): ?><option value="<?= $c->id ?>" <?= $campId==$c->id?'selected':'' ?>><?= ae($c->name) ?></option><?php endforeach; ?>
      </select>
      <select name="days" id="daysSelect" class="input" style="width:130px;" onchange="onDaysChange(this.value)">
        <?php foreach ([7,14,30,60,90] as $d): ?><option value="<?= $d ?>" <?= (!$isCustom && $days===$d)?'selected':'' ?>><?= $d ?> Hari</option><?php endforeach; ?>
        <option value="custom" <?= $isCustom ? 'selected' : '' ?>>Kustom...</option>
      </select>
      <div id="customRange" class="<?= $isCustom ? 'show' : '' ?>">
        <input type="date" name="date_from" id="dateFrom" class="input" style="width:145px;"
               value="<?= $isCustom ? ae($dateFrom) : '' ?>" max="<?= date('Y-m-d') ?>">
        <span class="date-sep">—</span>
        <input type="date" name="date_to" id="dateTo" class="input" style="width:145px;"
               value="<?= $isCustom ? ae($dateTo) : '' ?>" max="<?= date('Y-m-d') ?>">
      </div>
      <button class="btn btn-default btn-sm"><?= icon('activity') ?> Terapkan</button>
    </form>
  </div>
<script>
function onDaysChange(val) {
  var cr = document.getElementById('customRange');
  if (val === 'custom') {
    cr.classList.add('show');
    // Default date_from to 30 days ago if empty
    var df = document.getElementById('dateFrom');
    var dt = document.getElementById('dateTo');
    if (!dt.value) { var n = new Date(); dt.value = n.toISOString().slice(0,10); }
    if (!df.value) { var n2 = new Date(); n2.setDate(n2.getDate()-30); df.value = n2.toISOString().slice(0,10); }
  } else {
    cr.classList.remove('show');
    document.getElementById('dateFrom').value = '';
    document.getElementById('dateTo').value   = '';
  }
}
document.getElementById('analyticsForm').addEventListener('submit', function(e) {
  var sel = document.getElementById('daysSelect').value;
  if (sel !== 'custom') {
    document.getElementById('dateFrom').value = '';
    document.getElementById('dateTo').value   = '';
  }
});
</script>

  <!-- Stats -->
  <?php
  $scards = $campId
    ? [['form_submit'=>'Form Submit','form_view'=>'Form View','page_load'=>'Page Load','thanks_page'=>'Thanks Page','wa_click'=>'WA Click','leads'=>'Total Lead','purchased'=>'Beli','doubles'=>'Duplikat']]
    : [[]];
  $cards = $campId ? [
    ['Form Submit',  $stats['form_submit']??0, 'send',         'hsl(217 91% 60%)'],
    ['Thanks Page',  $stats['thanks_page']??0, 'check-circle', 'hsl(142 76% 36%)'],
    ['WA Click',     $stats['wa_click']??0,    'message-square','hsl(199 89% 48%)'],
    ['Total Lead',   $stats['leads']??0,       'file-text',    'hsl(262 83% 58%)'],
    ['Beli',         $stats['purchased']??0,   'shopping-cart','hsl(38 92% 50%)'],
  ] : [
    ['Total Lead',    $stats['total']??0,     'file-text',    'hsl(217 91% 60%)'],
    ['Lead Hari Ini', $stats['today']??0,     'trending-up',  'hsl(262 83% 58%)'],
    ['Berhasil Beli', $stats['purchased']??0, 'shopping-cart','hsl(142 76% 36%)'],
    ['Kampanye Aktif',$stats['campaigns']??0, 'megaphone',    'hsl(38 92% 50%)'],
    ['CS Online',     $stats['operators']??0, 'users',        'hsl(199 89% 48%)'],
  ];
  ?>
  <div class="grid-5" style="margin-bottom:20px;">
    <?php foreach ($cards as [$label,$value,$ico,$color]): ?>
    <div class="card">
      <div class="stat-card">
        <div class="stat-label" style="color:<?= $color ?>;"><?= icon($ico,'',14) ?> <?= ae($label) ?></div>
        <div class="stat-value"><?= number_format((int)$value) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div style="display:grid;grid-template-columns:3fr 2fr;gap:20px;margin-bottom:20px;">
    <div class="card">
      <div class="card-header"><h3 class="card-title">Lead per Hari</h3><p class="card-desc"><?= ae($rangeLabel) ?></p></div>
      <div class="card-content"><canvas id="barChart" height="90"></canvas></div>
    </div>
    <div class="card">
      <div class="card-header"><h3 class="card-title">Tren Lead</h3><p class="card-desc"><?= ae($rangeLabel) ?></p></div>
      <div class="card-content"><canvas id="lineChart" height="90"></canvas></div>
    </div>
  </div>

  <!-- Operator table -->
  <div class="card">
    <div class="card-header"><h3 class="card-title">Performa Operator</h3><p class="card-desc">Distribusi lead per CS</p></div>
    <div class="card-content" style="padding-top:0;">
      <div class="table-wrapper" style="border:none;border-top:1px solid hsl(var(--border));border-radius:0;">
        <table>
          <thead><tr><th>Nama</th><th>Total Lead</th><th>Baru</th><th>Dihubungi</th><th>Berhasil Beli</th><th>Konversi</th></tr></thead>
          <tbody>
            <?php if (empty($opStats)): ?>
            <tr><td colspan="6" style="text-align:center;padding:32px;color:hsl(var(--muted-foreground));">Belum ada data.</td></tr>
            <?php else: ?>
            <?php foreach ($opStats as $op):
              $conv = $op->total > 0 ? round($op->purchased / $op->total * 100, 1) : 0;
            ?>
            <tr>
              <td style="font-weight:500;"><?= ae($op->name) ?></td>
              <td><?= number_format((int)$op->total) ?></td>
              <td><span class="badge badge-blue"><?= (int)$op->new_leads ?></span></td>
              <td><span class="badge badge-warning"><?= (int)$op->contacted ?></span></td>
              <td><span class="badge badge-success"><?= (int)$op->purchased ?></span></td>
              <td>
                <div style="display:flex;align-items:center;gap:8px;">
                  <div style="flex:1;height:6px;background:hsl(var(--muted));border-radius:3px;overflow:hidden;">
                    <div style="width:<?= min(100,$conv) ?>%;height:100%;background:hsl(142 76% 36%);border-radius:3px;"></div>
                  </div>
                  <span style="font-size:12px;font-weight:500;color:hsl(var(--muted-foreground));white-space:nowrap;"><?= $conv ?>%</span>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
const labels = <?= json_encode(array_map(function($d) { return substr($d,5); }, $dates)) ?>;
const data   = <?= json_encode($counts) ?>;
const cfg = { responsive:true, plugins:{legend:{display:false}}, scales:{ y:{beginAtZero:true,ticks:{precision:0,font:{size:11}},grid:{color:'hsl(214.3,31.8%,94%)'},border:{display:false}}, x:{ticks:{font:{size:10}},grid:{display:false},border:{display:false}} } };
new Chart(document.getElementById('barChart'), { type:'bar', data:{labels,datasets:[{data,backgroundColor:'hsl(217,91%,60%,0.8)',borderRadius:4,borderSkipped:false}]}, options:cfg });
new Chart(document.getElementById('lineChart'), { type:'line', data:{labels,datasets:[{data,borderColor:'hsl(262,83%,58%)',backgroundColor:'hsl(262,83%,58%,0.1)',borderWidth:2,pointRadius:2,fill:true,tension:0.4}]}, options:cfg });
</script>

<?php include dirname(__DIR__, 2) . '/inc/foot.php'; ?>

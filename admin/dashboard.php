<?php
require_once __DIR__ . '/inc/bootstrap.php';
$pageTitle = 'Dashboard';

$stats    = Analytics::getOverallStats();
$daily    = Analytics::getDailyLeads(0, 30);
$opStats  = Analytics::getOperatorStats();
$recent   = array_map(function($l) { return Lead::decrypt($l); }, Lead::all(['per_page'=>8,'page'=>1]));

$dates  = array_column($daily, 'date');
$counts = array_column($daily, 'leads');

$statusBadge = [
    'new'       => 'badge-blue',
    'contacted' => 'badge-warning',
    'purchased' => 'badge-success',
    'cancelled' => 'badge-secondary',
    'blocked'   => 'badge-destructive',
];
$statusLabel = ['new'=>'Baru','contacted'=>'Dihubungi','purchased'=>'Beli','cancelled'=>'Batal','blocked'=>'Blokir'];

include __DIR__ . '/inc/head.php';
include __DIR__ . '/inc/sidebar.php';
?>
<div class="page-wrapper">

  <div class="page-header">
    <div>
      <h1 class="page-title">Dashboard</h1>
      <p class="page-desc">Ringkasan performa kampanye dan lead</p>
    </div>
    <div class="flex gap-2">
      <a href="views/campaigns/index.php" class="btn btn-default">
        <?= icon('plus') ?> Kampanye Baru
      </a>
    </div>
  </div>

  <?php include __DIR__ . '/inc/flash.php'; ?>

  <!-- Stat cards -->
  <div class="grid-5 mb-6">
    <?php
    $statCards = [
      ['label'=>'Total Lead',    'value'=>$stats['total']??0,     'sub'=>'Semua waktu',   'icon'=>'file-text',    'color'=>'hsl(217 91% 60%)'],
      ['label'=>'Lead Hari Ini', 'value'=>$stats['today']??0,     'sub'=>'Sejak tengah malam','icon'=>'trending-up','color'=>'hsl(262 83% 58%)'],
      ['label'=>'Berhasil Beli', 'value'=>$stats['purchased']??0, 'sub'=>'Status purchased','icon'=>'shopping-cart','color'=>'hsl(142 76% 36%)'],
      ['label'=>'Kampanye Aktif','value'=>$stats['campaigns']??0, 'sub'=>'Status active', 'icon'=>'megaphone',    'color'=>'hsl(38 92% 50%)'],
      ['label'=>'CS Online',     'value'=>$stats['operators']??0, 'sub'=>'Status on',     'icon'=>'users',        'color'=>'hsl(199 89% 48%)'],
    ];
    foreach ($statCards as $sc): ?>
    <div class="card">
      <div class="stat-card">
        <div class="stat-label" style="color:<?= $sc['color'] ?>">
          <?= icon($sc['icon'],'',14) ?>
          <?= ae($sc['label']) ?>
        </div>
        <div class="stat-value"><?= number_format((int)$sc['value']) ?></div>
        <div class="stat-sub"><?= ae($sc['sub']) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px;">

    <!-- Chart -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Lead 30 Hari Terakhir</h3>
        <p class="card-desc">Volume lead harian</p>
      </div>
      <div class="card-content">
        <canvas id="leadsChart" height="80"></canvas>
      </div>
    </div>

    <!-- Operator stats -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Performa CS</h3>
        <p class="card-desc">Distribusi lead per operator</p>
      </div>
      <div class="card-content">
        <?php if (empty($opStats)): ?>
        <div class="empty-state" style="padding:32px 0;">
          <?= icon('users','',32) ?>
          <p class="empty-state-desc">Belum ada operator.</p>
        </div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:12px;">
        <?php foreach ($opStats as $op): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
            <div style="min-width:0;">
              <div style="font-size:13px;font-weight:500;color:hsl(var(--foreground));white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= ae($op->name) ?></div>
              <div style="font-size:11px;color:hsl(var(--muted-foreground));margin-top:1px;"><?= (int)$op->total ?> lead total</div>
            </div>
            <div style="display:flex;gap:4px;flex-shrink:0;">
              <span class="badge badge-blue"><?= (int)$op->new_leads ?> baru</span>
              <span class="badge badge-success"><?= (int)$op->purchased ?> beli</span>
            </div>
          </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Recent leads -->
  <div class="card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
      <div>
        <h3 class="card-title">Lead Terbaru</h3>
        <p class="card-desc">8 lead terakhir masuk</p>
      </div>
      <a href="views/leads/index.php" class="btn btn-outline btn-sm">
        Lihat Semua <?= icon('chevron-right') ?>
      </a>
    </div>
    <div class="card-content" style="padding-top:0;">
      <div class="table-wrapper" style="border:none;border-top:1px solid hsl(var(--border));border-radius:0;">
        <table>
          <thead>
            <tr>
              <th>Nama</th><th>No HP</th><th>Kampanye</th><th>Operator</th><th>Status</th><th>Tanggal</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($recent)): ?>
            <tr><td colspan="6" style="padding:32px;text-align:center;color:hsl(var(--muted-foreground));">Belum ada lead.</td></tr>
            <?php else: ?>
            <?php foreach ($recent as $l): ?>
            <tr>
              <td>
                <span style="font-weight:500;"><?= ae($l->name ?: '—') ?></span>
                <?php if ($l->is_double): ?><br><span class="badge badge-warning" style="margin-top:2px;">Duplikat</span><?php endif; ?>
              </td>
              <td class="td-muted"><?= ae($l->phone ?: '—') ?></td>
              <td class="td-muted"><?= ae($l->campaign_name ?? '—') ?></td>
              <td class="td-muted"><?= ae($l->operator_name ?? '—') ?></td>
              <td><span class="badge <?= $statusBadge[$l->status] ?? 'badge-secondary' ?>"><?= $statusLabel[$l->status] ?? ae($l->status) ?></span></td>
              <td class="td-muted"><?= date('d/m H:i', strtotime($l->created_at)) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div><!-- /page-wrapper -->

<script>
new Chart(document.getElementById('leadsChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_map(function($d) { return substr($d, 5); }, $dates)) ?>,
    datasets: [{
      data: <?= json_encode($counts) ?>,
      backgroundColor: 'hsl(217, 91%, 60%, 0.85)',
      borderRadius: 4,
      borderSkipped: false,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false }, tooltip: { callbacks: { title: (i) => i[0].label } } },
    scales: {
      y: { beginAtZero: true, ticks: { precision: 0, font: { size: 11 } }, grid: { color: 'hsl(214.3, 31.8%, 94%)' }, border: { display: false } },
      x: { ticks: { font: { size: 10 } }, grid: { display: false }, border: { display: false } }
    }
  }
});
</script>

<?php include __DIR__ . '/inc/foot.php'; ?>

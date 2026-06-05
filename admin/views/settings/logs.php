<?php
require_once dirname(__DIR__, 3) . '/admin/inc/bootstrap.php';
$pageTitle = 'API Logs';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_clear'])) {
    Logger::clearOld(0);
    flashSet('success','Semua log dihapus.');
    header('Location: logs.php'); exit;
}

$page  = max(1,(int)($_GET['page']??1));
$limit = 50;
$logs  = Logger::getLogs($limit,($page-1)*$limit);
$total = Logger::countLogs();
$pages = (int)ceil($total/$limit);

include dirname(__DIR__, 2) . '/inc/head.php';
include dirname(__DIR__, 2) . '/inc/sidebar.php';
?>
<div class="page-wrapper">
  <div class="page-header">
    <div>
      <h1 class="page-title">API Logs</h1>
      <p class="page-desc"><?= number_format($total) ?> log tersimpan</p>
    </div>
    <form method="POST" onsubmit="return confirm('Hapus semua log?')">
      <input type="hidden" name="_clear" value="1">
      <button class="btn btn-outline btn-sm" style="color:hsl(var(--destructive));border-color:hsl(var(--destructive)/0.3);"><?= icon('trash-2') ?> Hapus Semua</button>
    </form>
  </div>
  <?php include dirname(__DIR__, 2) . '/inc/flash.php'; ?>

  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>Sumber</th><th>Event</th><th>Status</th><th>Tanggal</th><th>Detail</th></tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
        <tr><td colspan="5" style="text-align:center;padding:48px;color:hsl(var(--muted-foreground));">Belum ada log.</td></tr>
        <?php else: ?>
        <?php foreach ($logs as $log): ?>
        <tr>
          <td style="font-weight:500;"><?= ae($log->source) ?></td>
          <td class="td-muted"><?= ae($log->event_name) ?></td>
          <td>
            <?php if ($log->success): ?>
            <span class="badge badge-success"><?= icon('check','',11) ?> <?= (int)$log->status_code ?></span>
            <?php else: ?>
            <span class="badge badge-destructive"><?= icon('x','',11) ?> <?= (int)$log->status_code ?></span>
            <?php endif; ?>
          </td>
          <td class="td-muted"><?= date('d/m/Y H:i:s',strtotime($log->created_at)) ?></td>
          <td><button onclick="toggleLog(<?= $log->id ?>)" class="btn btn-ghost btn-sm"><?= icon('code') ?> Payload</button></td>
        </tr>
        <tr id="log-<?= $log->id ?>" class="hidden">
          <td colspan="5" style="padding:0;">
            <div style="background:hsl(222.2 84% 4.9%);padding:16px 20px;">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div>
                  <div style="font-size:10px;font-weight:600;color:hsl(var(--muted-foreground));letter-spacing:.08em;text-transform:uppercase;margin-bottom:8px;">Payload</div>
                  <pre class="code-block green" style="margin:0;"><?= ae(json_encode(json_decode($log->payload??'{}'),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre>
                </div>
                <div>
                  <div style="font-size:10px;font-weight:600;color:hsl(var(--muted-foreground));letter-spacing:.08em;text-transform:uppercase;margin-bottom:8px;">Response</div>
                  <pre class="code-block blue" style="margin:0;"><?= ae($log->response??'') ?></pre>
                </div>
              </div>
              <div style="font-size:11px;color:hsl(var(--muted-foreground));margin-top:10px;">Endpoint: <?= ae($log->endpoint??'') ?></div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
  <div class="pagination">
    <?php for($p=max(1,$page-3);$p<=min($pages,$page+3);$p++): ?>
    <a href="?page=<?= $p ?>" class="page-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>

<script>
function toggleLog(id) {
  document.getElementById('log-'+id).classList.toggle('hidden');
}
</script>

<?php include dirname(__DIR__, 2) . '/inc/foot.php'; ?>

<?php
require_once dirname(__DIR__, 3) . '/admin/inc/bootstrap.php';
$pageTitle = 'Daftar Terblokir';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['_unblock_id'])) {
    Blocker::unblock((int)$_POST['_unblock_id']);
    flashSet('success','Blokir dicabut.');
    header('Location: blocked.php'); exit;
}

$page  = max(1,(int)($_GET['page']??1));
$limit = 30;
$items = Blocker::all(null,$limit,($page-1)*$limit);
$total = Blocker::count();
$pages = (int)ceil($total/$limit);

include dirname(__DIR__, 2) . '/inc/head.php';
include dirname(__DIR__, 2) . '/inc/sidebar.php';
?>
<div class="page-wrapper">
  <div class="page-header">
    <div>
      <h1 class="page-title">Daftar Terblokir</h1>
      <p class="page-desc"><?= number_format($total) ?> entri diblokir</p>
    </div>
  </div>
  <?php include dirname(__DIR__, 2) . '/inc/flash.php'; ?>

  <div class="table-wrapper">
    <table>
      <thead>
        <tr><th>IP Address</th><th>Nomor HP</th><th>Email</th><th>Alasan</th><th>Diblokir oleh</th><th>Tanggal</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        <?php if (empty($items)): ?>
        <tr><td colspan="7" style="text-align:center;padding:48px;color:hsl(var(--muted-foreground));">Tidak ada data terblokir.</td></tr>
        <?php else: ?>
        <?php foreach ($items as $b): ?>
        <tr>
          <td><code style="font-size:11px;"><?= ae($b->ip_address??'—') ?></code></td>
          <td class="td-muted"><?= ae($b->phone??'—') ?></td>
          <td class="td-muted"><?= ae($b->email??'—') ?></td>
          <td class="td-muted"><?= ae($b->reason??'—') ?></td>
          <td class="td-muted"><?= ae($b->operator_name??'Admin') ?></td>
          <td class="td-muted"><?= date('d/m/Y H:i',strtotime($b->blocked_at)) ?></td>
          <td>
            <form method="POST" onsubmit="return confirm('Cabut blokir ini?')">
              <input type="hidden" name="_unblock_id" value="<?= $b->id ?>">
              <button class="btn btn-outline btn-sm" style="color:hsl(142 76% 36%);border-color:hsl(142 76% 36%/0.3);"><?= icon('shield-check') ?> Unblock</button>
            </form>
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
<?php include dirname(__DIR__, 2) . '/inc/foot.php'; ?>

<?php
require_once dirname(__DIR__, 3) . '/admin/inc/bootstrap.php';
$pageTitle = 'Operator CS';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['_delete_id'])) {
        Operator::delete((int)$_POST['_delete_id']);
        flashSet('success', 'Operator dihapus.');
    }
    if (isset($_POST['_gen_token_id'])) {
        $opId  = (int)$_POST['_gen_token_id'];
        Auth::revokeOperatorTokens($opId);
        $token = Auth::generateOperatorToken($opId);
        $url   = Helper::siteUrl(Settings::get('cs_panel_slug','cs-panel') . '/' . $token);
        flashSet('success', 'Link panel CS: ' . $url);
    }
    header('Location: index.php'); exit;
}

$operators = Operator::all();
include dirname(__DIR__, 2) . '/inc/head.php';
include dirname(__DIR__, 2) . '/inc/sidebar.php';
?>
<div class="page-wrapper">
  <div class="page-header">
    <div>
      <h1 class="page-title">Operator CS</h1>
      <p class="page-desc"><?= count($operators) ?> operator terdaftar</p>
    </div>
    <a href="form.php" class="btn btn-default"><?= icon('plus') ?> Tambah Operator</a>
  </div>
  <?php include dirname(__DIR__, 2) . '/inc/flash.php'; ?>

  <?php if (empty($operators)): ?>
  <div class="card"><div class="empty-state">
    <?= icon('users','',40) ?>
    <p class="empty-state-title">Belum ada operator</p>
    <p class="empty-state-desc">Tambahkan operator CS untuk menerima dan menangani lead.</p>
    <a href="form.php" class="btn btn-default" style="margin-top:16px;"><?= icon('plus') ?> Tambah Operator</a>
  </div></div>
  <?php else: ?>
  <div style="display:flex;flex-direction:column;gap:8px;">
    <?php foreach ($operators as $op):
      $token    = DB::val("SELECT token FROM ".DB::t('operator_tokens')." WHERE operator_id=? AND (expires_at IS NULL OR expires_at>NOW()) LIMIT 1", [$op->id]);
      $panelUrl = $token ? Helper::siteUrl(Settings::get('cs_panel_slug','cs-panel').'/'.$token) : null;
      $leads    = Lead::count(['operator_id' => $op->id]);
    ?>
    <div class="card" style="padding:16px 20px;">
      <div style="display:flex;align-items:center;gap:16px;">

        <!-- Avatar -->
        <div style="width:40px;height:40px;background:hsl(217 91% 60%/0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;color:hsl(217 91% 40%);font-weight:700;font-size:15px;flex-shrink:0;">
          <?= strtoupper(substr($op->name, 0, 1)) ?>
        </div>

        <!-- Info -->
        <div style="flex:1;min-width:0;">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
            <span style="font-size:14px;font-weight:600;"><?= ae($op->name) ?></span>
            <span class="badge <?= $op->status==='on'?'badge-success':'badge-secondary' ?>"><?= $op->status==='on'?'Online':'Offline' ?></span>
            <span class="badge badge-outline" style="text-transform:capitalize;"><?= ae($op->type) ?></span>
            <?php if ($op->work_hours_enabled): ?>
            <span class="badge badge-outline"><?= icon('clock','',11) ?> Jam Kerja</span>
            <?php endif; ?>
          </div>
          <div style="font-size:12px;color:hsl(var(--muted-foreground));"><?= ae($op->value) ?></div>
          <?php if ($panelUrl): ?>
          <div style="display:flex;align-items:center;gap:6px;margin-top:6px;">
            <code style="font-size:11px;color:hsl(var(--muted-foreground));max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= ae($panelUrl) ?></code>
            <!-- data-url avoids any inline JS / HTML escaping issues -->
            <button class="btn btn-ghost btn-sm btn-icon knk-copy-btn"
              data-url="<?= ae($panelUrl) ?>"
              style="width:22px;height:22px;padding:3px;flex-shrink:0;" title="Salin URL">
              <?= icon('copy') ?>
            </button>
            <a href="<?= ae($panelUrl) ?>" target="_blank" rel="noopener"
              class="btn btn-ghost btn-sm btn-icon" style="width:22px;height:22px;padding:3px;flex-shrink:0;">
              <?= icon('external-link') ?>
            </a>
          </div>
          <?php endif; ?>
        </div>

        <!-- Stats + actions -->
        <div style="display:flex;align-items:center;gap:16px;flex-shrink:0;">
          <div style="text-align:right;">
            <div style="font-size:16px;font-weight:700;"><?= number_format($leads) ?></div>
            <div style="font-size:11px;color:hsl(var(--muted-foreground));">lead</div>
          </div>
          <div style="width:1px;height:32px;background:hsl(var(--border));"></div>
          <div style="display:flex;gap:4px;">
            <form method="POST" style="display:inline;">
              <input type="hidden" name="_gen_token_id" value="<?= (int)$op->id ?>">
              <button type="submit" class="btn btn-outline btn-sm"><?= icon('key') ?> Panel Link</button>
            </form>
            <a href="form.php?id=<?= (int)$op->id ?>" class="btn btn-outline btn-sm"><?= icon('pencil') ?></a>
            <form method="POST" style="display:inline;" onsubmit="return confirm('Hapus operator ini?')">
              <input type="hidden" name="_delete_id" value="<?= (int)$op->id ?>">
              <button type="submit" class="btn btn-outline btn-sm" style="color:hsl(var(--destructive));border-color:hsl(var(--destructive)/0.3);"><?= icon('trash-2') ?></button>
            </form>
          </div>
        </div>

      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<script>
// Copy URL buttons — using data-url attribute, no inline onclick
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.knk-copy-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var url = this.getAttribute('data-url');
      var self = this;
      var orig = self.innerHTML;
      navigator.clipboard.writeText(url).then(function() {
        self.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
        setTimeout(function() { self.innerHTML = orig; }, 1500);
      });
    });
  });
});
</script>

<?php include dirname(__DIR__, 2) . '/inc/foot.php'; ?>

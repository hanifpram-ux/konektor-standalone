<?php
$currentScript = basename($_SERVER['SCRIPT_FILENAME'], '.php');
$currentDir    = basename(dirname($_SERVER['SCRIPT_FILENAME']));

function navActive(...$keys) {
    global $currentScript, $currentDir;
    foreach ($keys as $k) {
        $parts = explode('/', $k, 2);
        if (count($parts) === 1) {
            if ($currentScript === $parts[0]) return ' active';
        } else {
            if ($currentDir === $parts[0] && $currentScript === $parts[1]) return ' active';
        }
    }
    return '';
}

$nav = [
    ['icon'=>'layout-dashboard','label'=>'Dashboard',   'href'=>'dashboard.php',             'keys'=>['dashboard']],
    ['icon'=>'megaphone',       'label'=>'Kampanye',    'href'=>'views/campaigns/index.php',  'keys'=>['campaigns/index','campaigns/form']],
    ['icon'=>'users',           'label'=>'Operator CS', 'href'=>'views/operators/index.php',  'keys'=>['operators/index','operators/form']],
    ['icon'=>'file-text',       'label'=>'Leads',       'href'=>'views/leads/index.php',      'keys'=>['leads/index']],
    ['icon'=>'shield-off',      'label'=>'Terblokir',   'href'=>'views/leads/blocked.php',    'keys'=>['leads/blocked']],
    ['icon'=>'bar-chart-2',     'label'=>'Analitik',    'href'=>'views/analytics/index.php',  'keys'=>['analytics/index']],
    ['icon'=>'settings',        'label'=>'Pengaturan',  'href'=>'views/settings/index.php',   'keys'=>['settings/index']],
    ['icon'=>'book-open',       'label'=>'Panduan',     'href'=>'views/guide/index.php',         'keys'=>['guide/index']],
    ['icon'=>'test-tube-2',    'label'=>'Test Pixel',  'href'=>'views/settings/pixel-test.php', 'keys'=>['settings/pixel-test']],
];
?>
<div class="layout">
<aside class="sidebar">
  <div class="sidebar-logo">
    <div style="width:32px;height:32px;background:hsl(217.2 91.2% 59.8%);border-radius:8px;display:flex;align-items:center;justify-content:center;color:white;flex-shrink:0;">
      <?= icon('zap','',16) ?>
    </div>
    <div>
      <div class="sidebar-logo-text">Konektor</div>
      <div class="sidebar-logo-sub">Admin Panel</div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="sidebar-section-label">Menu</div>
    <?php foreach ($nav as $item):
      $active = navActive(...$item['keys']);
      $href   = adminPath($item['href']);
    ?>
    <a href="<?= ae($href) ?>" class="nav-item<?= $active ?>">
      <?= icon($item['icon']) ?>
      <span><?= ae($item['label']) ?></span>
    </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <strong><?= ae(Auth::user()['username'] ?? 'Admin') ?></strong>
      <?= ae(Auth::user()['email'] ?? '') ?>
    </div>
    <a href="<?= adminPath('logout.php') ?>" class="nav-item" style="margin:0;padding:6px 8px;">
      <?= icon('log-out') ?>
      <span>Logout</span>
    </a>
  </div>
</aside>
<div class="main-content">

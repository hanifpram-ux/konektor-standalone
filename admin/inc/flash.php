<?php $flash = flashGet(); if ($flash): ?>
<div x-data="{show:true}" x-show="show" x-cloak x-init="setTimeout(()=>show=false,4000)"
     class="toast-container" @click="show=false" style="cursor:pointer">
  <div class="toast <?= $flash['type'] !== 'success' ? 'destructive' : '' ?>">
    <?= $flash['type'] === 'success' ? icon('check-circle') : icon('alert-circle') ?>
    <div>
      <div class="alert-title"><?= $flash['type'] === 'success' ? 'Berhasil' : 'Error' ?></div>
      <div style="font-size:12px;opacity:.85;margin-top:2px;font-weight:400;"><?= ae($flash['msg']) ?></div>
    </div>
  </div>
</div>
<?php endif; ?>

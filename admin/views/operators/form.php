<?php
require_once dirname(__DIR__, 3) . '/admin/inc/bootstrap.php';
$id     = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$op     = $isEdit ? Operator::find($id) : null;
$pageTitle = $isEdit ? 'Edit Operator' : 'Operator Baru';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST;
    $data['work_hours'] = json_decode($data['work_hours_json'] ?? '[]', true) ?: [];
    Operator::save($data, $id);
    flashSet('success', $isEdit ? 'Operator diperbarui.' : 'Operator berhasil ditambahkan.');
    header('Location: index.php'); exit;
}

$initHours = $op && $op->work_hours ? json_decode($op->work_hours, true) : [];
$days      = ['Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu','Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu','Sunday'=>'Minggu'];

include dirname(__DIR__, 2) . '/inc/head.php';
include dirname(__DIR__, 2) . '/inc/sidebar.php';
?>
<div class="page-wrapper"
  x-data="{
    type: '<?= ae($op->type ?? 'whatsapp') ?>',
    status: '<?= ae($op->status ?? 'on') ?>',
    whEnabled: <?= ($op->work_hours_enabled ?? 0) ? 'true' : 'false' ?>,
    workHours: <?= json_encode($initHours) ?>
  }">

  <div class="page-header">
    <div style="display:flex;align-items:center;gap:12px;">
      <a href="index.php" class="btn btn-ghost btn-sm btn-icon"><?= icon('arrow-left') ?></a>
      <div>
        <h1 class="page-title"><?= ae($pageTitle) ?></h1>
        <p class="page-desc">Konfigurasi data, notifikasi Telegram, dan jadwal operator CS</p>
      </div>
    </div>
    <button onclick="document.getElementById('opForm').submit()" class="btn btn-default">
      <?= icon('save') ?> Simpan
    </button>
  </div>
  <?php include dirname(__DIR__, 2) . '/inc/flash.php'; ?>

  <form id="opForm" method="POST" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">
    <input type="hidden" name="work_hours_json" :value="JSON.stringify(workHours)">
    <input type="hidden" name="type"               x-model="type">
    <input type="hidden" name="status"             x-model="status">
    <input type="hidden" name="work_hours_enabled" :value="whEnabled ? 1 : 0">

    <!-- Left column -->
    <div style="display:flex;flex-direction:column;gap:16px;">

      <!-- Kontak utama -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Informasi Operator</h3>
        </div>
        <div class="card-content" style="display:flex;flex-direction:column;gap:14px;">
          <div>
            <label class="label">Nama CS <span style="color:hsl(var(--destructive));">*</span></label>
            <input name="name" value="<?= ae($op->name ?? '') ?>" class="input" placeholder="Nama lengkap operator" required>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
              <label class="label">Tipe Kontak Utama</label>
              <select x-model="type" class="input">
                <option value="whatsapp">WhatsApp</option>
                <option value="telegram">Telegram</option>
                <option value="email">Email</option>
                <option value="line">LINE</option>
              </select>
            </div>
            <div>
              <label class="label">Status</label>
              <select x-model="status" class="input">
                <option value="on">Online</option>
                <option value="off">Offline</option>
              </select>
            </div>
          </div>
          <div>
            <label class="label"
              x-text="type==='whatsapp' ? 'Nomor WhatsApp' : type==='telegram' ? 'Username Telegram' : type==='email' ? 'Alamat Email' : 'ID LINE'">
            </label>
            <input name="value" value="<?= ae($op->value ?? '') ?>" class="input"
              :placeholder="type==='whatsapp' ? '08123456789' : type==='telegram' ? '@username' : type==='email' ? 'cs@brand.com' : '@lineid'"
              required>
            <p class="form-description"
              x-text="type==='whatsapp' ? 'Nomor WA yang akan dibuka saat customer klik tombol chat' : type==='telegram' ? 'Username Telegram operator untuk link t.me/@username' : type==='email' ? 'Alamat email tujuan lead' : 'ID LINE operator'">
            </p>
          </div>
          <div>
            <label class="label">Catatan Internal</label>
            <textarea name="notes" class="input" rows="2" placeholder="Catatan untuk admin..."><?= ae($op->notes ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <!-- Jadwal jam kerja -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Jadwal Jam Kerja</h3>
          <p class="card-desc">Di luar jadwal, operator tidak menerima lead</p>
        </div>
        <div class="card-content">
          <label class="checkbox-item" style="margin-bottom:14px;">
            <input type="checkbox" x-model="whEnabled">
            <span>
              <div class="checkbox-label">Aktifkan jadwal jam kerja</div>
              <div class="checkbox-desc">Lead hanya diteruskan saat operator sedang aktif</div>
            </span>
          </label>
          <div x-show="whEnabled" style="display:flex;flex-direction:column;gap:8px;">
            <template x-for="(slot, i) in workHours" :key="i">
              <div style="display:flex;align-items:center;gap:8px;padding:10px 12px;background:hsl(var(--muted));border-radius:var(--radius);">
                <select x-model="slot.day" class="input" style="flex:1;padding:6px 10px;font-size:12px;">
                  <?php foreach ($days as $en => $id): ?>
                  <option value="<?= ae($en) ?>"><?= ae($id) ?></option>
                  <?php endforeach; ?>
                </select>
                <input type="time" x-model="slot.start" class="input" style="width:100px;padding:6px 10px;font-size:12px;">
                <span style="color:hsl(var(--muted-foreground));font-size:12px;flex-shrink:0;">–</span>
                <input type="time" x-model="slot.end" class="input" style="width:100px;padding:6px 10px;font-size:12px;">
                <button type="button" @click="workHours.splice(i,1)"
                  class="btn btn-ghost btn-sm btn-icon" style="color:hsl(var(--destructive));flex-shrink:0;">
                  <?= icon('trash-2') ?>
                </button>
              </div>
            </template>
            <button type="button"
              @click="workHours.push({day:'Monday',start:'09:00',end:'17:00'})"
              class="btn btn-outline btn-sm" style="align-self:flex-start;">
              <?= icon('plus') ?> Tambah Jadwal
            </button>
            <p x-show="!workHours.length" style="text-align:center;padding:16px;color:hsl(var(--muted-foreground));font-size:13px;">
              Belum ada jadwal. Klik tombol di atas untuk menambahkan.
            </p>
          </div>
        </div>
      </div>

    </div><!-- /left -->

    <!-- Right column -->
    <div style="display:flex;flex-direction:column;gap:16px;">

      <!-- Telegram Notifikasi — selalu tampil, bukan hanya saat tipe=telegram -->
      <div class="card" style="border-color:hsl(199 89% 48%/0.25);background:hsl(199 89% 48%/0.03);">
        <div class="card-header">
          <h3 class="card-title" style="display:flex;align-items:center;gap:8px;">
            <?= icon('send','',16) ?>
            Notifikasi Telegram
          </h3>
          <p class="card-desc">
            Setiap ada lead baru, bot akan langsung mengirim notifikasi ke CS ini via Telegram &mdash;
            apapun tipe kontak utamanya (WA, email, dll.)
          </p>
        </div>
        <div class="card-content" style="display:flex;flex-direction:column;gap:14px;">
          <div>
            <label class="label">Telegram Chat ID</label>
            <input name="telegram_chat_id"
              value="<?= ae($op->telegram_chat_id ?? '') ?>"
              class="input"
              placeholder="Contoh: 123456789 atau -100987654321">
            <p class="form-description">
              Chat ID pribadi (angka positif) atau group (diawali -100).
              Kosongkan jika tidak ingin menerima notifikasi Telegram.
            </p>
          </div>

          <!-- How to get chat ID -->
          <div style="background:hsl(var(--muted));border-radius:var(--radius);padding:14px 16px;font-size:12px;color:hsl(var(--muted-foreground));line-height:1.7;">
            <strong style="color:hsl(var(--foreground));display:block;margin-bottom:6px;">Cara mendapatkan Chat ID:</strong>
            <ol style="padding-left:16px;margin:0;">
              <li>Buka Telegram, cari <strong style="color:hsl(var(--foreground));">@userinfobot</strong></li>
              <li>Klik <strong>Start</strong> — bot akan membalas dengan info akun Anda</li>
              <li>Salin angka di baris <strong>Id:</strong> — itulah Chat ID Anda</li>
              <li>Paste di kolom di atas</li>
            </ol>
            <div style="margin-top:10px;padding-top:10px;border-top:1px solid hsl(var(--border));">
              <strong style="color:hsl(var(--foreground));">Untuk grup Telegram:</strong><br>
              Tambahkan <strong>@userinfobot</strong> ke grup, ketik /start@userinfobot.<br>
              Chat ID grup diawali dengan <code>-100</code>.
            </div>
          </div>

          <?php if (!Settings::get('telegram_bot_token')): ?>
          <div style="display:flex;gap:10px;align-items:flex-start;padding:12px 14px;background:hsl(38 92% 50%/0.08);border:1px solid hsl(38 92% 50%/0.2);border-radius:var(--radius);">
            <?= icon('alert-circle','',15) ?>
            <div style="font-size:12px;color:hsl(38 92% 35%);line-height:1.6;">
              <strong>Bot Token belum diatur.</strong>
              Notifikasi tidak akan terkirim sampai Anda mengisi Bot Token di
              <a href="<?= ae(adminPath('views/settings/index.php')) ?>" style="color:hsl(217 91% 50%);font-weight:600;">Pengaturan → Telegram Bot</a>.
            </div>
          </div>
          <?php else: ?>
          <div style="display:flex;gap:10px;align-items:center;padding:10px 14px;background:hsl(142 76% 36%/0.08);border:1px solid hsl(142 76% 36%/0.2);border-radius:var(--radius);">
            <?= icon('check-circle','',15) ?>
            <span style="font-size:12px;color:hsl(142 76% 28%);">Bot Token sudah terkonfigurasi. Notifikasi siap dikirim.</span>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Info lead routing -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Cara Kerja Distribusi Lead</h3>
        </div>
        <div class="card-content" style="font-size:13px;color:hsl(var(--muted-foreground));line-height:1.7;">
          <div style="display:flex;flex-direction:column;gap:10px;">
            <div style="display:flex;gap:10px;">
              <span style="width:24px;height:24px;border-radius:50%;background:hsl(217 91% 60%/0.12);color:hsl(217 91% 45%);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;">1</span>
              <span>Customer mengisi form → lead masuk ke sistem</span>
            </div>
            <div style="display:flex;gap:10px;">
              <span style="width:24px;height:24px;border-radius:50%;background:hsl(217 91% 60%/0.12);color:hsl(217 91% 45%);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;">2</span>
              <span>Sistem memilih operator aktif secara otomatis (weighted round-robin)</span>
            </div>
            <div style="display:flex;gap:10px;">
              <span style="width:24px;height:24px;border-radius:50%;background:hsl(217 91% 60%/0.12);color:hsl(217 91% 45%);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;">3</span>
              <span>Notifikasi langsung dikirim ke <strong style="color:hsl(var(--foreground));">Chat ID Telegram</strong> operator yang terpilih</span>
            </div>
            <div style="display:flex;gap:10px;">
              <span style="width:24px;height:24px;border-radius:50%;background:hsl(217 91% 60%/0.12);color:hsl(217 91% 45%);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;">4</span>
              <span>Customer diarahkan ke WA / Telegram / Email operator sesuai <strong style="color:hsl(var(--foreground));">Tipe Kontak Utama</strong></span>
            </div>
          </div>
          <div style="margin-top:14px;padding:10px 12px;background:hsl(var(--muted));border-radius:var(--radius);font-size:12px;">
            Pastikan operator sudah ditambahkan ke kampanye di tab <strong style="color:hsl(var(--foreground));">Operator CS</strong> saat buat/edit kampanye.
          </div>
        </div>
      </div>

    </div><!-- /right -->

  </form>
</div>
<?php include dirname(__DIR__, 2) . '/inc/foot.php'; ?>

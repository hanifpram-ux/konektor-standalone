<?php
require_once dirname(__DIR__, 3) . '/admin/inc/bootstrap.php';
$pageTitle = 'Panduan Setup';

$currentUrl = rtrim((knk_is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'), '/');
$exampleUrl = $currentUrl . '/k/nama-kampanye';
$csUrl      = $currentUrl . '/' . Settings::get('cs_panel_slug','cs-panel') . '/TOKEN';
$hasTelegram = Settings::get('telegram_bot_token') !== '';
$hasOperator = DB::count('operators') > 0;
$hasCampaign = DB::count('campaigns') > 0;

$steps = [
    [
        'num'    => 1,
        'title'  => 'Atur Telegram Bot (Opsional)',
        'badge'  => $hasTelegram ? 'done' : 'optional',
        'icon'   => 'send',
        'desc'   => 'Jika ingin mendapatkan notifikasi lead baru secara real-time di Telegram, setup bot terlebih dahulu.',
        'action' => adminPath('views/settings/index.php'),
        'action_label' => 'Pengaturan',
        'items'  => [
            ['Buka Telegram, cari <strong>@BotFather</strong>'],
            ['Ketik <code>/newbot</code>, ikuti instruksi, beri nama bot'],
            ['Salin <strong>Bot Token</strong> yang diberikan'],
            ['Paste token di <strong>Pengaturan → Telegram Bot Token</strong>'],
            ['Untuk mendapatkan Chat ID: cari <strong>@userinfobot</strong> di Telegram, klik Start'],
            ['Chat ID akan diisi di profil setiap operator'],
            ['Klik <strong>Daftarkan Webhook</strong> di halaman Pengaturan agar callback Telegram aktif'],
            ['Setelah webhook terdaftar, operator dapat memperbarui status lead langsung dari tombol Telegram'],
        ],
        'note' => 'Tanpa Telegram, lead tetap masuk dan bisa dilihat di panel admin. Telegram hanya untuk notifikasi instan.',
    ],
    [
        'num'    => 2,
        'title'  => 'Tambahkan Operator CS',
        'badge'  => $hasOperator ? 'done' : 'required',
        'icon'   => 'users',
        'desc'   => 'Operator CS adalah orang yang akan menerima dan menangani lead. Bisa WhatsApp, Telegram, Email, atau LINE.',
        'action' => adminPath('views/operators/form.php'),
        'action_label' => 'Tambah Operator',
        'items'  => [
            ['Buka menu <strong>Operator CS</strong>'],
            ['Klik <strong>Tambah Operator</strong>'],
            ['Isi nama, tipe kontak (WhatsApp/Telegram/dll.), dan nomor/username'],
            ['Jika pakai Telegram notifikasi: isi juga <strong>Telegram Chat ID</strong>'],
            ['Opsional: atur <strong>jadwal jam kerja</strong> — di luar jadwal, operator tidak menerima lead'],
            ['Klik <strong>Panel Link</strong> untuk membuat link akses CS panel operator tersebut'],
        ],
        'note' => 'Anda bisa menambahkan banyak operator. Lead akan didistribusikan secara otomatis (weighted round-robin).',
    ],
    [
        'num'    => 3,
        'title'  => 'Buat Kampanye',
        'badge'  => $hasCampaign ? 'done' : 'required',
        'icon'   => 'megaphone',
        'desc'   => 'Kampanye adalah halaman form atau link WA yang dibagikan ke calon customer.',
        'action' => adminPath('views/campaigns/index.php'),
        'action_label' => 'Buat Kampanye',
        'items'  => [
            ['Buka menu <strong>Kampanye</strong>, klik <strong>Kampanye Baru</strong>'],
            ['Tab <strong>Informasi Dasar</strong>: isi nama produk, toko, tipe (Form atau WA Link)'],
            ['Tab <strong>Form & Tampilan</strong>: pilih template warna, ukuran, aktifkan field yang diperlukan'],
            ['Tab <strong>Halaman Thanks</strong>: atur tema, pesan terima kasih, dan redirect ke CS'],
            ['Tab <strong>Pixel & Tracking</strong>: isi Pixel ID Meta/TikTok/Google/Snack jika ada'],
            ['Tab <strong>Operator CS</strong>: pilih operator yang menerima lead dari kampanye ini, atur bobot distribusi'],
            ['Klik <strong>Simpan</strong>'],
        ],
        'note' => null,
    ],
    [
        'num'    => 4,
        'title'  => 'Bagikan URL atau Embed Kode',
        'badge'  => 'info',
        'icon'   => 'external-link',
        'desc'   => 'Setelah kampanye aktif, bagikan URL atau embed form ke halaman/iklan Anda.',
        'action' => adminPath('views/campaigns/index.php'),
        'action_label' => 'Lihat Kampanye',
        'items'  => [
            ['Di halaman <strong>Kampanye</strong>, klik ikon <strong>Code (</>) </strong> pada kampanye yang dibuat'],
            ['Tab <strong>URL Langsung</strong>: salin URL untuk dibagikan langsung (iklan, bio link, QR code)'],
            ['Tab <strong>Form Embed</strong>: salin kode HTML untuk ditempel di halaman landing/website — pixel sudah termasuk'],
            ['Tab <strong>Tombol / Link</strong>: salin kode tombol WA untuk website atau blog'],
            ['Untuk iklan berbayar: tambahkan parameter <code>?click_id=XXXX</code> di URL untuk tracking SnackVideo/Google'],
        ],
        'note' => 'Kode embed sudah otomatis menyertakan pixel Meta, TikTok, dan Google sesuai konfigurasi kampanye.',
    ],
    [
        'num'    => 5,
        'title'  => 'Atur Pixel Tracking',
        'badge'  => 'optional',
        'icon'   => 'activity',
        'desc'   => 'Hubungkan kampanye dengan platform iklan untuk tracking konversi yang akurat (server-side + browser-side).',
        'action' => null,
        'action_label' => null,
        'items'  => [
            ['<strong>Meta CAPI v21.0</strong>: isi Pixel ID dan Access Token dari Meta Business Suite → Events Manager → Conversions API'],
            ['<strong>TikTok Events API v1.3</strong>: isi Pixel ID dan Access Token dari TikTok Ads Manager → Assets → Events'],
            ['<strong>Google Ads</strong>: isi Conversion ID dan label dari Google Ads → Tools → Conversions'],
            ['<strong>Google GTM/GA4</strong>: isi GTM ID (GTM-XXXXX) atau GA4 Measurement ID (G-XXXXX)'],
            ['<strong>SnackVideo/Kwai</strong>: isi Pixel ID dan Access Token dari Kwai For Business → Pixel'],
            ['Semua event: Page Load, Form Submit, dan Thanks Page terlacak secara server-side otomatis'],
        ],
        'note' => 'Server-side tracking bekerja bahkan jika pengunjung menggunakan ad blocker.',
    ],
    [
        'num'    => 6,
        'title'  => 'Kelola Lead Masuk',
        'badge'  => 'info',
        'icon'   => 'file-text',
        'desc'   => 'Pantau dan kelola semua lead yang masuk dari berbagai kampanye.',
        'action' => adminPath('views/leads/index.php'),
        'action_label' => 'Lihat Leads',
        'items'  => [
            ['Buka menu <strong>Leads</strong> untuk melihat semua lead masuk'],
            ['Filter berdasarkan kampanye, operator, atau status'],
            ['Klik ikon mata untuk melihat detail: alamat, pesan, IP, source URL'],
            ['Update status lead: Baru → Dihubungi → Beli / Batal'],
            ['Tambah catatan pada lead untuk dokumentasi internal'],
            ['Gunakan tombol <strong>Export CSV</strong> untuk ekspor data ke Excel'],
            ['CS agent mengakses lead mereka via <strong>Panel CS</strong> (link unik per operator)'],
        ],
        'note' => null,
    ],
    [
        'num'    => 7,
        'title'  => 'Panel CS untuk Agent',
        'badge'  => 'info',
        'icon'   => 'users',
        'desc'   => 'Setiap operator mendapat link unik untuk mengakses lead mereka tanpa perlu login ke admin.',
        'action' => adminPath('views/operators/index.php'),
        'action_label' => 'Lihat Operator',
        'items'  => [
            ['Buka <strong>Operator CS</strong>'],
            ['Klik <strong>Panel Link</strong> pada operator yang diinginkan'],
            ['Link unik akan muncul — bagikan link ini ke agent CS'],
            ['Agent CS bisa melihat lead mereka, update status, chat WA langsung dari panel'],
            ['Link tidak memerlukan login — jaga kerahasiaannya'],
            ['Untuk keamanan: klik <strong>Panel Link</strong> lagi untuk generate token baru (token lama otomatis tidak aktif)'],
        ],
        'note' => 'URL panel CS: ' . $csUrl,
    ],
];

$badgeStyle = [
    'done'     => 'badge-success',
    'required' => 'badge-blue',
    'optional' => 'badge-secondary',
    'info'     => 'badge-outline',
];
$badgeLabel = [
    'done'     => 'Selesai',
    'required' => 'Wajib',
    'optional' => 'Opsional',
    'info'     => 'Info',
];

include dirname(__DIR__, 2) . '/inc/head.php';
include dirname(__DIR__, 2) . '/inc/sidebar.php';
?>
<div class="page-wrapper">

  <div class="page-header">
    <div>
      <h1 class="page-title">Panduan Setup</h1>
      <p class="page-desc">Ikuti langkah-langkah ini untuk menggunakan Konektor secara optimal</p>
    </div>
    <a href="<?= ae(adminPath('dashboard.php')) ?>" class="btn btn-outline btn-sm">
      <?= icon('arrow-left') ?> Kembali ke Dashboard
    </a>
  </div>

  <!-- Progress summary -->
  <div class="card" style="margin-bottom:24px;">
    <div class="card-content" style="display:flex;align-items:center;gap:24px;flex-wrap:wrap;">
      <div style="flex:1;min-width:0;">
        <div style="font-size:14px;font-weight:600;margin-bottom:4px;">Status Setup</div>
        <div style="font-size:13px;color:hsl(var(--muted-foreground));">
          <?php
          $doneCount = ($hasTelegram ? 1 : 0) + ($hasOperator ? 1 : 0) + ($hasCampaign ? 1 : 0);
          $totalRequired = 2; // operator + campaign
          if ($doneCount >= $totalRequired): ?>
          Konektor sudah siap digunakan. Semua konfigurasi dasar terpasang.
          <?php else: ?>
          <?= 2 - $doneCount + (2 - $totalRequired < 0 ? 0 : 2 - $totalRequired) ?> langkah wajib belum selesai.
          <?php endif; ?>
        </div>
      </div>
      <div style="display:flex;gap:12px;flex-shrink:0;">
        <div style="text-align:center;">
          <div style="font-size:22px;font-weight:700;color:hsl(<?= $hasTelegram ? '142 76% 36%' : '215.4 16.3% 46.9%' ?>);"><?= $hasTelegram ? icon('check-circle') : icon('alert-circle') ?></div>
          <div style="font-size:11px;color:hsl(var(--muted-foreground));margin-top:2px;">Telegram</div>
        </div>
        <div style="text-align:center;">
          <div style="font-size:22px;font-weight:700;color:hsl(<?= $hasOperator ? '142 76% 36%' : '215.4 16.3% 46.9%' ?>);"><?= $hasOperator ? icon('check-circle') : icon('alert-circle') ?></div>
          <div style="font-size:11px;color:hsl(var(--muted-foreground));margin-top:2px;">Operator</div>
        </div>
        <div style="text-align:center;">
          <div style="font-size:22px;font-weight:700;color:hsl(<?= $hasCampaign ? '142 76% 36%' : '215.4 16.3% 46.9%' ?>);"><?= $hasCampaign ? icon('check-circle') : icon('alert-circle') ?></div>
          <div style="font-size:11px;color:hsl(var(--muted-foreground));margin-top:2px;">Kampanye</div>
        </div>
      </div>
    </div>
  </div>

  <!-- URL Info -->
  <div class="card" style="margin-bottom:24px;border-color:hsl(217 91% 60%/0.3);background:hsl(217 91% 60%/0.04);">
    <div class="card-content" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
      <div>
        <div style="font-size:12px;font-weight:600;color:hsl(var(--muted-foreground));text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;">URL Kampanye</div>
        <code style="font-size:13px;color:hsl(217 91% 40%);word-break:break-all;display:block;"><?= ae($exampleUrl) ?></code>
        <div style="font-size:11px;color:hsl(var(--muted-foreground));margin-top:4px;">Domain terdeteksi otomatis dari akses saat ini</div>
      </div>
      <div>
        <div style="font-size:12px;font-weight:600;color:hsl(var(--muted-foreground));text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;">Panel CS</div>
        <code style="font-size:13px;color:hsl(217 91% 40%);word-break:break-all;display:block;"><?= ae($csUrl) ?></code>
        <div style="font-size:11px;color:hsl(var(--muted-foreground));margin-top:4px;">Token unik per operator, didapat dari menu Operator CS</div>
      </div>
    </div>
  </div>

  <!-- Steps -->
  <div style="display:flex;flex-direction:column;gap:12px;">
    <?php foreach ($steps as $step): ?>
    <div class="card">
      <!-- Step header -->
      <div style="display:flex;align-items:center;gap:16px;padding:18px 24px;cursor:pointer;"
           onclick="toggleStep(<?= $step['num'] ?>)">
        <!-- Number circle -->
        <div style="width:36px;height:36px;border-radius:50%;background:hsl(var(--muted));display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:hsl(var(--muted-foreground));flex-shrink:0;
          <?= $step['badge'] === 'done' ? 'background:hsl(142 76% 36%/0.12);color:hsl(142 76% 28%);' : '' ?>">
          <?= $step['badge'] === 'done' ? icon('check','',16) : $step['num'] ?>
        </div>
        <div style="flex:1;min-width:0;">
          <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <span style="font-size:14px;font-weight:600;"><?= ae($step['title']) ?></span>
            <span class="badge <?= $badgeStyle[$step['badge']] ?>"><?= $badgeLabel[$step['badge']] ?></span>
          </div>
          <p style="font-size:13px;color:hsl(var(--muted-foreground));margin-top:3px;"><?= ae($step['desc']) ?></p>
        </div>
        <?php if ($step['action']): ?>
        <a href="<?= ae($step['action']) ?>" class="btn btn-outline btn-sm" onclick="event.stopPropagation()">
          <?= icon($step['icon']) ?> <?= ae($step['action_label']) ?>
        </a>
        <?php endif; ?>
        <div id="chevron-<?= $step['num'] ?>" style="color:hsl(var(--muted-foreground));transition:transform .2s;flex-shrink:0;">
          <?= icon('chevron-down') ?>
        </div>
      </div>

      <!-- Step body (collapsible) -->
      <div id="step-body-<?= $step['num'] ?>" style="display:none;border-top:1px solid hsl(var(--border));padding:16px 24px 20px;">
        <ol style="margin:0;padding:0;list-style:none;display:flex;flex-direction:column;gap:10px;">
          <?php foreach ($step['items'] as $i => $item): ?>
          <li style="display:flex;gap:12px;align-items:flex-start;">
            <span style="width:22px;height:22px;border-radius:50%;background:hsl(var(--muted));display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:hsl(var(--muted-foreground));flex-shrink:0;margin-top:1px;"><?= $i+1 ?></span>
            <span style="font-size:13px;color:hsl(var(--foreground));line-height:1.6;"><?= $item[0] ?></span>
          </li>
          <?php endforeach; ?>
        </ol>
        <?php if ($step['note']): ?>
        <div style="margin-top:16px;padding:12px 14px;background:hsl(38 92% 50%/0.08);border:1px solid hsl(38 92% 50%/0.2);border-radius:var(--radius);font-size:12px;color:hsl(38 92% 30%);display:flex;gap:10px;align-items:flex-start;">
          <?= icon('info','',14) ?>
          <span><?= $step['note'] ?></span>
        </div>
        <?php endif; ?>
      </div>

    </div>
    <?php endforeach; ?>
  </div><!-- /steps -->

  <!-- FAQ Section -->
  <div style="margin-top:24px;">
    <h2 style="font-size:16px;font-weight:700;margin-bottom:14px;color:hsl(var(--foreground));">Pertanyaan Umum</h2>
    <div style="display:flex;flex-direction:column;gap:8px;">
      <?php
      $faqs = [
        ['Apakah Konektor bisa digunakan tanpa Telegram?', 'Ya. Telegram hanya untuk notifikasi real-time. Lead tetap masuk dan bisa dikelola dari panel admin maupun panel CS.'],
        ['Berapa banyak operator yang bisa ditambahkan?', 'Tidak ada batasan. Anda bisa menambahkan banyak operator dan mengatur bobot distribusi lead (1-10) per kampanye.'],
        ['Bagaimana cara kerja deteksi lead duplikat?', 'Konektor menggunakan fingerprint SHA-256 dari kombinasi nomor HP + email. Jika ada lead dengan data yang sama masuk lagi, ditandai sebagai duplikat tapi tetap disimpan.'],
        ['Apakah data lead aman?', 'Ya. Data PII (nama, HP, email, alamat) dienkripsi menggunakan AES-256-CBC sebelum disimpan ke database. Aktifkan di Pengaturan → Enkripsi Data Lead.'],
        ['Bagaimana cara mengubah URL kampanye?', 'URL kampanye mengikuti domain yang sedang diakses secara otomatis (format: domain.com/k/slug-kampanye). Slug bisa diubah di form edit kampanye.'],
        ['Pixel tracking bekerja jika pengunjung pakai ad blocker?', 'Ya! Konektor menggunakan server-side tracking (CAPI) untuk Meta dan TikTok. Event dikirim langsung dari server ke platform iklan, tanpa melalui browser.'],
        ['Bagaimana cara backup data lead?', 'Gunakan tombol Export CSV di halaman Leads. Pilih filter yang diinginkan (per kampanye, status) lalu klik export.'],
      ];
      foreach ($faqs as [$q, $a]): ?>
      <details class="card card-details" style="padding:0;">
        <summary style="padding:14px 20px;font-size:13px;"><?= ae($q) ?></summary>
        <div class="details-body" style="font-size:13px;color:hsl(var(--muted-foreground));line-height:1.6;"><?= ae($a) ?></div>
      </details>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<script>
var openSteps = {};
function toggleStep(n) {
  var body    = document.getElementById('step-body-' + n);
  var chevron = document.getElementById('chevron-' + n);
  var isOpen  = openSteps[n];
  body.style.display    = isOpen ? 'none' : 'block';
  chevron.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
  openSteps[n] = !isOpen;
}
// Open first incomplete step automatically
document.addEventListener('DOMContentLoaded', function() {
  <?php if (!$hasOperator): ?>toggleStep(2);<?php elseif (!$hasCampaign): ?>toggleStep(3);<?php else: ?>toggleStep(4);<?php endif; ?>
});
</script>

<?php include dirname(__DIR__, 2) . '/inc/foot.php'; ?>

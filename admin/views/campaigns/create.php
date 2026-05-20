<?php
require_once dirname(__DIR__, 3) . '/admin/inc/bootstrap.php';

$id       = (int)(isset($_GET['id']) ? $_GET['id'] : 0);
$isEdit   = $id > 0;
$campaign = $isEdit ? Campaign::find($id) : null;
if ($isEdit && !$campaign) { header('Location: index.php'); exit; }
$pageTitle    = $isEdit ? 'Edit Kampanye' : 'Kampanye Baru';
$defaultType  = (isset($_GET['type']) && $_GET['type'] === 'wa_link') ? 'wa_link' : 'form';

// ── Handle POST save ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = $_POST;
    $data['form_config']        = json_decode(isset($data['form_config_json'])        ? $data['form_config_json']        : '{}', true) ?: [];
    $data['thanks_page_config'] = json_decode(isset($data['thanks_page_config_json']) ? $data['thanks_page_config_json'] : '{}', true) ?: [];
    $data['pixel_config']       = json_decode(isset($data['pixel_config_json'])       ? $data['pixel_config_json']       : '{}', true) ?: [];
    $data['operators']          = json_decode(isset($data['operators_json'])           ? $data['operators_json']           : '{}', true) ?: [];
    $savedId = Campaign::save($data, $id);
    flashSet('success', $isEdit ? 'Kampanye diperbarui.' : 'Kampanye berhasil dibuat.');
    header('Location: index.php'); exit;
}

// ── Load configs ───────────────────────────────────────────────────────────
$formCfg   = $campaign ? Campaign::getFormConfig($campaign)   : Campaign::defaultFormConfig();
$thanksCfg = $campaign ? Campaign::getThanksConfig($campaign) : Campaign::defaultThanksConfig();

$pixelBase = [
    'meta'   => ['enabled'=>'0','pixel_id'=>'','token'=>'','page_load_event'=>'','form_submit_event'=>'','thanks_page_event'=>'','value'=>'','currency'=>'IDR','test_event_code'=>''],
    'tiktok' => ['enabled'=>'0','pixel_id'=>'','access_token'=>'','page_load_event'=>'','form_submit_event'=>'','thanks_page_event'=>'','value'=>'','currency'=>'IDR','test_event_code'=>''],
    'google' => ['enabled'=>'0','gtm_id'=>'','conversion_id'=>'','ga4_id'=>'','page_load_label'=>'','form_submit_label'=>'','thanks_page_label'=>'','value'=>'','currency'=>'IDR'],
    'snack'  => ['enabled'=>'0','pixel_id'=>'','access_token'=>'','page_load_event'=>'','form_submit_event'=>'','thanks_page_event'=>'','value'=>'','currency'=>'IDR','test_mode'=>'','test_click_id'=>'','html'=>''],
];
$pixelCfg = $campaign && $campaign->pixel_config ? json_decode($campaign->pixel_config, true) : [];
foreach ($pixelBase as $k => $def) {
    $pixelCfg[$k] = array_merge($def, isset($pixelCfg[$k]) ? $pixelCfg[$k] : []);
}

$allOps   = Operator::all();
$existOps = $campaign ? Campaign::getOperators($campaign->id) : [];
$initOps  = [];
foreach ($existOps as $o) {
    $initOps[] = [
        'id'     => (int)(property_exists($o, 'operator_id') ? $o->operator_id : $o->id),
        'weight' => (int)(isset($o->weight) ? $o->weight : 1),
    ];
}

$alpineInit = [
    'tab'         => 'basic',
    'showPreview' => false,
    'previewTab'  => 'form',
    'formCfg'     => $formCfg,
    'thanksCfg'   => $thanksCfg,
    'pixelCfg'    => $pixelCfg,
    'operators'   => $initOps,
    'distMode'    => 'proportional',
    'camp' => [
        'name'                => isset($campaign->name)                ? $campaign->name                : '',
        'slug'                => isset($campaign->slug)                ? $campaign->slug                : '',
        'type'                => isset($campaign->type)                ? $campaign->type                : $defaultType,
        'store_name'          => isset($campaign->store_name)          ? $campaign->store_name          : '',
        'product_name'        => isset($campaign->product_name)        ? $campaign->product_name        : '',
        'status'              => isset($campaign->status)              ? $campaign->status              : 'active',
        'block_enabled'       => (bool)(isset($campaign->block_enabled)       ? $campaign->block_enabled       : 1),
        'block_message'       => isset($campaign->block_message)       ? $campaign->block_message       : '',
        'double_lead_enabled' => (bool)(isset($campaign->double_lead_enabled) ? $campaign->double_lead_enabled : 1),
        'double_lead_message' => isset($campaign->double_lead_message) ? $campaign->double_lead_message : '',
        'followup_message'    => isset($campaign->followup_message)    ? $campaign->followup_message    : '',
    ],
];

$isEditJs  = $isEdit ? 'true' : 'false';
$baseUrl   = ae(Helper::siteUrl(Settings::get('base_slug','k')));

include dirname(__DIR__, 2) . '/inc/head.php';
include dirname(__DIR__, 2) . '/inc/sidebar.php';
?>

<div class="page-wrapper" id="campApp">

  <!-- Page header -->
  <div class="page-header">
    <div style="display:flex;align-items:center;gap:12px;">
      <a href="index.php" class="btn btn-ghost btn-sm btn-icon"><?= icon('arrow-left') ?></a>
      <div>
        <h1 class="page-title"><?= ae($pageTitle) ?></h1>
        <p class="page-desc" id="page_desc_main">Atur kampanye, form, tracking pixel, dan routing CS</p>
      </div>
    </div>
    <div style="display:flex;gap:8px;">
      <button type="button" id="btn_preview_main" onclick="KNK.openPreview('form')" class="btn btn-outline btn-sm">
        <?= icon('eye') ?> <span id="btn_preview_main_label">Preview Form</span>
      </button>
      <button type="button" onclick="KNK.openPreview('thanks')" class="btn btn-outline btn-sm">
        <?= icon('eye') ?> Preview Thanks
      </button>
      <button type="button" onclick="KNK.save()" class="btn btn-default">
        <?= icon('save') ?> Simpan
      </button>
    </div>
  </div>

  <?php include dirname(__DIR__, 2) . '/inc/flash.php'; ?>

  <!-- Preview Modal -->
  <div id="previewModal" style="display:none;" class="dialog-overlay" onclick="if(event.target===this)KNK.closePreview()">
    <div class="dialog-content" style="max-width:540px;">
      <div class="dialog-header" style="display:flex;align-items:center;justify-content:space-between;">
        <div>
          <h3 class="dialog-title">Preview</h3>
          <p class="dialog-desc">Tampilan yang akan dilihat pengunjung</p>
        </div>
        <button type="button" onclick="KNK.closePreview()" class="btn btn-ghost btn-icon"><?= icon('x') ?></button>
      </div>
      <div style="padding:0 24px 8px;display:flex;gap:4px;">
        <button id="prevTabForm"   onclick="KNK.switchPreview('form')"   class="btn btn-default btn-sm"><span id="prev_tab_form_label">Form</span></button>
        <button id="prevTabThanks" onclick="KNK.switchPreview('thanks')" class="btn btn-outline btn-sm">Halaman Thanks</button>
      </div>
      <div class="dialog-body" style="padding-top:8px;">
        <div id="prevForm"></div>
        <div id="prevThanks" style="display:none;"></div>
      </div>
    </div>
  </div>

  <!-- Real submit form (hidden) — values populated by JS before submit -->
  <form id="mainForm" method="POST" action="">
    <input type="hidden" name="name"                  id="f_name">
    <input type="hidden" name="slug"                  id="f_slug">
    <input type="hidden" name="type"                  id="f_type">
    <input type="hidden" name="store_name"            id="f_store_name">
    <input type="hidden" name="product_name"          id="f_product_name">
    <input type="hidden" name="status"                id="f_status">
    <input type="hidden" name="block_enabled"         id="f_block_enabled">
    <input type="hidden" name="block_message"         id="f_block_message">
    <input type="hidden" name="double_lead_enabled"   id="f_double_lead_enabled">
    <input type="hidden" name="double_lead_message"   id="f_double_lead_message">
    <input type="hidden" name="followup_message"      id="f_followup_message">
    <input type="hidden" name="form_config_json"      id="f_form_config_json">
    <input type="hidden" name="thanks_page_config_json" id="f_thanks_page_config_json">
    <input type="hidden" name="pixel_config_json"     id="f_pixel_config_json">
    <input type="hidden" name="operators_json"        id="f_operators_json">
  </form>

  <!-- Tabs nav -->
  <div class="tabs-list" id="tabNav" style="display:flex;gap:4px;padding:6px;background:hsl(var(--muted));border-radius:calc(var(--radius) + 2px);margin-bottom:4px;">
    <button class="tab-trigger active" onclick="KNK.tab('basic',this)" style="display:flex;align-items:center;gap:6px;flex:1;justify-content:center;">
      <svg style="width:14px;height:14px;flex-shrink:0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
      <span>Dasar</span>
    </button>
    <button class="tab-trigger" id="tabnav_form" onclick="KNK.tab('form',this)" style="display:flex;align-items:center;gap:6px;flex:1;justify-content:center;">
      <svg style="width:14px;height:14px;flex-shrink:0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
      <span id="tabnav_form_label">Form</span>
    </button>
    <button class="tab-trigger" onclick="KNK.tab('thanks',this)" style="display:flex;align-items:center;gap:6px;flex:1;justify-content:center;">
      <svg style="width:14px;height:14px;flex-shrink:0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      <span>Thanks</span>
    </button>
    <button class="tab-trigger" onclick="KNK.tab('pixel',this)" style="display:flex;align-items:center;gap:6px;flex:1;justify-content:center;">
      <svg style="width:14px;height:14px;flex-shrink:0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      <span>Pixel</span>
    </button>
    <button class="tab-trigger" onclick="KNK.tab('ops',this)" style="display:flex;align-items:center;gap:6px;flex:1;justify-content:center;">
      <svg style="width:14px;height:14px;flex-shrink:0;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <span>Operator</span>
    </button>
  </div>

  <!-- ══ TAB: BASIC ════════════════════════════════════════════════ -->
  <div id="tab-basic" class="tab-pane" style="display:flex;flex-direction:column;gap:16px;">
    <div class="card">
      <div class="card-header"><h3 class="card-title">Informasi Kampanye</h3></div>
      <div class="card-content" style="display:flex;flex-direction:column;gap:16px;">
        <div class="form-item" style="margin:0;">
          <label class="label">Nama Kampanye <span style="color:hsl(var(--destructive))">*</span></label>
          <input id="inp_name" class="input" placeholder="Contoh: Kampanye Ramadan"
            oninput="KNK.onNameInput(this.value)">
        </div>
        <div class="form-item" style="margin:0;">
          <label class="label">Slug URL</label>
          <input id="inp_slug" class="input" placeholder="kampanye-ramadan"
            oninput="KNK.d.camp.slug=this.value.toLowerCase().replace(/[^a-z0-9\-]/g,'');this.value=KNK.d.camp.slug">
          <p class="form-description">URL: <?= $baseUrl ?>/<span id="slugPreview">slug-kampanye</span></p>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <!-- Tipe tidak ditampilkan di sini — sudah dipilih dari picker sebelumnya -->
          <!-- Badge tipe kampanye saja -->
          <div>
            <label class="label">Tipe Kampanye</label>
            <div id="camp_type_badge" style="display:flex;align-items:center;gap:8px;padding:8px 12px;background:hsl(var(--muted));border-radius:var(--radius);font-size:13px;font-weight:500;">
              <span id="camp_type_icon"></span>
              <span id="camp_type_label">Form Lead</span>
            </div>
            <input type="hidden" id="inp_type">
          </div>
          <div>
            <label class="label">Status</label>
            <select id="inp_status" class="input" onchange="KNK.d.camp.status=this.value">
              <option value="active">Aktif</option>
              <option value="inactive">Nonaktif</option>
            </select>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div>
            <label class="label">Nama Toko / Brand</label>
            <input id="inp_store" class="input" placeholder="Nama Brand" oninput="KNK.d.camp.store_name=this.value">
          </div>
          <div>
            <label class="label">Nama Produk / Layanan</label>
            <input id="inp_product" class="input" placeholder="Nama Produk" oninput="KNK.d.camp.product_name=this.value">
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3 class="card-title">Proteksi Lead</h3><p class="card-desc">Pemblokiran dan deteksi duplikat</p></div>
      <div class="card-content" style="display:flex;flex-direction:column;gap:16px;">
        <div>
          <label class="checkbox-item">
            <input type="checkbox" id="inp_block" onchange="KNK.d.camp.block_enabled=this.checked;KNK.toggle('block_msg_row',this.checked)">
            <span>
              <div class="checkbox-label">Aktifkan pemblokiran pengunjung</div>
              <div class="checkbox-desc">Blokir berdasarkan IP, fingerprint, atau cookie</div>
            </span>
          </label>
          <div id="block_msg_row" style="display:none;margin-top:10px;">
            <textarea id="inp_block_msg" class="input" rows="2" placeholder="Pesan untuk pengunjung yang diblokir..."
              oninput="KNK.d.camp.block_message=this.value"></textarea>
          </div>
        </div>
        <div class="separator" style="margin:0;"></div>
        <div>
          <label class="checkbox-item">
            <input type="checkbox" id="inp_double" onchange="KNK.d.camp.double_lead_enabled=this.checked;KNK.toggle('double_msg_row',this.checked)">
            <span>
              <div class="checkbox-label">Deteksi lead duplikat</div>
              <div class="checkbox-desc">Tandai lead yang sudah pernah submit sebelumnya</div>
            </span>
          </label>
          <div id="double_msg_row" style="display:none;margin-top:10px;">
            <textarea id="inp_double_msg" class="input" rows="2" placeholder="Pesan untuk lead duplikat..."
              oninput="KNK.d.camp.double_lead_message=this.value"></textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="card" id="card-followup">
      <div class="card-header"><h3 class="card-title">Pesan Follow-up</h3><p class="card-desc">Pesan WA dari CS ke customer setelah lead masuk</p></div>
      <div class="card-content">
        <textarea id="inp_followup" class="input" rows="3" placeholder="Halo [cname], saya [oname]..."
          oninput="KNK.d.camp.followup_message=this.value"></textarea>
        <p class="form-description">Variabel: [name] [phone] [email] [oname] [product] [quantity]</p>
      </div>
    </div>
  </div>

  <!-- ══ TAB: FORM & TAMPILAN ══════════════════════════════════════ -->
  <div id="tab-form" class="tab-pane" style="display:none;flex-direction:column;gap:16px;">

    <!-- ══ LINK APPEARANCE PANEL (wa_link only) ══════════════════════ -->
    <div id="link-appearance-panel" style="display:none;flex-direction:column;gap:16px;">

      <!-- Link Template Scheme -->
      <div class="card">
        <div class="card-header"><h3 class="card-title">Template Warna Tombol</h3><p class="card-desc">Warna halaman dan tombol link</p></div>
        <div class="card-content">
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;" id="linkTplGrid">
            <?php
            $linkTpls = [
              'modern'       => ['Modern',       'Biru profesional',    '#ffffff','#2563eb'],
              'classic'      => ['Classic',      'Merah klasik',        '#fff8f0','#dc2626'],
              'classic_flat' => ['Classic Flat', 'Hijau daun + oranye', '#b7d09a','#FF5000'],
              'minimal'      => ['Minimal',      'Hitam putih',         '#fafafa','#111111'],
              'card'         => ['Card',         'Latar biru muda',     '#eff6ff','#2563eb'],
              'gradient'     => ['Gradient',     'Biru tua gelap',      '#1e3a5f','#38bdf8'],
              'rose'         => ['Rose',         'Pink feminine',       '#fff1f2','#e11d48'],
              'forest'       => ['Forest',       'Hijau segar',         '#f0fdf4','#16a34a'],
              'sunset'       => ['Sunset',       'Oranye hangat',       '#ff6b6b','#ee5a24'],
              'ocean'        => ['Ocean',        'Biru laut',           '#0077b6','#48cae4'],
            ];
            foreach ($linkTpls as $tid => [$tl,$td,$tbg,$tacc]): ?>
            <button type="button" class="tpl-card" id="ltpl_<?= $tid ?>" onclick="KNK.setLinkTpl('<?= $tid ?>')">
              <div class="tpl-swatch" style="background:<?= $tbg ?>;border-top:4px solid <?= $tacc ?>;"></div>
              <div class="tpl-card-name"><?= ae($tl) ?></div>
              <div class="tpl-card-desc"><?= ae($td) ?></div>
            </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Icon & Label -->
      <div class="card">
        <div class="card-header"><h3 class="card-title">Label &amp; Icon Tombol</h3></div>
        <div class="card-content" style="display:flex;flex-direction:column;gap:16px;">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div>
              <label class="label">Label Tombol</label>
              <input id="link_btn_label" class="input" placeholder="Hubungi Sekarang"
                oninput="KNK.d.formCfg.submit_label=this.value;KNK.refreshPreview()">
            </div>
            <div>
              <label class="label">Icon Tombol</label>
              <select id="link_icon_pick" class="input" onchange="KNK.setLinkIcon(this.value)">
                <option value="wa">WhatsApp</option>
                <option value="tg">Telegram</option>
                <option value="line">LINE</option>
                <option value="email">Email</option>
                <option value="none">Tanpa Icon</option>
              </select>
            </div>
          </div>
          <div>
            <label class="label">Tagline / Keterangan (opsional)</label>
            <input id="link_tagline" class="input" placeholder="Stok terbatas! Hubungi segera."
              oninput="KNK.d.formCfg.custom_style=KNK.d.formCfg.custom_style||{};KNK.d.formCfg.custom_style.tagline=this.value;KNK.refreshPreview()">
          </div>
        </div>
      </div>

      <!-- Color overrides -->
      <details class="card card-details">
        <summary>Kustomisasi Warna Lanjutan</summary>
        <div class="details-body">
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
            <?php
            $linkStyleFields = [
              ['Warna Background','color_bg','color'],
              ['Warna Aksen / Tombol','color_accent','color'],
              ['Warna Teks','color_text','color'],
              ['Warna Teks Tombol','color_btn_text','color'],
              ['Lebar Maks Tombol','max_width','text'],
              ['Border Radius','border_radius','text'],
            ];
            foreach ($linkStyleFields as [$lbl,$key,$type]): ?>
            <div>
              <label class="label" style="font-size:11px;"><?= ae($lbl) ?></label>
              <?php if ($type === 'color'): ?>
              <div style="display:flex;gap:6px;">
                <input type="color" id="lcs_c_<?= $key ?>" style="width:36px;height:32px;border:1px solid hsl(var(--border));border-radius:var(--radius);padding:2px;cursor:pointer;"
                  oninput="KNK.setStyle('<?= $key ?>',this.value);document.getElementById('lcs_<?= $key ?>').value=this.value;KNK.refreshPreview()">
                <input type="text" id="lcs_<?= $key ?>" class="input" style="font-size:12px;padding:6px 10px;" placeholder="auto"
                  oninput="KNK.setStyle('<?= $key ?>',this.value);document.getElementById('lcs_c_<?= $key ?>').value=this.value;KNK.refreshPreview()">
              </div>
              <?php else: ?>
              <input type="text" id="lcs_<?= $key ?>" class="input" style="font-size:12px;padding:6px 10px;" placeholder="auto"
                oninput="KNK.setStyle('<?= $key ?>',this.value);KNK.refreshPreview()">
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </details>

    </div><!-- /link-appearance-panel -->

    <!-- ══ FORM APPEARANCE PANEL (form type only) ══════════════════════ -->
    <div id="form-appearance-panel" style="display:flex;flex-direction:column;gap:16px;">

    <!-- Template picker -->
    <div class="card">
      <div class="card-header"><h3 class="card-title">Template Warna</h3></div>
      <div class="card-content">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;" id="tplGrid">
          <?php
          $formTpls = [
            'modern'       =>['Modern',       'Biru profesional',    '#ffffff','#2563eb'],
            'classic'      =>['Classic',      'Merah klasik',        '#ffffff','#dc2626'],
            'classic_flat' =>['Classic Flat', 'Hijau daun + oranye', '#b7d09a','#FF5000'],
            'minimal'      =>['Minimal',      'Hitam putih',         '#fafafa','#111111'],
            'card'         =>['Card',         'Latar biru muda',     '#eff6ff','#2563eb'],
            'gradient'     =>['Gradient',     'Biru tua gelap',      '#1e3a5f','#38bdf8'],
            'rose'         =>['Rose',         'Pink feminine',       '#fff1f2','#e11d48'],
            'forest'       =>['Forest',       'Hijau segar',         '#f0fdf4','#16a34a'],
            'sunset'       =>['Sunset',       'Oranye hangat',       '#fff3e0','#ee5a24'],
            'ocean'        =>['Ocean',        'Biru laut',           '#e0f7fa','#0077b6'],
          ];
          foreach ($formTpls as $tid => [$tl,$td,$tbg,$tacc]): ?>
          <button type="button" class="tpl-card" id="tpl_<?= $tid ?>" onclick="KNK.setFormTpl('<?= $tid ?>')">
            <div class="tpl-swatch" style="background:<?= $tbg ?>;border-top:4px solid <?= $tacc ?>;"></div>
            <div class="tpl-card-name"><?= ae($tl) ?></div>
            <div class="tpl-card-desc"><?= ae($td) ?></div>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Size -->
    <div class="card">
      <div class="card-header"><h3 class="card-title">Ukuran Form</h3></div>
      <div class="card-content">
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;" id="sizeGrid">
          <?php foreach(['compact'=>['Compact','380px'],'default'=>['Default','480px'],'large'=>['Large','600px'],'fullwidth'=>['Full Width','100%']] as $sid=>[$sl,$sw]): ?>
          <button type="button" class="tpl-card" id="sz_<?= $sid ?>" onclick="KNK.setSize('<?= $sid ?>')">
            <div class="tpl-card-name"><?= ae($sl) ?></div>
            <div class="tpl-card-desc"><?= ae($sw) ?></div>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Labels -->
    <div class="card">
      <div class="card-content" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <div>
          <label class="label">Teks Tombol Submit</label>
          <input id="inp_submit_label" class="input" placeholder="Kirim Sekarang"
            oninput="KNK.d.formCfg.submit_label=this.value">
        </div>
        <div>
          <label class="label">Tagline / Keterangan</label>
          <input id="inp_tagline" class="input" placeholder="Stok terbatas!"
            oninput="KNK.d.formCfg.custom_style=KNK.d.formCfg.custom_style||{};KNK.d.formCfg.custom_style.tagline=this.value">
        </div>
      </div>
    </div>

    <!-- Standard fields -->
    <div class="card">
      <div class="card-header"><h3 class="card-title">Field Standar</h3><p class="card-desc">Aktifkan dan sesuaikan field bawaan</p></div>
      <div class="card-content" id="stdFieldsContainer" style="display:flex;flex-direction:column;gap:8px;"></div>
    </div>

    <!-- Extra fields -->
    <div class="card">
      <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <div><h3 class="card-title">Field Tambahan</h3><p class="card-desc">Field kustom sesuai kebutuhan</p></div>
        <button type="button" onclick="KNK.addExtraField()" class="btn btn-outline btn-sm"><?= icon('plus') ?> Tambah Field</button>
      </div>
      <div class="card-content" id="extraFieldsContainer" style="display:flex;flex-direction:column;gap:8px;">
        <p id="extraEmpty" style="text-align:center;color:hsl(var(--muted-foreground));font-size:13px;padding:16px;">Belum ada field tambahan.</p>
      </div>
    </div>

    <!-- Advanced style -->
    <details class="card card-details">
      <summary>Kustomisasi Warna &amp; Ukuran Lanjutan</summary>
      <div class="details-body">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;" id="styleOverrides">
          <?php
          $styleFields = [
            ['Warna Background','color_bg','color'],['Warna Aksen','color_accent','color'],
            ['Warna Teks','color_text','color'],['Warna Border Input','color_border','color'],
            ['Warna Teks Tombol','color_btn_text','color'],['Background Input','input_bg','color'],
            ['Lebar Maksimal','max_width','text'],['Padding','padding','text'],
            ['Ukuran Font','font_size','text'],['Font Tombol','btn_font_size','text'],
            ['Border Radius','border_radius','text'],['Font Family','font_family','text'],
          ];
          foreach ($styleFields as [$lbl,$key,$type]): ?>
          <div>
            <label class="label" style="font-size:11px;"><?= ae($lbl) ?></label>
            <?php if ($type === 'color'): ?>
            <div style="display:flex;gap:6px;">
              <input type="color" id="cs_c_<?= $key ?>" style="width:36px;height:32px;border:1px solid hsl(var(--border));border-radius:var(--radius);padding:2px;cursor:pointer;"
                oninput="KNK.setStyle('<?= $key ?>',this.value);document.getElementById('cs_<?= $key ?>').value=this.value">
              <input type="text" id="cs_<?= $key ?>" class="input" style="font-size:12px;padding:6px 10px;" placeholder="auto"
                oninput="KNK.setStyle('<?= $key ?>',this.value);document.getElementById('cs_c_<?= $key ?>').value=this.value">
            </div>
            <?php else: ?>
            <input type="text" id="cs_<?= $key ?>" class="input" style="font-size:12px;padding:6px 10px;" placeholder="auto"
              oninput="KNK.setStyle('<?= $key ?>',this.value)">
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </details>

    </div><!-- /form-appearance-panel -->
  </div><!-- /tab-form -->

  <!-- ══ TAB: THANKS ═══════════════════════════════════════════════ -->
  <div id="tab-thanks" class="tab-pane" style="display:none;flex-direction:column;gap:16px;">
    <div class="card">
      <div class="card-header"><h3 class="card-title">Template Halaman Thanks</h3></div>
      <div class="card-content">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;" id="thanksTplGrid">
          <?php foreach(['modern'=>['Modern','Card shadow ringan'],'minimal'=>['Minimal','Border atas berwarna'],'card'=>['Card','Shadow tebal'],'celebration'=>['Celebration','Animasi bounce'],'fullscreen'=>['Fullscreen','Layar penuh']] as $tid=>[$tl,$td]): ?>
          <button type="button" class="tpl-card" id="thnk_<?= $tid ?>" onclick="KNK.setThanksTpl('<?= $tid ?>')">
            <div class="tpl-card-name"><?= ae($tl) ?></div>
            <div class="tpl-card-desc"><?= ae($td) ?></div>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <div>
          <h3 class="card-title">Kustomisasi Warna</h3>
          <p class="card-desc">Secara default mengikuti warna template form/link</p>
        </div>
        <button type="button" class="btn btn-outline btn-sm" onclick="KNK.resetThanksTheme()" title="Reset ke warna template form/link">
          <?= icon('rotate-ccw','',13) ?> Reset ke Skema
        </button>
      </div>
      <div class="card-content">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;">
          <div style="grid-column:span 3;">
            <label class="label" style="font-size:11px;">Judul Halaman</label>
            <input id="th_heading" class="input" style="font-size:12px;" placeholder="Pesanan Berhasil!"
              oninput="KNK.setTheme('heading',this.value)">
          </div>
          <?php foreach([['Warna Background','bg_color'],['Warna Card','card_color'],['Warna Aksen','accent_color'],['Warna Teks','text_color']] as [$lbl,$k]): ?>
          <div>
            <label class="label" style="font-size:11px;"><?= ae($lbl) ?></label>
            <div style="display:flex;gap:6px;">
              <input type="color" id="th_c_<?= $k ?>" style="width:36px;height:32px;border:1px solid hsl(var(--border));border-radius:var(--radius);padding:2px;cursor:pointer;"
                oninput="KNK.setTheme('<?= $k ?>',this.value);document.getElementById('th_<?= $k ?>').value=this.value">
              <input type="text" id="th_<?= $k ?>" class="input" style="font-size:12px;padding:6px 10px;" placeholder="auto"
                oninput="KNK.setTheme('<?= $k ?>',this.value);document.getElementById('th_c_<?= $k ?>').value=this.value">
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3 class="card-title">Konten &amp; Redirect</h3></div>
      <div class="card-content" style="display:flex;flex-direction:column;gap:16px;">

        <div>
          <label class="label">Deskripsi / Pesan</label>
          <textarea id="th_desc" class="input" rows="3" oninput="KNK.d.thanksCfg.description=this.value"></textarea>
        </div>

        <!-- Tipe Redirect — 3 pilihan visual -->
        <div>
          <label class="label" style="margin-bottom:8px;">Tujuan Setelah Submit</label>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;">

            <label id="rtype_cs_card" onclick="KNK.onRedirectType('cs')"
              style="display:flex;flex-direction:column;gap:8px;padding:14px;border:2px solid hsl(var(--primary));border-radius:var(--radius);cursor:pointer;transition:border-color .15s;background:hsl(var(--primary)/.04);">
              <div style="display:flex;align-items:center;gap:8px;">
                <input type="radio" name="redirect_type" value="cs" id="rtype_cs" checked style="accent-color:hsl(var(--primary));flex-shrink:0;">
                <span style="font-size:13px;font-weight:600;">Kontak Utama CS</span>
              </div>
              <div id="rtype_cs_hint" style="font-size:11px;color:hsl(var(--muted-foreground));line-height:1.5;padding-left:20px;">
                Redirect ke kontak operator yang menangani lead ini (WA / Telegram / Email — sesuai pengaturan operator)
              </div>
            </label>

            <label id="rtype_url_card" onclick="KNK.onRedirectType('url')"
              style="display:flex;flex-direction:column;gap:8px;padding:14px;border:2px solid hsl(var(--border));border-radius:var(--radius);cursor:pointer;transition:border-color .15s;">
              <div style="display:flex;align-items:center;gap:8px;">
                <input type="radio" name="redirect_type" value="url" id="rtype_url" style="accent-color:hsl(var(--primary));flex-shrink:0;">
                <span style="font-size:13px;font-weight:600;">Custom URL</span>
              </div>
              <div style="font-size:11px;color:hsl(var(--muted-foreground));line-height:1.5;padding-left:20px;">
                Arahkan ke URL tertentu — halaman produk, payment link, dsb.
              </div>
            </label>

            <label id="rtype_none_card" onclick="KNK.onRedirectType('none')"
              style="display:flex;flex-direction:column;gap:8px;padding:14px;border:2px solid hsl(var(--border));border-radius:var(--radius);cursor:pointer;transition:border-color .15s;">
              <div style="display:flex;align-items:center;gap:8px;">
                <input type="radio" name="redirect_type" value="none" id="rtype_none" style="accent-color:hsl(var(--primary));flex-shrink:0;">
                <span style="font-size:13px;font-weight:600;">Tidak Ada</span>
              </div>
              <div style="font-size:11px;color:hsl(var(--muted-foreground));line-height:1.5;padding-left:20px;">
                Tampilkan halaman thanks saja tanpa redirect otomatis.
              </div>
            </label>

          </div>
        </div>

        <!-- URL input — hanya tampil jika pilih Custom URL -->
        <div id="th_url_row" style="display:none;">
          <label class="label">URL Tujuan</label>
          <input id="th_url" class="input" placeholder="https://..." oninput="KNK.d.thanksCfg.redirect_url=this.value">
        </div>

        <!-- Pesan ke CS — tampil jika pilih Kontak Utama CS -->
        <div id="th_msg_row">
          <label class="label">Pesan Otomatis ke CS</label>
          <textarea id="th_msg" class="input" rows="3" oninput="KNK.d.thanksCfg.custom_message=this.value"></textarea>
          <p class="form-description">Variabel: [name] [phone] [email] [product] [quantity] [oname]</p>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
          <div>
            <label class="label">Delay Redirect (detik)</label>
            <input type="number" id="th_delay" class="input" min="0" max="60"
              oninput="KNK.d.thanksCfg.delay_redirect=parseInt(this.value)||0">
          </div>
          <div style="display:flex;align-items:flex-end;padding-bottom:2px;">
            <label class="checkbox-item">
              <input type="checkbox" id="th_countdown" onchange="KNK.d.thanksCfg.show_countdown=this.checked">
              <span><div class="checkbox-label">Tampilkan hitung mundur</div></span>
            </label>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- ══ TAB: PIXEL ════════════════════════════════════════════════ -->
  <div id="tab-pixel" class="tab-pane" style="display:none;flex-direction:column;gap:12px;">

    <?php
    $metaEvents   = ['PageView','ViewContent','Search','AddToCart','InitiateCheckout','AddPaymentInfo','Lead','CompleteRegistration','Purchase','Subscribe','StartTrial','SubmitApplication','Contact'];
    $tiktokEvents = ['PageView','ViewContent','ClickButton','Search','AddWishlist','AddToCart','InitiateCheckout','AddPaymentInfo','PlaceAnOrder','CompleteRegistration','Subscribe','SubmitForm','Download','Contact','CompletePayment'];
    $snackEvents  = ['EVENT_CONTENT_VIEW','EVENT_ADD_TO_CART','EVENT_FORM_SUBMIT','EVENT_COMPLETE_REGISTRATION','EVENT_PURCHASE','EVENT_SEARCH','EVENT_CLICK_BUTTON','EVENT_SUBSCRIBE'];
    $currencies   = ['IDR','USD','SGD','MYR','THB','PHP','VND'];

    function pixelSelect($id, $options, $platform, $key, $val) {
        $out = '<select id="' . $id . '" class="input" style="font-size:12px;padding:6px 10px;" onchange="KNK.setPixel(\'' . $platform . '\',\'' . $key . '\',this.value)">';
        $out .= '<option value="">— Tidak Digunakan —</option>';
        foreach ($options as $opt) {
            $sel = ($opt === $val) ? ' selected' : '';
            $out .= '<option value="' . htmlspecialchars($opt, ENT_QUOTES) . '"' . $sel . '>' . htmlspecialchars($opt, ENT_QUOTES) . '</option>';
        }
        $out .= '</select>';
        return $out;
    }

    function pxLabel($txt) {
        return '<label class="label" style="font-size:11px;margin-bottom:4px;">' . $txt . '</label>';
    }

    // Step label helper — JS will update text but PHP renders initial (form type default)
    $step2LabelForm = 'Form Submit';
    $step2LabelWa   = 'Link Clicked';
    $step2DescForm  = 'Saat pengunjung kirim form';
    $step2DescWa    = 'Saat tombol WA diklik';
    ?>

    <!-- ── META ──────────────────────────────────────────────────── -->
    <details class="card card-details" id="px_meta_details" open>
      <summary style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
          <div style="width:32px;height:32px;border-radius:8px;background:#1877f2;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg style="width:16px;height:16px;fill:#fff;" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
          </div>
          <div>
            <div style="font-size:13px;font-weight:600;line-height:1.3;">Meta / Facebook</div>
            <div style="font-size:11px;color:hsl(var(--muted-foreground));">CAPI v21.0</div>
          </div>
          <span id="px_meta_status_badge" class="px-status-badge" style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;padding:2px 8px;border-radius:99px;">
            <span id="px_meta_status_dot" class="px-status-dot" style="width:5px;height:5px;border-radius:50%;"></span>
            <span id="px_meta_status_text" class="px-status-text"></span>
          </span>
        </div>
        <div onclick="event.stopPropagation();KNK.togglePixel('meta')" id="px_meta_toggle" style="position:relative;width:44px;height:24px;flex-shrink:0;cursor:pointer;">
          <div id="px_meta_track" style="width:44px;height:24px;border-radius:12px;transition:background .2s;"></div>
          <div id="px_meta_thumb" style="position:absolute;top:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.3);transition:left .2s;pointer-events:none;"></div>
        </div>
      </summary>
      <div class="details-body" id="px_meta_body" style="display:flex;flex-direction:column;gap:14px;">

        <!-- Kredensial -->
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
          <div>
            <?= pxLabel('Pixel ID') ?>
            <input id="px_meta_pixel_id" class="input" style="font-size:12px;padding:6px 10px;" placeholder="123456789012345"
              value="<?= ae($pixelCfg['meta']['pixel_id']) ?>" oninput="KNK.setPixel('meta','pixel_id',this.value)">
          </div>
          <div>
            <?= pxLabel('Access Token (CAPI)') ?>
            <input id="px_meta_token" class="input" style="font-size:12px;padding:6px 10px;" placeholder="EAAxxxxxxxx..."
              value="<?= ae($pixelCfg['meta']['token']) ?>" oninput="KNK.setPixel('meta','token',this.value)">
          </div>
          <div>
            <?= pxLabel('Test Event Code') ?>
            <input id="px_meta_test_event_code" class="input" style="font-size:12px;padding:6px 10px;" placeholder="TEST12345"
              value="<?= ae($pixelCfg['meta']['test_event_code']) ?>" oninput="KNK.setPixel('meta','test_event_code',this.value)">
          </div>
        </div>

        <!-- Event per step -->
        <div style="background:hsl(var(--muted));border-radius:var(--radius);overflow:hidden;">
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:hsl(var(--border));">
            <div style="background:hsl(var(--card));padding:12px 14px;">
              <div style="font-size:10px;font-weight:600;color:hsl(var(--muted-foreground));text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px;">
                Step 1 — Page Load
              </div>
              <?= pixelSelect('px_meta_page_load_event', $metaEvents, 'meta', 'page_load_event', $pixelCfg['meta']['page_load_event']) ?>
            </div>
            <div style="background:hsl(var(--card));padding:12px 14px;">
              <div style="font-size:10px;font-weight:600;color:hsl(var(--muted-foreground));text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px;"
                id="px_meta_step2_label" data-form="Step 2 — <?= ae($step2LabelForm) ?>" data-wa="Step 2 — <?= ae($step2LabelWa) ?>">
                Step 2 — <?= ae($step2LabelForm) ?>
              </div>
              <?= pixelSelect('px_meta_form_submit_event', $metaEvents, 'meta', 'form_submit_event', $pixelCfg['meta']['form_submit_event']) ?>
            </div>
            <div style="background:hsl(var(--card));padding:12px 14px;">
              <div style="font-size:10px;font-weight:600;color:hsl(var(--muted-foreground));text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px;">
                Step 3 — Thanks Page
              </div>
              <?= pixelSelect('px_meta_thanks_page_event', $metaEvents, 'meta', 'thanks_page_event', $pixelCfg['meta']['thanks_page_event']) ?>
            </div>
          </div>
        </div>

        <!-- Nilai & currency -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <div>
            <?= pxLabel('Nilai Konversi') ?>
            <input id="px_meta_value" class="input" style="font-size:12px;padding:6px 10px;" placeholder="0"
              value="<?= ae($pixelCfg['meta']['value']) ?>" oninput="KNK.setPixel('meta','value',this.value)">
          </div>
          <div>
            <?= pxLabel('Currency') ?>
            <select id="px_meta_currency" class="input" style="font-size:12px;padding:6px 10px;" onchange="KNK.setPixel('meta','currency',this.value)">
              <?php foreach($currencies as $c): ?><option value="<?= $c ?>" <?= (isset($pixelCfg['meta']['currency']) ? $pixelCfg['meta']['currency'] : 'IDR') === $c ? 'selected':'' ?>><?= $c ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>

      </div>
    </details>

    <!-- ── TIKTOK ─────────────────────────────────────────────────── -->
    <details class="card card-details" id="px_tiktok_details">
      <summary style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
          <div style="width:32px;height:32px;border-radius:8px;background:#010101;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg style="width:16px;height:16px;fill:#fff;" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 0 0-.79-.05 6.34 6.34 0 0 0-6.34 6.34 6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.33-6.34V8.97a8.16 8.16 0 0 0 4.77 1.52V7.01a4.85 4.85 0 0 1-1-.32z"/></svg>
          </div>
          <div>
            <div style="font-size:13px;font-weight:600;line-height:1.3;">TikTok</div>
            <div style="font-size:11px;color:hsl(var(--muted-foreground));">Events API v1.3</div>
          </div>
          <span id="px_tiktok_status_badge" class="px-status-badge" style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;padding:2px 8px;border-radius:99px;">
            <span id="px_tiktok_status_dot" class="px-status-dot" style="width:5px;height:5px;border-radius:50%;"></span>
            <span id="px_tiktok_status_text" class="px-status-text"></span>
          </span>
        </div>
        <div onclick="event.stopPropagation();KNK.togglePixel('tiktok')" id="px_tiktok_toggle" style="position:relative;width:44px;height:24px;flex-shrink:0;cursor:pointer;">
          <div id="px_tiktok_track" style="width:44px;height:24px;border-radius:12px;transition:background .2s;"></div>
          <div id="px_tiktok_thumb" style="position:absolute;top:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.3);transition:left .2s;pointer-events:none;"></div>
        </div>
      </summary>
      <div class="details-body" id="px_tiktok_body" style="display:flex;flex-direction:column;gap:14px;">

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
          <div>
            <?= pxLabel('Pixel ID') ?>
            <input id="px_tiktok_pixel_id" class="input" style="font-size:12px;padding:6px 10px;" placeholder="CPXXXXXXXX"
              value="<?= ae($pixelCfg['tiktok']['pixel_id']) ?>" oninput="KNK.setPixel('tiktok','pixel_id',this.value)">
          </div>
          <div>
            <?= pxLabel('Access Token') ?>
            <input id="px_tiktok_access_token" class="input" style="font-size:12px;padding:6px 10px;" placeholder="Token dari Events Manager"
              value="<?= ae($pixelCfg['tiktok']['access_token']) ?>" oninput="KNK.setPixel('tiktok','access_token',this.value)">
          </div>
          <div>
            <?= pxLabel('Test Event Code') ?>
            <input id="px_tiktok_test_event_code" class="input" style="font-size:12px;padding:6px 10px;" placeholder="TEST12345"
              value="<?= ae($pixelCfg['tiktok']['test_event_code']) ?>" oninput="KNK.setPixel('tiktok','test_event_code',this.value)">
          </div>
        </div>

        <div style="background:hsl(var(--muted));border-radius:var(--radius);overflow:hidden;">
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:hsl(var(--border));">
            <div style="background:hsl(var(--card));padding:12px 14px;">
              <div style="font-size:10px;font-weight:600;color:hsl(var(--muted-foreground));text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px;">Step 1 — Page Load</div>
              <?= pixelSelect('px_tiktok_page_load_event', $tiktokEvents, 'tiktok', 'page_load_event', $pixelCfg['tiktok']['page_load_event']) ?>
            </div>
            <div style="background:hsl(var(--card));padding:12px 14px;">
              <div style="font-size:10px;font-weight:600;color:hsl(var(--muted-foreground));text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px;"
                id="px_tiktok_step2_label" data-form="Step 2 — <?= ae($step2LabelForm) ?>" data-wa="Step 2 — <?= ae($step2LabelWa) ?>">
                Step 2 — <?= ae($step2LabelForm) ?>
              </div>
              <?= pixelSelect('px_tiktok_form_submit_event', $tiktokEvents, 'tiktok', 'form_submit_event', $pixelCfg['tiktok']['form_submit_event']) ?>
            </div>
            <div style="background:hsl(var(--card));padding:12px 14px;">
              <div style="font-size:10px;font-weight:600;color:hsl(var(--muted-foreground));text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px;">Step 3 — Thanks Page</div>
              <?= pixelSelect('px_tiktok_thanks_page_event', $tiktokEvents, 'tiktok', 'thanks_page_event', $pixelCfg['tiktok']['thanks_page_event']) ?>
            </div>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <div>
            <?= pxLabel('Nilai Konversi') ?>
            <input id="px_tiktok_value" class="input" style="font-size:12px;padding:6px 10px;" placeholder="0"
              value="<?= ae($pixelCfg['tiktok']['value']) ?>" oninput="KNK.setPixel('tiktok','value',this.value)">
          </div>
          <div>
            <?= pxLabel('Currency') ?>
            <select id="px_tiktok_currency" class="input" style="font-size:12px;padding:6px 10px;" onchange="KNK.setPixel('tiktok','currency',this.value)">
              <?php foreach($currencies as $c): ?><option value="<?= $c ?>" <?= (isset($pixelCfg['tiktok']['currency']) ? $pixelCfg['tiktok']['currency'] : 'IDR') === $c ? 'selected':'' ?>><?= $c ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>

      </div>
    </details>

    <!-- ── SNACKVIDEO ─────────────────────────────────────────────── -->
    <details class="card card-details" id="px_snack_details">
      <summary style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
          <div style="width:32px;height:32px;border-radius:8px;background:#e4372c;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg style="width:16px;height:16px;fill:#fff;" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/></svg>
          </div>
          <div>
            <div style="font-size:13px;font-weight:600;line-height:1.3;">SnackVideo / Kwai</div>
            <div style="font-size:11px;color:hsl(var(--muted-foreground));">Ads Pixel</div>
          </div>
          <span id="px_snack_status_badge" class="px-status-badge" style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;padding:2px 8px;border-radius:99px;">
            <span id="px_snack_status_dot" class="px-status-dot" style="width:5px;height:5px;border-radius:50%;"></span>
            <span id="px_snack_status_text" class="px-status-text"></span>
          </span>
        </div>
        <div onclick="event.stopPropagation();KNK.togglePixel('snack')" id="px_snack_toggle" style="position:relative;width:44px;height:24px;flex-shrink:0;cursor:pointer;">
          <div id="px_snack_track" style="width:44px;height:24px;border-radius:12px;transition:background .2s;"></div>
          <div id="px_snack_thumb" style="position:absolute;top:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.3);transition:left .2s;pointer-events:none;"></div>
        </div>
      </summary>
      <div class="details-body" id="px_snack_body" style="display:flex;flex-direction:column;gap:14px;">

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
          <div>
            <?= pxLabel('Pixel ID') ?>
            <input id="px_snack_pixel_id" class="input" style="font-size:12px;padding:6px 10px;" placeholder="Pixel ID"
              value="<?= ae($pixelCfg['snack']['pixel_id']) ?>" oninput="KNK.setPixel('snack','pixel_id',this.value)">
          </div>
          <div>
            <?= pxLabel('Access Token') ?>
            <input id="px_snack_access_token" class="input" style="font-size:12px;padding:6px 10px;" placeholder="Access Token"
              value="<?= ae($pixelCfg['snack']['access_token']) ?>" oninput="KNK.setPixel('snack','access_token',this.value)">
          </div>
          <div>
            <?= pxLabel('Test Click ID') ?>
            <input id="px_snack_test_click_id" class="input" style="font-size:12px;padding:6px 10px;" placeholder="Click ID test"
              value="<?= ae($pixelCfg['snack']['test_click_id']) ?>" oninput="KNK.setPixel('snack','test_click_id',this.value)">
          </div>
        </div>

        <div style="background:hsl(var(--muted));border-radius:var(--radius);overflow:hidden;">
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:hsl(var(--border));">
            <div style="background:hsl(var(--card));padding:12px 14px;">
              <div style="font-size:10px;font-weight:600;color:hsl(var(--muted-foreground));text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px;">Step 1 — Page Load</div>
              <?= pixelSelect('px_snack_page_load_event', $snackEvents, 'snack', 'page_load_event', $pixelCfg['snack']['page_load_event']) ?>
            </div>
            <div style="background:hsl(var(--card));padding:12px 14px;">
              <div style="font-size:10px;font-weight:600;color:hsl(var(--muted-foreground));text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px;"
                id="px_snack_step2_label" data-form="Step 2 — <?= ae($step2LabelForm) ?>" data-wa="Step 2 — <?= ae($step2LabelWa) ?>">
                Step 2 — <?= ae($step2LabelForm) ?>
              </div>
              <?= pixelSelect('px_snack_form_submit_event', $snackEvents, 'snack', 'form_submit_event', $pixelCfg['snack']['form_submit_event']) ?>
            </div>
            <div style="background:hsl(var(--card));padding:12px 14px;">
              <div style="font-size:10px;font-weight:600;color:hsl(var(--muted-foreground));text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px;">Step 3 — Thanks Page</div>
              <?= pixelSelect('px_snack_thanks_page_event', $snackEvents, 'snack', 'thanks_page_event', $pixelCfg['snack']['thanks_page_event']) ?>
            </div>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
          <div>
            <?= pxLabel('Nilai Konversi') ?>
            <input id="px_snack_value" class="input" style="font-size:12px;padding:6px 10px;" placeholder="0"
              value="<?= ae($pixelCfg['snack']['value']) ?>" oninput="KNK.setPixel('snack','value',this.value)">
          </div>
          <div>
            <?= pxLabel('Currency') ?>
            <select id="px_snack_currency" class="input" style="font-size:12px;padding:6px 10px;" onchange="KNK.setPixel('snack','currency',this.value)">
              <?php foreach($currencies as $c): ?><option value="<?= $c ?>" <?= (isset($pixelCfg['snack']['currency']) ? $pixelCfg['snack']['currency'] : 'IDR') === $c ? 'selected':'' ?>><?= $c ?></option><?php endforeach; ?>
            </select>
          </div>
          <div style="display:flex;align-items:flex-end;padding-bottom:2px;">
            <label class="checkbox-item">
              <input type="checkbox" id="px_snack_test" onchange="KNK.setPixel('snack','test_mode',this.checked?'1':'')">
              <span><div class="checkbox-label">Test Mode</div></span>
            </label>
          </div>
        </div>

        <div>
          <?= pxLabel('HTML Snippet (opsional)') ?>
          <textarea id="px_snack_html" class="input" rows="2" style="font-size:12px;"
            oninput="KNK.setPixel('snack','html',this.value)"><?= ae($pixelCfg['snack']['html']) ?></textarea>
        </div>

      </div>
    </details>

    <!-- ── GOOGLE ADS / GTM / GA4 ─────────────────────────────────── -->
    <details class="card card-details" id="px_google_details">
      <summary style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
        <div style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;">
          <div style="width:32px;height:32px;border-radius:8px;background:#fff;border:1px solid #e0e0e0;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <svg style="width:16px;height:16px;" viewBox="0 0 24 24"><path fill="#4285f4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34a853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#fbbc05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#ea4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
          </div>
          <div>
            <div style="font-size:13px;font-weight:600;line-height:1.3;">Google Ads / GTM</div>
            <div style="font-size:11px;color:hsl(var(--muted-foreground));">GA4 &amp; Conversion Tracking</div>
          </div>
          <span id="px_google_status_badge" class="px-status-badge" style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;padding:2px 8px;border-radius:99px;">
            <span id="px_google_status_dot" class="px-status-dot" style="width:5px;height:5px;border-radius:50%;"></span>
            <span id="px_google_status_text" class="px-status-text"></span>
          </span>
        </div>
        <div onclick="event.stopPropagation();KNK.togglePixel('google')" id="px_google_toggle" style="position:relative;width:44px;height:24px;flex-shrink:0;cursor:pointer;">
          <div id="px_google_track" style="width:44px;height:24px;border-radius:12px;transition:background .2s;"></div>
          <div id="px_google_thumb" style="position:absolute;top:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.3);transition:left .2s;pointer-events:none;"></div>
        </div>
      </summary>
      <div class="details-body" id="px_google_body" style="display:flex;flex-direction:column;gap:14px;">

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
          <div>
            <?= pxLabel('GTM ID') ?>
            <input id="px_google_gtm_id" class="input" style="font-size:12px;padding:6px 10px;" placeholder="GTM-XXXXXXX"
              value="<?= ae($pixelCfg['google']['gtm_id']) ?>" oninput="KNK.setPixel('google','gtm_id',this.value)">
          </div>
          <div>
            <?= pxLabel('Conversion ID (tanpa AW-)') ?>
            <input id="px_google_conversion_id" class="input" style="font-size:12px;padding:6px 10px;" placeholder="123456789"
              value="<?= ae($pixelCfg['google']['conversion_id']) ?>" oninput="KNK.setPixel('google','conversion_id',this.value)">
          </div>
          <div>
            <?= pxLabel('GA4 Measurement ID') ?>
            <input id="px_google_ga4_id" class="input" style="font-size:12px;padding:6px 10px;" placeholder="G-XXXXXXXXXX"
              value="<?= ae($pixelCfg['google']['ga4_id']) ?>" oninput="KNK.setPixel('google','ga4_id',this.value)">
          </div>
        </div>

        <!-- Conversion labels per step -->
        <div style="background:hsl(var(--muted));border-radius:var(--radius);overflow:hidden;">
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1px;background:hsl(var(--border));">
            <div style="background:hsl(var(--card));padding:12px 14px;">
              <div style="font-size:10px;font-weight:600;color:hsl(var(--muted-foreground));text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px;">Step 1 — Page Load</div>
              <?= pxLabel('Conversion Label') ?>
              <input id="px_google_page_load_label" class="input" style="font-size:12px;padding:6px 10px;" placeholder="ConversionLabel"
                value="<?= ae(isset($pixelCfg['google']['page_load_label']) ? $pixelCfg['google']['page_load_label'] : '') ?>"
                oninput="KNK.setPixel('google','page_load_label',this.value)">
            </div>
            <div style="background:hsl(var(--card));padding:12px 14px;">
              <div style="font-size:10px;font-weight:600;color:hsl(var(--muted-foreground));text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px;"
                id="px_google_step2_label" data-form="Step 2 — <?= ae($step2LabelForm) ?>" data-wa="Step 2 — <?= ae($step2LabelWa) ?>">
                Step 2 — <?= ae($step2LabelForm) ?>
              </div>
              <?= pxLabel('Conversion Label') ?>
              <input id="px_google_form_submit_label" class="input" style="font-size:12px;padding:6px 10px;" placeholder="ConversionLabel"
                value="<?= ae(isset($pixelCfg['google']['form_submit_label']) ? $pixelCfg['google']['form_submit_label'] : '') ?>"
                oninput="KNK.setPixel('google','form_submit_label',this.value)">
            </div>
            <div style="background:hsl(var(--card));padding:12px 14px;">
              <div style="font-size:10px;font-weight:600;color:hsl(var(--muted-foreground));text-transform:uppercase;letter-spacing:.4px;margin-bottom:6px;">Step 3 — Thanks Page</div>
              <?= pxLabel('Conversion Label') ?>
              <input id="px_google_thanks_page_label" class="input" style="font-size:12px;padding:6px 10px;" placeholder="ConversionLabel"
                value="<?= ae(isset($pixelCfg['google']['thanks_page_label']) ? $pixelCfg['google']['thanks_page_label'] : '') ?>"
                oninput="KNK.setPixel('google','thanks_page_label',this.value)">
            </div>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
          <div>
            <?= pxLabel('Nilai Konversi') ?>
            <input id="px_google_value" class="input" style="font-size:12px;padding:6px 10px;" placeholder="0"
              value="<?= ae($pixelCfg['google']['value']) ?>" oninput="KNK.setPixel('google','value',this.value)">
          </div>
          <div>
            <?= pxLabel('Currency') ?>
            <select id="px_google_currency" class="input" style="font-size:12px;padding:6px 10px;" onchange="KNK.setPixel('google','currency',this.value)">
              <?php foreach($currencies as $c): ?><option value="<?= $c ?>" <?= (isset($pixelCfg['google']['currency']) ? $pixelCfg['google']['currency'] : 'IDR') === $c ? 'selected':'' ?>><?= $c ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>

      </div>
    </details>

  </div>

  <!-- ══ TAB: OPERATORS ════════════════════════════════════════════ -->
  <div id="tab-ops" class="tab-pane" style="display:none;flex-direction:column;gap:16px;">

    <!-- Mode distribusi -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Mode Distribusi Lead</h3>
        <p class="card-desc">Pilih cara lead dibagi ke operator CS</p>
      </div>
      <div class="card-content">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">

          <label id="dist_mode_proportional_row" style="display:flex;gap:14px;padding:16px;border:2px solid hsl(var(--border));border-radius:var(--radius);cursor:pointer;transition:border-color .15s;"
            onclick="KNK.setDistMode('proportional')">
            <input type="radio" name="dist_mode" value="proportional" id="dist_mode_proportional"
              style="accent-color:hsl(var(--primary));margin-top:2px;flex-shrink:0;">
            <div>
              <div style="font-size:13px;font-weight:600;margin-bottom:3px;">Proporsi (Bobot)</div>
              <div style="font-size:12px;color:hsl(var(--muted-foreground));line-height:1.5;">
                Lead dibagi berdasarkan rasio bobot. CS A bobot 2, CS B bobot 1 = CS A dapat 2x lebih banyak.
                Cocok untuk CS dengan kapasitas berbeda.
              </div>
              <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;">
                <span class="badge badge-outline">CS A: 2x</span>
                <span class="badge badge-outline">CS B: 1x</span>
                <span style="font-size:11px;color:hsl(var(--muted-foreground));align-self:center;">= 2:1 rasio</span>
              </div>
            </div>
          </label>

          <label id="dist_mode_roundrobin_row" style="display:flex;gap:14px;padding:16px;border:2px solid hsl(var(--border));border-radius:var(--radius);cursor:pointer;transition:border-color .15s;"
            onclick="KNK.setDistMode('roundrobin')">
            <input type="radio" name="dist_mode" value="roundrobin" id="dist_mode_roundrobin"
              style="accent-color:hsl(var(--primary));margin-top:2px;flex-shrink:0;">
            <div>
              <div style="font-size:13px;font-weight:600;margin-bottom:3px;">Giliran (Round-robin)</div>
              <div style="font-size:12px;color:hsl(var(--muted-foreground));line-height:1.5;">
                Lead dibagi bergiliran sama rata. CS A → CS B → CS C → CS A dst.
                Cocok untuk tim CS dengan kemampuan setara.
              </div>
              <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;">
                <span class="badge badge-outline">Lead 1 → CS A</span>
                <span class="badge badge-outline">Lead 2 → CS B</span>
                <span class="badge badge-outline">Lead 3 → CS A</span>
              </div>
            </div>
          </label>

        </div>
        <!-- Preview distribusi -->
        <div id="dist_preview" style="margin-top:12px;padding:10px 14px;background:hsl(var(--muted));border-radius:var(--radius);font-size:12px;color:hsl(var(--muted-foreground));display:none;">
          <strong>Simulasi distribusi:</strong> <span id="dist_preview_text"></span>
        </div>
      </div>
    </div>

    <!-- Daftar operator -->
    <div class="card">
      <div class="card-header">
        <h3 class="card-title">Pilih Operator CS</h3>
        <p class="card-desc" id="ops_mode_desc">Centang operator yang akan menerima lead dari kampanye ini</p>
      </div>
      <div class="card-content" id="opsContainer" style="display:flex;flex-direction:column;gap:8px;">
        <?php if (empty($allOps)): ?>
        <div class="empty-state">
          <?= icon('users','',36) ?>
          <p class="empty-state-title">Belum ada operator</p>
          <p class="empty-state-desc"><a href="../operators/form.php" style="color:hsl(217 91% 60%);">Tambah operator</a> terlebih dahulu.</p>
        </div>
        <?php else: ?>
        <?php foreach ($allOps as $op): ?>
        <div id="op_row_<?= (int)$op->id ?>" class="tpl-card" style="padding:14px 16px;cursor:default;transition:background .15s;">
          <div style="display:flex;align-items:center;gap:12px;">
            <input type="checkbox" id="op_cb_<?= (int)$op->id ?>"
              style="width:15px;height:15px;accent-color:hsl(var(--primary));flex-shrink:0;cursor:pointer;"
              onchange="KNK.toggleOp(<?= (int)$op->id ?>,this.checked)">

            <!-- Avatar -->
            <div style="width:34px;height:34px;background:hsl(217 91% 60%/0.1);border-radius:50%;display:flex;align-items:center;justify-content:center;color:hsl(217 91% 45%);font-weight:700;font-size:13px;flex-shrink:0;">
              <?= strtoupper(substr($op->name, 0, 1)) ?>
            </div>

            <div style="flex:1;min-width:0;">
              <div style="font-size:13px;font-weight:500;"><?= ae($op->name) ?></div>
              <div style="font-size:11px;color:hsl(var(--muted-foreground));margin-top:1px;text-transform:capitalize;"><?= ae($op->type) ?> &middot; <?= ae($op->value) ?></div>
            </div>

            <span class="badge <?= $op->status === 'on' ? 'badge-success' : 'badge-secondary' ?>"><?= $op->status === 'on' ? 'Online' : 'Offline' ?></span>

            <!-- Weight control (proportional mode) -->
            <div id="op_weight_row_<?= (int)$op->id ?>" style="display:none;align-items:center;gap:6px;">
              <span style="font-size:11px;color:hsl(var(--muted-foreground));">Bobot:</span>
              <div style="display:flex;align-items:center;gap:0;border:1px solid hsl(var(--border));border-radius:var(--radius);overflow:hidden;">
                <button type="button" onclick="KNK.adjustWeight(<?= (int)$op->id ?>,-1)"
                  style="width:28px;height:28px;border:none;background:hsl(var(--muted));cursor:pointer;font-size:16px;font-weight:300;display:flex;align-items:center;justify-content:center;">−</button>
                <span id="op_weight_display_<?= (int)$op->id ?>" style="min-width:28px;text-align:center;font-size:13px;font-weight:700;padding:0 4px;line-height:28px;">1</span>
                <button type="button" onclick="KNK.adjustWeight(<?= (int)$op->id ?>,1)"
                  style="width:28px;height:28px;border:none;background:hsl(var(--muted));cursor:pointer;font-size:16px;font-weight:300;display:flex;align-items:center;justify-content:center;">+</button>
              </div>
              <span id="op_weight_pct_<?= (int)$op->id ?>" style="font-size:11px;color:hsl(var(--primary));font-weight:600;min-width:32px;"></span>
            </div>

            <!-- Round-robin order badge -->
            <div id="op_order_row_<?= (int)$op->id ?>" style="display:none;align-items:center;gap:6px;">
              <span style="font-size:11px;color:hsl(var(--muted-foreground));">Urutan:</span>
              <span id="op_order_badge_<?= (int)$op->id ?>" style="width:24px;height:24px;border-radius:50%;background:hsl(var(--primary));color:#fff;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;">1</span>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </div>

</div><!-- /page-wrapper -->

<script>
// ── KNK: single plain-JS controller (no Alpine, no framework) ──────────────
const KNK = (() => {
  // State — initialised from PHP
  const d = <?= json_encode($alpineInit, JSON_HEX_TAG|JSON_HEX_APOS|JSON_UNESCAPED_UNICODE) ?>;
  const isEdit = <?= $isEditJs ?>;
  // distMode stored in operators_json on save, default to proportional
  if (!d.distMode) d.distMode = 'proportional';

  // ── helpers ────────────────────────────────────────────────────────────
  const $  = (id) => document.getElementById(id);
  const v  = (id) => $( id)?.value ?? '';
  const sv = (id, val) => { const el=$(id); if(el) el.value=val; };
  const ck = (id, val) => { const el=$(id); if(el) el.checked=val; };
  const sh = (id, show) => { const el=$(id); if(el) el.style.display=show?'':('none'); };
  const slug = (s) => s.toLowerCase().trim().replace(/[^a-z0-9\s\-]/g,'').replace(/\s+/g,'-');

  // ── Tab switching ──────────────────────────────────────────────────────
  function tab(name, btn) {
    document.querySelectorAll('.tab-pane').forEach(p => p.style.display='none');
    document.querySelectorAll('.tab-trigger').forEach(b => b.classList.remove('active'));
    const pane = $('tab-'+name);
    if (pane) { pane.style.display='flex'; pane.style.flexDirection='column'; }
    if (btn) btn.classList.add('active');
  }

  // ── Campaign type badge + tab visibility + label updates ──────────────
  function updateCampTypeBadge(type) {
    var isWa = (type === 'wa_link');
    // badge
    var iconEl = $('camp_type_icon'), labelEl = $('camp_type_label');
    if(iconEl)  iconEl.textContent  = isWa ? '🔗' : '📋';
    if(labelEl) labelEl.textContent = isWa ? 'Link' : 'Form Lead';
    var inp = $('inp_type'); if(inp) inp.value = type;
    // Tab "Form" selalu tampil — hanya ganti labelnya
    var formTabLbl = $('tabnav_form_label');
    if(formTabLbl) formTabLbl.textContent = isWa ? 'Tampilan' : 'Form';
    // Show/hide inner panels
    var linkPanel = $('link-appearance-panel');
    var formPanel = $('form-appearance-panel');
    if(linkPanel) linkPanel.style.display = isWa ? 'flex' : 'none';
    if(formPanel) formPanel.style.display = isWa ? 'none' : 'flex';
    if(linkPanel) linkPanel.style.flexDirection = 'column';
    if(formPanel) formPanel.style.flexDirection = 'column';
    // Hide follow-up card for wa_link (no contact data to follow up)
    var followupCard = $('card-followup');
    if(followupCard) followupCard.style.display = isWa ? 'none' : '';
    // page desc
    var desc = $('page_desc_main');
    if(desc) desc.textContent = isWa
      ? 'Atur kampanye Link, tampilan tombol, halaman thanks, tracking pixel, dan routing CS'
      : 'Atur kampanye form, tampilan, tracking pixel, dan routing CS';
    // preview button label
    var prevLabel = $('btn_preview_main_label');
    if(prevLabel) prevLabel.textContent = isWa ? 'Preview Halaman' : 'Preview Form';
    // preview modal tab label
    var prevTabLabel = $('prev_tab_form_label');
    if(prevTabLabel) prevTabLabel.textContent = isWa ? 'Link' : 'Form';
    // pixel step 2 labels
    updatePixelStepLabels(type);
  }

  // ── Dist mode ──────────────────────────────────────────────────────────
  function setDistMode(mode) {
    d.distMode = mode;
    var isProp = (mode === 'proportional');
    var pr = $('dist_mode_proportional'); if(pr) pr.checked = isProp;
    var rr = $('dist_mode_roundrobin');   if(rr) rr.checked = !isProp;
    var prow = $('dist_mode_proportional_row');
    var rrow = $('dist_mode_roundrobin_row');
    if(prow) prow.style.borderColor = isProp ? 'hsl(var(--primary))' : 'hsl(var(--border))';
    if(rrow) rrow.style.borderColor = !isProp ? 'hsl(var(--primary))' : 'hsl(var(--border))';
    var desc = $('ops_mode_desc');
    if(desc) desc.textContent = isProp
      ? 'Atur bobot setiap operator — bobot lebih tinggi = lebih banyak lead'
      : 'Lead akan dibagi bergiliran sama rata ke semua operator aktif';
    // Show/hide weight vs order controls for selected ops
    d.operators.forEach(function(op) {
      var wr = $('op_weight_row_'+op.id);
      var or_ = $('op_order_row_'+op.id);
      if(wr)  wr.style.display  = isProp ? 'flex' : 'none';
      if(or_) or_.style.display = !isProp ? 'flex' : 'none';
    });
    updateDistPreview();
    updateOrderBadges();
  }

  function updateDistPreview() {
    var prev = $('dist_preview'), txt = $('dist_preview_text');
    if(!prev || !txt || d.operators.length === 0) { if(prev) prev.style.display='none'; return; }
    prev.style.display = 'block';
    if(d.distMode === 'proportional') {
      var total = d.operators.reduce(function(s,o){return s+(o.weight||1);},0);
      var parts = d.operators.map(function(op){
        var pct = Math.round((op.weight||1)/total*100);
        var nameEl = $('op_row_'+op.id);
        var name = nameEl ? (nameEl.querySelector('[style*="font-weight:500"]')||{}).textContent||('CS #'+op.id) : 'CS #'+op.id;
        return name.trim() + ' ' + pct + '%';
      });
      txt.textContent = parts.join(' · ');
    } else {
      var seq = d.operators.map(function(op,i){
        var nameEl = $('op_row_'+op.id);
        var name = nameEl ? (nameEl.querySelector('[style*="font-weight:500"]')||{}).textContent||('CS #'+op.id) : 'CS #'+op.id;
        return (i+1)+'. '+name.trim();
      });
      txt.textContent = seq.join(' → ') + ' → (ulang)';
    }
  }

  function updateOrderBadges() {
    d.operators.forEach(function(op,i) {
      var badge = $('op_order_badge_'+op.id);
      if(badge) badge.textContent = i+1;
    });
  }

  function updateWeightPcts() {
    var total = d.operators.reduce(function(s,o){return s+(o.weight||1);},0);
    d.operators.forEach(function(op){
      var disp = $('op_weight_display_'+op.id);
      var pct  = $('op_weight_pct_'+op.id);
      if(disp) disp.textContent = op.weight||1;
      if(pct)  pct.textContent  = total > 0 ? Math.round((op.weight||1)/total*100)+'%' : '';
    });
  }

  // ── Update pixel step 2 labels based on campaign type ─────────────────
  function updatePixelStepLabels(type) {
    var isWa = (type === 'wa_link');
    ['meta','tiktok','snack','google'].forEach(function(plat) {
      var el = $('px_'+plat+'_step2_label');
      if(el) el.textContent = isWa ? el.getAttribute('data-wa') : el.getAttribute('data-form');
    });
  }

  // ── Name input → auto-slug ─────────────────────────────────────────────
  function onNameInput(val) {
    d.camp.name = val;
    if (!isEdit) {
      d.camp.slug = slug(val);
      sv('inp_slug', d.camp.slug);
    }
    const sp = $('slugPreview'); if(sp) sp.textContent = d.camp.slug || 'slug-kampanye';
  }

  // ── Toggle helper (show/hide by id) ────────────────────────────────────
  function toggle(id, show) { sh(id, show); }

  // ── Redirect type change ───────────────────────────────────────────────
  function onRedirectType(val) {
    d.thanksCfg.redirect_type = val;
    sh('th_url_row', val==='url');
    sh('th_msg_row', val==='cs');
    // radio buttons
    ['cs','url','none'].forEach(function(v) {
      var radio = $('rtype_'+v);
      var card  = $('rtype_'+v+'_card');
      if(radio) radio.checked = (v === val);
      if(card)  card.style.borderColor = (v === val) ? 'hsl(var(--primary))' : 'hsl(var(--border))';
      if(card)  card.style.background  = (v === val) ? 'hsl(var(--primary)/.04)' : '';
    });
  }

  // ── Scheme lookup table (shared between form and link) ───────────────
  var ALL_SCHEMES = {
    modern:        {bg:'#f8fafc', card:'#ffffff',               accent:'#2563eb', text:'#0f172a'},
    classic:       {bg:'#fff8f0', card:'#ffffff',               accent:'#dc2626', text:'#1a1a1a'},
    classic_flat:  {bg:'#b7d09a', card:'#b7d09a',               accent:'#FF5000', text:'#000000'},
    minimal:       {bg:'#fafafa', card:'#ffffff',               accent:'#111111', text:'#111111'},
    card:          {bg:'#eff6ff', card:'#ffffff',               accent:'#2563eb', text:'#0f172a'},
    gradient:      {bg:'#0f2027', card:'rgba(255,255,255,.12)', accent:'#38bdf8', text:'#e0f2fe'},
    rose:          {bg:'#fff1f2', card:'#ffffff',               accent:'#e11d48', text:'#0f172a'},
    forest:        {bg:'#f0fdf4', card:'#ffffff',               accent:'#16a34a', text:'#0f172a'},
    sunset:        {bg:'#ff6b6b', card:'rgba(255,255,255,.97)', accent:'#ee5a24', text:'#2d3436'},
    ocean:         {bg:'#0077b6', card:'rgba(255,255,255,.14)', accent:'#48cae4', text:'#caf0f8'},
  };

  // Sync thanks theme colors from the active form/link scheme.
  // Skips if user has manually customised a field (tracked by _custom flag).
  function syncThanksThemeFromScheme() {
    var tpl = d.formCfg.template || 'modern';
    var sc  = ALL_SCHEMES[tpl] || ALL_SCHEMES.modern;
    // Apply custom_style accent override if set
    var cs  = d.formCfg.custom_style || {};
    var acc = cs.color_accent || sc.accent;
    var bg  = cs.color_bg     || sc.bg;
    var txt = cs.color_text   || sc.text;
    var card= cs.card_bg      || sc.card;

    d.thanksCfg.theme = d.thanksCfg.theme || {};
    var th = d.thanksCfg.theme;

    // Only update fields the user hasn't manually touched
    if (!th._custom_bg)    { th.bg_color     = bg;   applyThankThemeInput('bg_color',   bg);   }
    if (!th._custom_card)  { th.card_color   = card; applyThankThemeInput('card_color', card); }
    if (!th._custom_acc)   { th.accent_color = acc;  applyThankThemeInput('accent_color',acc); }
    if (!th._custom_txt)   { th.text_color   = txt;  applyThankThemeInput('text_color', txt);  }
  }

  function applyThankThemeInput(key, val) {
    var ti = $('th_'+key), ci = $('th_c_'+key);
    if (ti) ti.value = val;
    if (ci && /^#[0-9a-f]{6}$/i.test(val)) ci.value = val;
  }

  // ── Template / size pickers ────────────────────────────────────────────
  function setFormTpl(tid) {
    d.formCfg.template = tid;
    document.querySelectorAll('#tplGrid .tpl-card').forEach(c => c.classList.remove('selected'));
    $('tpl_'+tid)?.classList.add('selected');
    syncThanksThemeFromScheme();
  }

  function setSize(sid) {
    d.formCfg.size = sid;
    document.querySelectorAll('#sizeGrid .tpl-card').forEach(c => c.classList.remove('selected'));
    $('sz_'+sid)?.classList.add('selected');
  }

  function setThanksTpl(tid) {
    d.thanksCfg.template = tid;
    document.querySelectorAll('#thanksTplGrid .tpl-card').forEach(c => c.classList.remove('selected'));
    $('thnk_'+tid)?.classList.add('selected');
  }

  // ── Custom style ───────────────────────────────────────────────────────
  function setStyle(key, val) {
    d.formCfg.custom_style = d.formCfg.custom_style || {};
    d.formCfg.custom_style[key] = val;
  }

  // ── Thanks theme ───────────────────────────────────────────────────────
  function setTheme(key, val) {
    d.thanksCfg.theme = d.thanksCfg.theme || {};
    d.thanksCfg.theme[key] = val;
    // Mark this field as manually customised so scheme sync won't overwrite it
    var flagMap = {bg_color:'_custom_bg', card_color:'_custom_card', accent_color:'_custom_acc', text_color:'_custom_txt'};
    if (flagMap[key]) d.thanksCfg.theme[flagMap[key]] = true;
  }

  function resetThanksTheme() {
    // Clear all custom flags so syncThanksThemeFromScheme can overwrite all fields
    var th = d.thanksCfg.theme || {};
    delete th._custom_bg; delete th._custom_card;
    delete th._custom_acc; delete th._custom_txt;
    d.thanksCfg.theme = th;
    syncThanksThemeFromScheme();
  }

  // ── Pixel config ───────────────────────────────────────────────────────
  function setPixel(platform, key, val) {
    if (!d.pixelCfg[platform]) d.pixelCfg[platform] = {};
    d.pixelCfg[platform][key] = val;
  }

  function togglePixel(platform) {
    var cur = d.pixelCfg[platform] && d.pixelCfg[platform].enabled === '1';
    var next = !cur;
    d.pixelCfg[platform].enabled = next ? '1' : '0';
    applyPixelToggleUI(platform, next);
  }

  function applyPixelToggleUI(platform, enabled) {
    // body fade
    var body = $('px_' + platform + '_body');
    if (body) {
      body.style.opacity = enabled ? '1' : '.45';
      body.style.pointerEvents = enabled ? '' : 'none';
    }
    // track + thumb
    var track = $('px_' + platform + '_track');
    var thumb = $('px_' + platform + '_thumb');
    if (track) track.style.background = enabled ? 'hsl(var(--primary))' : 'rgba(0,0,0,.2)';
    if (thumb) thumb.style.left = enabled ? '23px' : '3px';
    // status badge
    var badge = $('px_' + platform + '_status_badge');
    var dot   = $('px_' + platform + '_status_dot');
    var txt   = $('px_' + platform + '_status_text');
    if (badge) {
      badge.style.background = enabled ? 'hsl(142 71% 45%/.15)' : 'hsl(var(--muted))';
      badge.style.color      = enabled ? 'hsl(142 71% 35%)' : 'hsl(var(--muted-foreground))';
    }
    if (dot) dot.style.background = enabled ? 'hsl(142 71% 40%)' : 'hsl(var(--muted-foreground))';
    if (txt) txt.textContent = enabled ? 'Aktif' : 'Nonaktif';
  }

  function togglePixelUI(platform, enabled) {
    applyPixelToggleUI(platform, enabled);
  }

  // ── Standard fields rendering ──────────────────────────────────────────
  function renderStdFields() {
    const c = $('stdFieldsContainer'); if(!c) return;
    c.innerHTML = '';
    const types = ['text','tel','email','number','textarea','select'];
    (d.formCfg.fields||[]).forEach((f,i) => {
      const row = document.createElement('div');
      row.style.cssText='display:flex;align-items:center;gap:10px;padding:10px 12px;background:hsl(var(--muted));border-radius:var(--radius);';
      row.innerHTML = `
        <input type="checkbox" ${f.enabled?'checked':''} style="width:15px;height:15px;accent-color:hsl(var(--primary));flex-shrink:0;"
          onchange="KNK.d.formCfg.fields[${i}].enabled=this.checked">
        <div style="display:grid;grid-template-columns:1fr 110px 1fr;gap:8px;flex:1;">
          <input value="${f.label||''}" class="input" style="padding:6px 10px;font-size:12px;" placeholder="Label"
            oninput="KNK.d.formCfg.fields[${i}].label=this.value">
          <select class="input" style="padding:6px 10px;font-size:12px;" onchange="KNK.d.formCfg.fields[${i}].type=this.value">
            ${types.map(t=>`<option value="${t}" ${f.type===t?'selected':''}>${t}</option>`).join('')}
          </select>
          <input value="${f.placeholder||''}" class="input" style="padding:6px 10px;font-size:12px;" placeholder="Placeholder"
            oninput="KNK.d.formCfg.fields[${i}].placeholder=this.value">
        </div>
        <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:500;cursor:pointer;white-space:nowrap;flex-shrink:0;">
          <input type="checkbox" ${f.required?'checked':''} style="width:13px;height:13px;accent-color:hsl(var(--primary));"
            onchange="KNK.d.formCfg.fields[${i}].required=this.checked"> Wajib
        </label>`;
      c.appendChild(row);
    });
  }

  // ── Extra fields ───────────────────────────────────────────────────────
  function renderExtraFields() {
    const c = $('extraFieldsContainer'); if(!c) return;
    const em= $('extraEmpty');
    const ef= d.formCfg.extra_fields||[];
    if(em) em.style.display = ef.length ? 'none' : 'block';
    // remove old rows
    c.querySelectorAll('.extra-row').forEach(r=>r.remove());
    const types = ['text','tel','email','number','textarea','select'];
    ef.forEach((f,i) => {
      const row = document.createElement('div');
      row.className='extra-row';
      row.style.cssText='display:flex;align-items:center;gap:8px;padding:10px 12px;background:hsl(var(--muted));border-radius:var(--radius);';
      row.innerHTML = `
        <div style="display:grid;grid-template-columns:1fr 1fr 100px 1fr;gap:8px;flex:1;">
          <input value="${f.name||''}" class="input" style="padding:6px 10px;font-size:12px;" placeholder="field_key"
            oninput="KNK.d.formCfg.extra_fields[${i}].name=this.value">
          <input value="${f.label||''}" class="input" style="padding:6px 10px;font-size:12px;" placeholder="Label"
            oninput="KNK.d.formCfg.extra_fields[${i}].label=this.value">
          <select class="input" style="padding:6px 10px;font-size:12px;" onchange="KNK.d.formCfg.extra_fields[${i}].type=this.value">
            ${types.map(t=>`<option value="${t}" ${f.type===t?'selected':''}>${t}</option>`).join('')}
          </select>
          <input value="${f.placeholder||''}" class="input" style="padding:6px 10px;font-size:12px;" placeholder="Placeholder"
            oninput="KNK.d.formCfg.extra_fields[${i}].placeholder=this.value">
        </div>
        <label style="display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;flex-shrink:0;">
          <input type="checkbox" ${f.required?'checked':''} style="width:13px;height:13px;accent-color:hsl(var(--primary));"
            onchange="KNK.d.formCfg.extra_fields[${i}].required=this.checked"> Wajib
        </label>
        <button type="button" onclick="KNK.removeExtraField(${i})" class="btn btn-ghost btn-sm btn-icon" style="color:hsl(var(--destructive));flex-shrink:0;"><?= addslashes(icon('trash-2')) ?></button>`;
      c.appendChild(row);
    });
  }

  function addExtraField() {
    if (!d.formCfg.extra_fields) d.formCfg.extra_fields = [];
    d.formCfg.extra_fields.push({name:'field_'+Date.now(),label:'Field Baru',type:'text',required:false,enabled:true,placeholder:''});
    renderExtraFields();
  }

  function removeExtraField(i) {
    d.formCfg.extra_fields.splice(i,1);
    renderExtraFields();
  }

  // ── Operators ──────────────────────────────────────────────────────────
  function toggleOp(id, checked) {
    var idx = d.operators.findIndex(function(o){return o.id===id;});
    if (checked && idx<0) d.operators.push({id:id,weight:1});
    if (!checked && idx>=0) d.operators.splice(idx,1);
    var isProp = (d.distMode !== 'roundrobin');
    var wr  = $('op_weight_row_'+id);
    var or_ = $('op_order_row_'+id);
    var row = $('op_row_'+id);
    if(wr)  wr.style.display  = checked && isProp  ? 'flex' : 'none';
    if(or_) or_.style.display = checked && !isProp ? 'flex' : 'none';
    if(row) row.classList.toggle('selected', checked);
    updateWeightPcts();
    updateDistPreview();
    updateOrderBadges();
  }

  function adjustWeight(id, delta) {
    var op = d.operators.find(function(o){return o.id===id;});
    if(!op) return;
    op.weight = Math.max(1, Math.min(10, (op.weight||1) + delta));
    var disp = $('op_weight_display_'+id);
    if(disp) disp.textContent = op.weight;
    updateWeightPcts();
    updateDistPreview();
  }

  function setOpWeight(id, w) {
    var op = d.operators.find(function(o){return o.id===id;});
    if(op) { op.weight = w; updateWeightPcts(); updateDistPreview(); }
  }

  // ── Preview ────────────────────────────────────────────────────────────
  function openPreview(which) {
    $('previewModal').style.display='flex';
    switchPreview(which);
  }
  function closePreview() { $('previewModal').style.display='none'; }
  function switchPreview(which) {
    sh('prevForm',   which==='form');
    sh('prevThanks', which==='thanks');
    $('prevTabForm').className   = which==='form'   ? 'btn btn-default btn-sm' : 'btn btn-outline btn-sm';
    $('prevTabThanks').className = which==='thanks' ? 'btn btn-default btn-sm' : 'btn btn-outline btn-sm';
    if (which==='form')   $('prevForm').innerHTML   = buildFormPreview();
    if (which==='thanks') $('prevThanks').innerHTML = buildThanksPreview();
  }

  function buildFormPreview() {
    var isWa = (d.camp.type === 'wa_link');
    if (isWa) return buildWaLinkPreview();

    // Pixel badges
    var pxBadges = [];
    var pxCfg = d.pixelCfg || {};
    // Backward compat: if 'enabled' key doesn't exist, treat as enabled
    var metaOn = pxCfg.meta && (!('enabled' in pxCfg.meta) || pxCfg.meta.enabled) && pxCfg.meta.pixel_id;
    var tiktokOn = pxCfg.tiktok && (!('enabled' in pxCfg.tiktok) || pxCfg.tiktok.enabled) && pxCfg.tiktok.pixel_id;
    var googleOn = pxCfg.google && (!('enabled' in pxCfg.google) || pxCfg.google.enabled) && (pxCfg.google.conversion_id || pxCfg.google.gtm_id || pxCfg.google.ga4_id);
    var snackOn = pxCfg.snack && (!('enabled' in pxCfg.snack) || pxCfg.snack.enabled) && pxCfg.snack.pixel_id;
    if (metaOn) pxBadges.push('<span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;background:#1877f2;color:#fff;padding:3px 8px;border-radius:99px;"><span style="width:6px;height:6px;border-radius:50%;background:#fff;"></span>Meta</span>');
    if (tiktokOn) pxBadges.push('<span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;background:#010101;color:#fff;padding:3px 8px;border-radius:99px;"><span style="width:6px;height:6px;border-radius:50%;background:#fff;"></span>TikTok</span>');
    if (googleOn) pxBadges.push('<span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;background:#4285f4;color:#fff;padding:3px 8px;border-radius:99px;"><span style="width:6px;height:6px;border-radius:50%;background:#fff;"></span>Google</span>');
    if (snackOn) pxBadges.push('<span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;background:#e4372c;color:#fff;padding:3px 8px;border-radius:99px;"><span style="width:6px;height:6px;border-radius:50%;background:#fff;"></span>Snack</span>');
    var pxBar = pxBadges.length ? '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px;">' + pxBadges.join('') + '</div>' : '';

    var S={modern:{bg:'#fff',acc:'#2563eb',txt:'#0f172a',bdr:'#e2e8f0',btnC:'#fff',inp:'#f8fafc',card:'#fff'},classic:{bg:'#fff',acc:'#dc2626',txt:'#1a1a1a',bdr:'#d4a27a',btnC:'#fff',inp:'#fff',card:'#fff'},classic_flat:{bg:'#b7d09a',acc:'#FF5000',txt:'#000',bdr:'#ccc',btnC:'#fff',inp:'#fff',card:'#b7d09a'},minimal:{bg:'#fafafa',acc:'#111',txt:'#111',bdr:'#e2e8f0',btnC:'#fff',inp:'#fff',card:'#fff'},card:{bg:'#eff6ff',acc:'#2563eb',txt:'#0f172a',bdr:'#bfdbfe',btnC:'#fff',inp:'#fff',card:'#fff'},gradient:{bg:'#1e3a5f',acc:'#38bdf8',txt:'#e0f2fe',bdr:'rgba(255,255,255,.2)',btnC:'#0f172a',inp:'rgba(255,255,255,.1)',card:'rgba(255,255,255,.08)'},rose:{bg:'#fff1f2',acc:'#e11d48',txt:'#0f172a',bdr:'#fecdd3',btnC:'#fff',inp:'#fff',card:'#fff'},forest:{bg:'#f0fdf4',acc:'#16a34a',txt:'#0f172a',bdr:'#bbf7d0',btnC:'#fff',inp:'#fff',card:'#fff'},sunset:{bg:'#fff3e0',acc:'#ee5a24',txt:'#2d3436',bdr:'rgba(238,90,36,.3)',btnC:'#fff',inp:'#fff',card:'rgba(255,255,255,.95)'},ocean:{bg:'#e0f7fa',acc:'#0077b6',txt:'#003049',bdr:'#90e0ef',btnC:'#fff',inp:'#fff',card:'#fff'}};
    var SZ={compact:{w:'380px',p:'20px 18px',fs:'13px',bfs:'14px'},default:{w:'480px',p:'28px 24px',fs:'14px',bfs:'15px'},large:{w:'600px',p:'40px 36px',fs:'15px',bfs:'16px'},fullwidth:{w:'100%',p:'28px 24px',fs:'14px',bfs:'15px'}};
    var sc=S[d.formCfg.template]||S.modern, sz=SZ[d.formCfg.size]||SZ.default, cs=d.formCfg.custom_style||{};
    var bg=cs.color_bg||sc.bg, acc=cs.color_accent||sc.acc, txt=cs.color_text||sc.txt;
    var bdr=cs.color_border||sc.bdr, btnC=cs.color_btn_text||sc.btnC, inp=cs.input_bg||sc.inp;
    var mw=cs.max_width||sz.w, pd=cs.padding||sz.p, fz=cs.font_size||sz.fs, bfz=cs.btn_font_size||sz.bfs;
    var isClassicFlat=(d.formCfg.template==='classic_flat');
    var rad=cs.border_radius||(isClassicFlat?'3px':'12px');
    var cardBg=cs.card_bg||sc.card;
    var btnRad=isClassicFlat?'3px':'8px';
    var reqColor=isClassicFlat?'#B30000':'#ef4444';
    var inpBorder=isClassicFlat?('1px solid '+bdr):('1.5px solid '+bdr);
    var inpRad=isClassicFlat?'3px':'7px';
    var fields=[].concat(d.formCfg.fields||[]).concat(d.formCfg.extra_fields||[]).filter(function(f){return f.enabled;});
    var fh='';
    fields.forEach(function(f){
      var req=f.required?'<span style="color:'+reqColor+';margin-left:2px">*</span>':'';
      var inp2=f.type==='textarea'
        ?'<textarea rows="2" placeholder="'+(f.placeholder||f.label)+'" style="width:100%;padding:8px 10px;font-size:'+fz+';border:'+inpBorder+';border-radius:'+inpRad+';background:'+inp+';color:'+txt+';font-family:inherit;outline:none;resize:vertical" readonly></textarea>'
        :f.type==='select'
        ?'<select style="width:100%;padding:8px 10px;font-size:'+fz+';border:'+inpBorder+';border-radius:'+inpRad+';background:'+inp+';color:'+txt+'"><option>-- Pilih --</option></select>'
        :'<input type="'+f.type+'" placeholder="'+(f.placeholder||f.label)+'" style="width:100%;'+(isClassicFlat?'height:36px;padding:0 8px;':'padding:8px 10px;')+'font-size:'+fz+';border:'+inpBorder+';border-radius:'+inpRad+';background:'+inp+';color:'+txt+';outline:none">';
      fh+='<div style="margin-bottom:12px"><label style="display:block;font-size:12px;font-weight:'+(isClassicFlat?'bold':'600')+';margin-bottom:4px;color:'+txt+'">'+f.label+req+'</label>'+inp2+'</div>';
    });
    var st=d.camp.store_name?'<div style="font-size:11px;color:'+acc+';text-align:center;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">'+d.camp.store_name+'</div>':'';
    var pd2=d.camp.product_name?'<div style="font-size:18px;font-weight:800;text-align:center;margin-bottom:4px;color:'+txt+'">'+d.camp.product_name+'</div>':'';
    var tl=cs.tagline?'<div style="font-size:12px;opacity:.6;text-align:center;margin-bottom:14px;color:'+txt+'">'+cs.tagline+'</div>':'';
    var dv=(st||pd2)?'<div style="height:1px;background:'+bdr+';margin:12px 0"></div>':'';
    return '<div style="background:'+bg+';padding:16px;border-radius:8px">'+pxBar+'<div style="max-width:'+mw+';margin:0 auto;background:'+cardBg+';border-radius:'+rad+';padding:'+pd+';color:'+txt+';box-shadow:'+(d.formCfg.template==='minimal'||isClassicFlat?'none':'0 4px 24px rgba(0,0,0,.08)')+'">'+st+pd2+tl+dv+fh+'<button style="width:100%;'+(isClassicFlat?'height:50px;padding:0 20px;':'padding:12px;')+'font-size:'+bfz+';font-weight:700;background:'+acc+';color:'+btnC+';border:none;border-radius:'+btnRad+';cursor:default;margin-top:4px">'+(d.formCfg.submit_label||'Kirim Sekarang')+'</button></div></div>';
  }

  // ─── Link appearance helpers ─────────────────────────────────────────────

  var LINK_SCHEMES = {
    modern:        {bg:'#ffffff',                                    card:'#ffffff',              acc:'#2563eb',btn:'#ffffff',txt:'#0f172a'},
    classic:       {bg:'#fff8f0',                                    card:'#ffffff',              acc:'#dc2626',btn:'#ffffff',txt:'#1a1a1a'},
    classic_flat:  {bg:'#b7d09a',                                    card:'#b7d09a',              acc:'#FF5000',btn:'#ffffff',txt:'#000000'},
    minimal:       {bg:'#fafafa',                                    card:'#ffffff',              acc:'#111111',btn:'#ffffff',txt:'#111111'},
    card:          {bg:'#eff6ff',                                    card:'#ffffff',              acc:'#2563eb',btn:'#ffffff',txt:'#0f172a'},
    gradient:      {bg:'linear-gradient(135deg,#1e3a5f,#0f2027)',   card:'rgba(255,255,255,.08)',acc:'#38bdf8',btn:'#0f172a',txt:'#e0f2fe'},
    rose:          {bg:'#fff1f2',                                    card:'#ffffff',              acc:'#e11d48',btn:'#ffffff',txt:'#0f172a'},
    forest:        {bg:'#f0fdf4',                                    card:'#ffffff',              acc:'#16a34a',btn:'#ffffff',txt:'#0f172a'},
    sunset:        {bg:'linear-gradient(135deg,#ff6b6b,#feca57)',   card:'rgba(255,255,255,.95)',acc:'#ee5a24',btn:'#ffffff',txt:'#2d3436'},
    ocean:         {bg:'linear-gradient(135deg,#0077b6,#00b4d8)',   card:'rgba(255,255,255,.12)',acc:'#48cae4',btn:'#003049',txt:'#caf0f8'},
  };

  var LINK_ICONS = {
    wa:    '<svg style="width:22px;height:22px;fill:currentColor;flex-shrink:0" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.123.554 4.117 1.528 5.852L0 24l6.335-1.508A11.945 11.945 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.818 9.818 0 01-5.002-1.368l-.36-.214-3.72.885.916-3.617-.234-.372A9.818 9.818 0 012.182 12C2.182 6.57 6.57 2.182 12 2.182S21.818 6.57 21.818 12 17.43 21.818 12 21.818z"/></svg>',
    tg:    '<svg style="width:22px;height:22px;fill:currentColor;flex-shrink:0" viewBox="0 0 24 24"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.248-1.97 9.284c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L6.92 14.41l-2.96-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.836.176z"/></svg>',
    line:  '<svg style="width:22px;height:22px;fill:currentColor;flex-shrink:0" viewBox="0 0 24 24"><path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.627-.63h2.386c.349 0 .63.285.63.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.627-.63.349 0 .631.285.631.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.281.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/></svg>',
    email: '<svg style="width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>',
    none:  '',
  };

  function getLinkScheme() {
    var cs  = d.formCfg.custom_style || {};
    var tpl = d.formCfg.template     || 'modern';
    var sc  = LINK_SCHEMES[tpl] || LINK_SCHEMES.modern;
    return {
      bg:   cs.color_bg       || sc.bg,
      card: cs.card_bg        || sc.card,
      acc:  cs.color_accent   || sc.acc,
      btn:  cs.color_btn_text || sc.btn,
      txt:  cs.color_text     || sc.txt,
      mw:   cs.max_width      || '480px',
      rad:  cs.border_radius  || '16px',
      bfz:  cs.btn_font_size  || '16px',
    };
  }

  function setLinkTpl(tid) {
    d.formCfg.template = tid;
    // Clear overrides so scheme takes effect cleanly
    var cs = d.formCfg.custom_style || {};
    ['color_bg','color_accent','color_text','color_btn_text','card_bg'].forEach(function(k) { delete cs[k]; });
    d.formCfg.custom_style = cs;
    // Update UI active state
    document.querySelectorAll('#linkTplGrid .tpl-card').forEach(function(el) {
      el.classList.remove('active');
    });
    var btn = $('ltpl_'+tid);
    if (btn) btn.classList.add('active');
    // Sync link style override inputs to new scheme values
    syncLinkStyleInputs();
    // Also sync thanks theme so it follows the new scheme
    syncThanksThemeFromScheme();
    refreshPreview();
  }

  function setLinkIcon(val) {
    d.formCfg.custom_style = d.formCfg.custom_style || {};
    d.formCfg.custom_style.link_icon = val;
    refreshPreview();
  }

  function syncLinkStyleInputs() {
    var s = getLinkScheme();
    var map = {color_bg: s.bg, color_accent: s.acc, color_text: s.txt, color_btn_text: s.btn};
    Object.keys(map).forEach(function(k) {
      var ti = $('lcs_'+k), ci = $('lcs_c_'+k);
      if (ti) ti.value = map[k];
      if (ci && /^#[0-9a-f]{6}$/i.test(map[k])) ci.value = map[k];
    });
  }

  function initLinkAppearance() {
    var tpl = d.formCfg.template || 'modern';
    // Activate template card
    var btn = $('ltpl_'+tpl);
    if (btn) btn.classList.add('active');
    // Label & tagline
    sv('link_btn_label', d.formCfg.submit_label || 'Hubungi Sekarang');
    sv('link_tagline', (d.formCfg.custom_style || {}).tagline || '');
    // Icon picker
    var iconPick = $('link_icon_pick');
    if (iconPick) {
      var icon = (d.formCfg.custom_style || {}).link_icon || 'wa';
      iconPick.value = icon;
    }
    syncLinkStyleInputs();
  }

  function buildWaLinkPreview() {
    var s     = getLinkScheme();
    var cs    = d.formCfg.custom_style || {};
    var label = d.formCfg.submit_label || 'Hubungi Sekarang';
    var store   = d.camp.store_name   || '';
    var product = d.camp.product_name || '';
    var tagline = cs.tagline || '';
    var iconKey = cs.link_icon || 'wa';
    var iconSvg = LINK_ICONS[iconKey] || '';
    var waIcon  = iconSvg; // keep var name for compat
    var storeLine   = store   ? '<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:'+acc+';margin-bottom:4px;">'+store+'</div>' : '';
    var productLine = product ? '<div style="font-size:20px;font-weight:800;margin-bottom:8px;color:'+txt+';">'+product+'</div>' : '';
    var header = (storeLine||productLine) ? '<div style="text-align:center;padding-bottom:16px;border-bottom:1px solid rgba(0,0,0,.08);margin-bottom:20px;">'+storeLine+productLine+'</div>' : '';
    // Pixel badges
    var pxBadges = [];
    var pxCfg = d.pixelCfg || {};
    // Backward compat: if 'enabled' key doesn't exist, treat as enabled
    var metaOn = pxCfg.meta && (!('enabled' in pxCfg.meta) || pxCfg.meta.enabled) && pxCfg.meta.pixel_id;
    var tiktokOn = pxCfg.tiktok && (!('enabled' in pxCfg.tiktok) || pxCfg.tiktok.enabled) && pxCfg.tiktok.pixel_id;
    var googleOn = pxCfg.google && (!('enabled' in pxCfg.google) || pxCfg.google.enabled) && (pxCfg.google.conversion_id || pxCfg.google.gtm_id || pxCfg.google.ga4_id);
    var snackOn = pxCfg.snack && (!('enabled' in pxCfg.snack) || pxCfg.snack.enabled) && pxCfg.snack.pixel_id;
    if (metaOn) pxBadges.push('<span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;background:#1877f2;color:#fff;padding:3px 8px;border-radius:99px;"><span style="width:6px;height:6px;border-radius:50%;background:#fff;"></span>Meta</span>');
    if (tiktokOn) pxBadges.push('<span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;background:#010101;color:#fff;padding:3px 8px;border-radius:99px;"><span style="width:6px;height:6px;border-radius:50%;background:#fff;"></span>TikTok</span>');
    if (googleOn) pxBadges.push('<span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;background:#4285f4;color:#fff;padding:3px 8px;border-radius:99px;"><span style="width:6px;height:6px;border-radius:50%;background:#fff;"></span>Google</span>');
    if (snackOn) pxBadges.push('<span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;background:#e4372c;color:#fff;padding:3px 8px;border-radius:99px;"><span style="width:6px;height:6px;border-radius:50%;background:#fff;"></span>Snack</span>');
    var pxBar = pxBadges.length ? '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px;">' + pxBadges.join('') + '</div>' : '';

    var storeLine   = store   ? '<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:'+s.acc+';margin-bottom:4px;text-align:center;">'+store+'</div>' : '';
    var productLine = product ? '<div style="font-size:20px;font-weight:800;margin-bottom:6px;color:'+s.txt+';text-align:center;">'+product+'</div>' : '';
    var taglineEl   = tagline ? '<div style="font-size:12px;opacity:.65;text-align:center;margin-bottom:14px;color:'+s.txt+';">'+tagline+'</div>' : '';
    var header      = (storeLine||productLine) ? '<div style="text-align:center;padding-bottom:14px;border-bottom:1px solid rgba(0,0,0,.08);margin-bottom:18px;">'+storeLine+productLine+taglineEl+'</div>' : (taglineEl ? taglineEl : '');

    return '<div style="background:'+s.bg+';padding:28px 20px;border-radius:8px;font-family:-apple-system,BlinkMacSystemFont,sans-serif;">' +
      pxBar +
      '<div style="max-width:'+s.mw+';margin:0 auto;background:'+s.card+';border-radius:'+s.rad+';padding:28px 24px;">' +
        header +
        '<div style="display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:15px 20px;background:'+s.acc+';color:'+s.btn+';border-radius:10px;font-size:'+s.bfz+';font-weight:700;cursor:default;">' +
          waIcon + (waIcon ? ' ' : '') + label +
        '</div>' +
      '</div>' +
    '</div>';
  }

  function buildThanksPreview() {
    const th=d.thanksCfg.theme||{};
    // Derive defaults from form/link scheme if thanks theme not set
    var sc = (d.camp.type === 'wa_link') ? getLinkScheme()
           : (function(){ var S=LINK_SCHEMES; var fm=d.formCfg.custom_style||{}; var sm=S[d.formCfg.template]||S.modern; return {bg:fm.color_bg||sm.bg,card:fm.card_bg||sm.card,acc:fm.color_accent||sm.acc,txt:fm.color_text||sm.txt}; })();
    const bg=th.bg_color||sc.bg||'#f0fdf4', card=th.card_color||sc.card||'#fff', acc=th.accent_color||sc.acc||'#16a34a', txt=th.text_color||sc.txt||'#0f172a';
    const ico=th.icon||'', h=th.heading||'Pesanan Berhasil!', tpl=d.thanksCfg.template||'modern';
    const cr=tpl==='minimal'||tpl==='fullscreen'?'0':'16px';
    const cb=tpl==='minimal'?`border-top:4px solid ${acc}`:'';
    const cs2=tpl==='minimal'||tpl==='fullscreen'?'none':'0 8px 32px rgba(0,0,0,.08)';
    const is=tpl==='celebration'?'60px':'44px';
    // Pixel badges
    var pxBadges = [];
    var pxCfg = d.pixelCfg || {};
    // Backward compat: if 'enabled' key doesn't exist, treat as enabled
    var metaOn = pxCfg.meta && (!('enabled' in pxCfg.meta) || pxCfg.meta.enabled) && pxCfg.meta.pixel_id;
    var tiktokOn = pxCfg.tiktok && (!('enabled' in pxCfg.tiktok) || pxCfg.tiktok.enabled) && pxCfg.tiktok.pixel_id;
    var googleOn = pxCfg.google && (!('enabled' in pxCfg.google) || pxCfg.google.enabled) && (pxCfg.google.conversion_id || pxCfg.google.gtm_id || pxCfg.google.ga4_id);
    var snackOn = pxCfg.snack && (!('enabled' in pxCfg.snack) || pxCfg.snack.enabled) && pxCfg.snack.pixel_id;
    if (metaOn) pxBadges.push('<span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;background:#1877f2;color:#fff;padding:3px 8px;border-radius:99px;"><span style="width:6px;height:6px;border-radius:50%;background:#fff;"></span>Meta</span>');
    if (tiktokOn) pxBadges.push('<span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;background:#010101;color:#fff;padding:3px 8px;border-radius:99px;"><span style="width:6px;height:6px;border-radius:50%;background:#fff;"></span>TikTok</span>');
    if (googleOn) pxBadges.push('<span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;background:#4285f4;color:#fff;padding:3px 8px;border-radius:99px;"><span style="width:6px;height:6px;border-radius:50%;background:#fff;"></span>Google</span>');
    if (snackOn) pxBadges.push('<span style="display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:600;background:#e4372c;color:#fff;padding:3px 8px;border-radius:99px;"><span style="width:6px;height:6px;border-radius:50%;background:#fff;"></span>Snack</span>');
    var pxBar = pxBadges.length ? '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px;">' + pxBadges.join('') + '</div>' : '';
    return '<div style="background:'+bg+';padding:20px;border-radius:8px">'+pxBar+'<div style="background:'+card+';padding:32px 24px;text-align:center;color:'+txt+';border-radius:'+cr+';'+cb+';box-shadow:'+cs2+'"><div style="font-size:'+is+';margin-bottom:12px">'+ico+'</div><h2 style="font-size:20px;font-weight:800;color:'+txt+';margin-bottom:10px">'+h+'</h2><p style="font-size:14px;color:'+txt+';opacity:.7;margin-bottom:20px;line-height:1.6">'+(d.thanksCfg.description||'')+'</p>'+(d.thanksCfg.show_countdown&&d.thanksCfg.delay_redirect>0?'<p style="font-size:12px;color:'+acc+';font-weight:600;margin-bottom:14px">Menghubungkan dalam '+d.thanksCfg.delay_redirect+' detik...</p>':'')+'<button style="width:100%;padding:13px 20px;background:'+acc+';color:#fff;border:none;border-radius:10px;font-size:14px;font-weight:700;cursor:default">Chat Sekarang</button></div></div>';
  }

  // ── Save (populate hidden form then submit) ─────────────────────────────
  function save() {
    sv('f_name',                d.camp.name);
    sv('f_slug',                d.camp.slug);
    sv('f_type',                d.camp.type);
    sv('f_store_name',          d.camp.store_name);
    sv('f_product_name',        d.camp.product_name);
    sv('f_status',              d.camp.status);
    sv('f_block_enabled',       d.camp.block_enabled ? '1' : '0');
    sv('f_block_message',       d.camp.block_message);
    sv('f_double_lead_enabled', d.camp.double_lead_enabled ? '1' : '0');
    sv('f_double_lead_message', d.camp.double_lead_message);
    sv('f_followup_message',    d.camp.followup_message);
    sv('f_form_config_json',        JSON.stringify(d.formCfg));
    // Strip internal _custom_* flags before saving
    var thanksToSave = JSON.parse(JSON.stringify(d.thanksCfg));
    if (thanksToSave.theme) {
      ['_custom_bg','_custom_card','_custom_acc','_custom_txt'].forEach(function(k) {
        delete thanksToSave.theme[k];
      });
    }
    sv('f_thanks_page_config_json', JSON.stringify(thanksToSave));
    sv('f_pixel_config_json',       JSON.stringify(d.pixelCfg));
    sv('f_operators_json', JSON.stringify({mode: d.distMode, operators: d.operators}));
    document.getElementById('mainForm').submit();
  }

  // ── Populate all UI fields from state (called on init) ──────────────────
  function populateUI() {
    sv('inp_name',   d.camp.name);
    sv('inp_slug',   d.camp.slug);
    sv('inp_status', d.camp.status);
    updateCampTypeBadge(d.camp.type);
    sv('inp_store',   d.camp.store_name);
    sv('inp_product', d.camp.product_name);
    ck('inp_block',   d.camp.block_enabled);
    sv('inp_block_msg', d.camp.block_message);
    sh('block_msg_row',  d.camp.block_enabled);
    ck('inp_double',  d.camp.double_lead_enabled);
    sv('inp_double_msg', d.camp.double_lead_message);
    sh('double_msg_row', d.camp.double_lead_enabled);
    sv('inp_followup', d.camp.followup_message);
    const sp=$('slugPreview'); if(sp) sp.textContent=d.camp.slug||'slug-kampanye';

    // Form tab
    sv('inp_submit_label', d.formCfg.submit_label||'');
    sv('inp_tagline', (d.formCfg.custom_style||{}).tagline||'');
    setFormTpl(d.formCfg.template||'modern');
    setSize(d.formCfg.size||'default');

    // Style overrides
    const cs=d.formCfg.custom_style||{};
    ['color_bg','color_accent','color_text','color_border','color_btn_text','input_bg','max_width','padding','font_size','btn_font_size','border_radius','font_family'].forEach(k=>{
      sv('cs_'+k, cs[k]||'');
      const cel=$('cs_c_'+k); if(cel&&cs[k]) cel.value=cs[k];
    });

    // Thanks tab
    setThanksTpl(d.thanksCfg.template||'modern');
    sv('th_desc', d.thanksCfg.description||'');
    onRedirectType(d.thanksCfg.redirect_type||'cs');
    sv('th_url', d.thanksCfg.redirect_url||'');
    sv('th_msg', d.thanksCfg.custom_message||'');
    sv('th_delay', d.thanksCfg.delay_redirect||3);
    ck('th_countdown', !!d.thanksCfg.show_countdown);

    // Sync thanks colors from scheme if no user-defined theme values exist yet
    var th = d.thanksCfg.theme || {};
    var hasUserTheme = th.bg_color || th.card_color || th.accent_color || th.text_color;
    if (!hasUserTheme) {
      syncThanksThemeFromScheme();
    } else {
      // Populate inputs with saved values
      ['heading','bg_color','card_color','accent_color','text_color'].forEach(function(k) {
        sv('th_'+k, th[k]||'');
        var cel=$('th_c_'+k); if(cel&&th[k]) cel.value=th[k];
      });
    }

    // Pixel tab
    Object.keys(d.pixelCfg).forEach(function(plat) {
      const cfg=d.pixelCfg[plat]||{};
      var isEnabled = cfg.enabled === '1';
      applyPixelToggleUI(plat, isEnabled);
      Object.keys(cfg).forEach(function(key) {
        if(key==='enabled') return;
        if(key==='test_mode') { const cel=$('px_snack_test'); if(cel) cel.checked=!!cfg[key]; return; }
        const el=$('px_'+plat+'_'+key);
        if(el && el.tagName!=='SELECT') el.value=cfg[key]||'';
        if(el && el.tagName==='SELECT') el.value=cfg[key]||el.options[0].value;
      });
    });
    updatePixelStepLabels(d.camp.type);

    // Operators
    setDistMode(d.distMode || 'proportional');
    d.operators.forEach(function(op) {
      var cb  = $('op_cb_'+op.id);
      var row = $('op_row_'+op.id);
      var disp= $('op_weight_display_'+op.id);
      if(cb)   { cb.checked = true; }
      if(row)  { row.classList.add('selected'); }
      if(disp) { disp.textContent = op.weight||1; }
      var wr  = $('op_weight_row_'+op.id);
      var or_ = $('op_order_row_'+op.id);
      var isProp = (d.distMode !== 'roundrobin');
      if(wr)  wr.style.display  = isProp  ? 'flex' : 'none';
      if(or_) or_.style.display = !isProp ? 'flex' : 'none';
    });
    updateWeightPcts();
    updateDistPreview();
    updateOrderBadges();

    renderStdFields();
    renderExtraFields();

    // Link appearance — populate if wa_link
    if (d.camp.type === 'wa_link') {
      initLinkAppearance();
    }
  }

  function refreshPreview() {
    // If preview modal is open, refresh the active pane
    var modal = $('previewModal');
    if (!modal || modal.style.display === 'none') return;
    var formPane = $('prevForm'), thanksPane = $('prevThanks');
    if (formPane && formPane.style.display !== 'none') formPane.innerHTML = buildFormPreview();
    if (thanksPane && thanksPane.style.display !== 'none') thanksPane.innerHTML = buildThanksPreview();
  }

  // Public API
  return { d, tab, onNameInput, toggle, onRedirectType, setFormTpl, setSize, setThanksTpl,
           setStyle, setTheme, setPixel, togglePixel, togglePixelUI, applyPixelToggleUI,
           addExtraField, removeExtraField,
           toggleOp, adjustWeight, setOpWeight, setDistMode,
           openPreview, closePreview, switchPreview, save, populateUI,
           updatePixelStepLabels, updateCampTypeBadge,
           setLinkTpl, setLinkIcon, initLinkAppearance, syncLinkStyleInputs, refreshPreview,
           resetThanksTheme };
})();

// Init on DOM ready
document.addEventListener('DOMContentLoaded', () => KNK.populateUI());
</script>

<style>
/* ── tab nav pill style ── */
#tabNav .tab-trigger {
  border-radius: calc(var(--radius) - 1px);
  padding: 14px 10px;
  font-size: 12px;
  font-weight: 500;
  color: hsl(var(--muted-foreground));
  background: transparent;
  border: none;
  transition: background .15s, color .15s, box-shadow .15s;
  white-space: nowrap;
}
#tabNav .tab-trigger:hover {
  background: hsl(var(--background)/.7);
  color: hsl(var(--foreground));
}
#tabNav .tab-trigger.active {
  background: hsl(var(--background));
  color: hsl(var(--foreground));
  font-weight: 600;
  box-shadow: 0 1px 4px rgba(0,0,0,.10);
}
/* ── pixel card details ── */
.card-details > summary {
  padding: 14px 18px;
  cursor: pointer;
  list-style: none;
  user-select: none;
}
.card-details > summary::-webkit-details-marker { display: none; }
.card-details[open] > summary {
  border-bottom: 1px solid hsl(var(--border));
  margin-bottom: 0;
}
.details-body { padding: 18px; }
</style>
<?php include dirname(__DIR__, 2) . '/inc/foot.php'; ?>

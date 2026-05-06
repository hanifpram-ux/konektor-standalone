# Konektor Standalone — Setup Guide

> **Dibuat Oleh:** Hanif Pramono  
> **Website:** [https://hanifprm.my.id](https://hanifprm.my.id)

## Requirements
- PHP 8.1+ dengan ekstensi: PDO, OpenSSL, cURL, mbstring
- MySQL 5.7+ / MariaDB 10.4+
- Apache/Nginx dengan mod_rewrite
- **Tidak butuh Node.js / npm** — semua library via CDN

---

## Step 1: Upload Files
Upload seluruh folder `standalone/` ke web server Anda.

## Step 2: Konfigurasi Web Server

### Apache (rekomendasi)
Arahkan DocumentRoot ke folder `standalone/`. File `.htaccess` sudah tersedia di `public/` dan `admin/`.

Atau tambahkan Alias di Apache config:
```apache
Alias /k     /var/www/konektor/standalone/public
Alias /admin /var/www/konektor/standalone/admin

<Directory /var/www/konektor/standalone/public>
    AllowOverride All
    Require all granted
</Directory>
<Directory /var/www/konektor/standalone/admin>
    AllowOverride All
    Require all granted
</Directory>
```

### Nginx
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/konektor/standalone;

    # Public form pages
    location ~ ^/(k|cs-panel) {
        try_files $uri $uri/ /public/index.php?$query_string;
    }

    # Admin panel (pure PHP, no build needed)
    location /admin {
        try_files $uri $uri/ /admin/index.php;
    }

    # Installer
    location = /install.php {
        root /var/www/konektor/standalone;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## Step 3: Jalankan Installer
Buka browser: `https://yourdomain.com/install.php`

Wizard 4 langkah:
1. **Welcome** — cek kebutuhan sistem
2. **Database** — isi host, port, nama DB, user, password, prefix tabel, base slug, site URL
3. **Admin Account** — buat username + email + password admin
4. **Done** ✅ — semua tabel + config otomatis dibuat

**Hapus atau rename `install.php` setelah instalasi selesai!**

## Step 4: Akses Admin Panel
Buka: `https://yourdomain.com/admin/`

Login dengan akun yang dibuat saat instalasi.

**Tidak perlu npm install, tidak perlu build apapun.**
Admin panel menggunakan Tailwind CSS via CDN + Alpine.js via CDN + Chart.js via CDN.

---

## Struktur Folder

```
standalone/
├── install.php              ← Installer wizard (hapus setelah install)
├── index.php                ← Redirect ke admin/
├── SETUP.md
├── config/
│   ├── config.php           ← Auto-generated oleh installer
│   └── installed.lock       ← Penanda sudah terinstall
├── app/
│   ├── bootstrap.php        ← Autoloader + DB init
│   ├── core/                ← DB, Auth, Router, Crypto, Helper, Logger
│   ├── models/              ← Campaign, Operator, Lead, Rotator, Blocker, Analytics, Settings
│   ├── integrations/        ← MetaApi, TiktokApi, GoogleApi, SnackApi, TelegramApi
│   └── api/                 ← PublicController, CsController, ApiController
├── public/
│   ├── .htaccess
│   ├── index.php            ← Front controller semua halaman publik
│   ├── form.php             ← Halaman form lead
│   ├── thanks.php           ← Halaman terima kasih
│   ├── wa-link.php          ← Halaman WA Link
│   ├── cs-panel.php         ← Panel agent CS
│   ├── blocked.php          ← Halaman terblokir
│   └── 404.php
└── admin/
    ├── .htaccess
    ├── index.php            ← Redirect ke dashboard
    ├── login.php
    ├── logout.php
    ├── dashboard.php
    ├── inc/                 ← Shared: bootstrap, head, sidebar, flash, foot
    └── views/
        ├── campaigns/       ← index.php, form.php
        ├── operators/       ← index.php, form.php
        ├── leads/           ← index.php, blocked.php
        ├── analytics/       ← index.php
        └── settings/        ← index.php (pengaturan), logs.php (API logs)
```

---

## URL Structure

```
yourdomain.com/install.php              → Installer
yourdomain.com/admin/                   → Admin Panel (PHP murni)
yourdomain.com/admin/login.php          → Login admin
yourdomain.com/k/{slug}                 → Halaman form / WA Link
yourdomain.com/k/{slug}/submit          → Endpoint submit form (JSON POST)
yourdomain.com/k/{slug}/thanks          → Halaman terima kasih
yourdomain.com/k/{slug}/pixel           → Server-side pixel ping
yourdomain.com/cs-panel/{token}         → Panel CS agent (tanpa login)
```

*Base slug `k` dan `cs-panel` bisa diubah di Pengaturan admin.*

---

## Fitur

### Form Templates (9 tema warna)
Modern, Classic, Minimal, Card, Gradient, Rose, Forest, Sunset, Ocean

### Ukuran Form (4 preset)
Compact (380px) · Default (480px) · Large (600px) · Full Width

### Kustomisasi Lanjutan
Override warna bg, aksen, teks, border, input background, font size, border radius, padding, font family, tagline — semua via admin tanpa edit code.

### Thanks Page Templates (5 tema)
Modern · Minimal · Card · Celebration (animasi) · Fullscreen

### Preview Real-time
Live preview form dan halaman thanks langsung di editor kampanye admin (rendered via Alpine.js).

### Pixel Integrations (versi terbaru)
- **Meta CAPI v21.0** — server-side, hashed PII (SHA256), fbp/fbc cookie matching
- **TikTok Events API v1.3** — server-side, ttp cookie matching
- **Google Ads / GTM / GA4** — conversion tracking browser-side
- **SnackVideo/Kwai** — event API dengan click_id attribution

### Lead Management
- Enkripsi AES-256-CBC untuk data PII
- Deteksi duplikat via fingerprint (phone+email hash) + cookie ID
- Pemblokiran IP/cookie/fingerprint
- Workflow status: new → contacted → purchased / cancelled / blocked
- Update status inline di tabel leads (tanpa reload halaman)
- Export CSV dengan BOM (kompatibel Excel)
- Catatan per lead

### CS Rotator
- Weighted round-robin (bobot 1–10 per operator)
- Jadwal jam kerja per operator (auto-offline di luar jadwal)
- Token-based CS panel — agent tidak perlu login
- Notifikasi Telegram per lead baru

### Admin Panel (PHP murni, tanpa npm/build)
- Dashboard dengan Chart.js (bar + line chart)
- CRUD: kampanye, operator, leads, daftar terblokir
- Halaman analitik per kampanye atau global
- API logs viewer dengan payload + response detail
- Pengaturan global + ganti password
- Info sistem (PHP version, enkripsi, DB, statistik)

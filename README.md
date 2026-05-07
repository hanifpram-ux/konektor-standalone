# 🚀 Konektor — Standalone Edition

> **Pure PHP Marketing Connector with CS Rotator, Lead Management & Multi-Platform Pixel Tracking**

[![PHP](https://img.shields.io/badge/PHP-7.1%2B-8892BF.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.6%2B-4479A1.svg)](https://mysql.com)
[![Apache](https://img.shields.io/badge/Apache-2.4%2B-D22128.svg)](https://httpd.apache.org)
[![License](https://img.shields.io/badge/license-GPL--3.0-green.svg)](https://www.gnu.org/licenses/gpl-3.0.html)
[![Author](https://img.shields.io/badge/author-Hanif%20Pramono-2563eb.svg)](https://hanifprm.my.id)

---

## ✨ Features

| Feature | Description |
|---------|-------------|
| 🔄 **CS Rotator** | Weighted or round-robin operator assignment with full control |
| 📋 **Lead Manager** | Track, filter, update status, block, and export leads to CSV |
| 📝 **Form Builder** | 7 visual themes, custom fields, real-time style editor |
| 🎯 **Meta CAPI v21** | Server-side + conditional browser pixel |
| 🎵 **TikTok Events API v1.3** | Server-side + conditional browser pixel |
| 🍿 **SnackVideo / Kwai** | Server-side + browser pixel support |
| 🔍 **Google Ads / GTM / GA4** | Browser-side conversion + remarketing tags |
| 🤖 **Telegram Bot** | Instant lead notifications with full context |
| 📊 **Analytics** | Lead trends, source breakdown, campaign performance charts |
| 🧪 **Test Pixel** | Built-in API test panel with log history |
| 🔒 **Security** | Domain blocking, duplicate lead detection, optional encryption |
| 🌍 **Embed Anywhere** | Manual HTML embed code for any platform |
| 📱 **CS Panel** | Standalone customer service panel for operators |

---

## 🚀 Installation

### Requirements

- PHP 7.1 or higher
- MySQL 5.6+ or MariaDB 10.1+
- Apache with `mod_rewrite` enabled
- SSL certificate (recommended for production)

### Step-by-Step

1. **Upload files** to your web server (e.g., `public_html/konektor/`)

```bash
git clone https://github.com/hanifprm/konektor.git
cd konektor/standalone
# Upload this folder to your server
```

2. **Create a MySQL database** (empty database, no tables needed)

3. **Set permissions**

```bash
chmod 755 storage/
chmod 644 config/config.php  # created after install
```

4. **Run the installer**

Open your browser and navigate to:

```
https://yourdomain.com/standalone/
```

If not yet installed, you'll see the installation guide. Click **"Mulai Instalasi"** and follow the steps:

- **Step 1**: Enter database credentials and test connection
- **Step 2**: Create your admin account
- **Done!** You'll be redirected to the admin login

> **Already installed?** Access directly at `https://yourdomain.com/standalone/admin/login.php`

---

## ⚙️ Configuration

### General Settings

After login, go to **Pengaturan**:

| Setting | Description |
|---------|-------------|
| App Name | Display name for your installation |
| Telegram Bot Token | For instant lead notifications |
| Allowed Domains | Restrict embed usage to specific domains |
| CS Panel Slug | Customize CS panel URL (default: `cs-panel`) |
| Encrypt Lead Data | Enable AES encryption for sensitive fields |

### Campaign Setup

1. Go to **Kampanye → Tambah Kampanye**
2. Choose type: **Form Lead** or **WA Link**
3. Configure form fields, themes, and thank-you page
4. Add **Operators** with distribution weights
5. Configure **Pixel Config**:
   - **Meta**: Pixel ID + Access Token
   - **TikTok**: Pixel ID + Access Token
   - **SnackVideo**: Pixel ID + Access Token
   - **Google**: Conversion ID / GTM ID / GA4 ID
6. Save and copy the **embed code**

### Pixel Event Mapping

Per campaign, customize event names for:

| Event | Meta Default | TikTok Default | Snack Default |
|-------|-------------|----------------|---------------|
| Page Load | `PageView` | `PageView` | `PageView` |
| Form Submit | `Lead` | `SubmitForm` | `Lead` |
| Thanks Page | `Purchase` | `CompletePayment` | `Purchase` |

---

## 🔗 Embed Usage

Each campaign provides a ready-to-use HTML embed code. Paste it into:

- Static HTML pages
- Landing page builders (Elementor, Divi, Brizy, etc.)
- CMS platforms (WordPress, Joomla, etc.)
- Custom web apps

> **No shortcodes.** The embed is pure HTML + JavaScript with built-in pixel tracking.

---

## 🧪 Testing Pixels

1. Go to **Pengaturan → Test Pixel**
2. Select an active campaign
3. Choose event type: Page Load / Form Submit / Thanks Page
4. Select platforms to test
5. Click **Kirim Test Event**
6. Review the API response, payload, and pixel code preview in real-time
7. Check the **Riwayat Test & Log API** section for recent API calls

---

## 📁 Project Structure

```
standalone/
├── admin/              # Admin panel (PHP pages, CSS, JS)
│   ├── views/          # Page templates
│   ├── inc/            # Bootstrap, helpers, icons
│   └── login.php       # Admin login
├── app/                # Core application
│   ├── api/            # REST API controllers
│   ├── core/           # Router, DB, Auth, Logger
│   ├── models/         # Campaign, Lead, Operator, etc.
│   └── integrations/   # Meta, TikTok, Snack, Google, Telegram
├── config/             # Config & lock files (generated)
├── public/             # Public front controller
│   ├── css/            # Public stylesheets
│   └── js/             # Public scripts
├── storage/            # Uploads & temp files
├── index.php           # Landing page (auto-redirects)
├── install.php         # Web installer
└── README.md           # This file
```

---

## 🛡️ Security Notes

- Always use HTTPS in production
- Keep `config/config.php` outside web root if possible
- Regularly backup the database
- Update Telegram Bot Token if compromised

---

## 🙏 Credit

** [Hanif Pramono](https://hanifprm.my.id)**  
🔗 [https://hanifprm.my.id](https://hanifprm.my.id)

---

## 📄 License

GPL-3.0-or-later. See [LICENSE](../LICENSE) for details.

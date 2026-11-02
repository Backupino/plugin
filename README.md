<div align="center">

# 📦 Backupino (بکاپینو)
### Fast, Resumable WordPress Backup, Cloning & Easy Migration
**بکاپ‌گیری رایگان و انتقال آسان وردپرس با اینستالر مستقل خودکفا**

[![Version](https://img.shields.io/badge/version-1.0.0--stable-blue.svg?style=for-the-badge)](https://github.com/Backupino/plugin)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%20--%206.8-21759b.svg?style=for-the-badge&logo=wordpress)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%20--%208.4-777bb4.svg?style=for-the-badge&logo=php)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg?style=for-the-badge)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Offline First](https://img.shields.io/badge/Architecture-100%25%20Offline--First-orange.svg?style=for-the-badge)](#offline-first-architecture)

[Official Website](https://backupino.com) • [Documentation Portal](https://github.com/Backupino/documents) • [WordPress Profile](https://profiles.wordpress.org/backupino/)

</div>

---

## 🌟 Overview

**Backupino** is a next-generation WordPress backup, cloning, and migration plugin built specifically for real-world shared hosting environments. It packages your entire WordPress website — database, themes, plugins, uploads, and core files — into a single archive paired with an ultra-lightweight, self-contained `installer.php` script that rebuilds the site on any empty server without WordPress being pre-installed.

### Why Backupino?

* **100% Free Forever**: Backing up, restoring, and cloning have zero arbitrary file size limits and zero fees. Restoring a site will never be locked behind a paywall.
* **Anti-Timeout Chunked Resumable Engine**: Operations are split into micro-batches with persistent database checkpoints. If your server hits a 30-second timeout, it automatically resumes exactly from the last processed file.
* **Standalone Installer (`installer.php`)**: Rebuilds your website on fresh, empty hosting in 5 guided steps.
* **Pure PHP SQL Streamer**: Dumps databases directly through native PDO/mysqli APIs. No `mysqldump`, `exec()`, or shell permissions required.
* **Permanent Latest-Backup Link**: A permanent, secure download URL that always resolves to your newest backup, guarded by administrator authentication.
* **Telegram & Bale Messenger Alerts**: Instant dispatch of backup completion reports and direct download links to Telegram and Bale.
* **Jalali (Solar Hijri) Calendar Support**: Native support for Persian calendar scheduling and formatting alongside Gregorian.
* **100% Offline-First**: Zero external CDNs, zero remote font calls, zero telemetry tracking. Fully compliant with intranet and national networks.

---

<div dir="rtl" align="right">

## 🌟 معرفی فارسی

**بکاپینو (Backupino)** افزونه نسل جدید برای پشتیبان‌گیری، شبیه‌سازی و انتقال بی‌دردسر وب‌سایت‌های وردپرسی است. این افزونه با معماری قطعه‌بندی‌شده ضد تایم‌اوت، نصاب مستقل خودکفا (`installer.php`) و استخراج خالص SQL طراحی شده تا در محدودترین هاست‌های اشتراکی نیز بدون افت کیفیت یا قطعی فرآیند عمل کند.

### مزایای کلیدی:
* **۱۰۰٪ رایگان برای همیشه:** ساخت نسخه پشتیبان و بازگردانی سایت تا ابد و بدون هیچ سقف حجمی رایگان است.
* **نصاب مستقل خودکفا:** نیازی به نصب وردپرس در هاست مقصد نیست؛ فایل سبک `installer.php` همه چیز را روی هاست خام بازسازی می‌کند.
* **موتور ضد تایم‌اوت:** پردازش در قطعات چند مگابایتی با ثبت چک‌پوینت؛ در صورت قطعی دقیقاً از همان ثانیه ادامه می‌یابد.
* **لینک دائمی دانلود:** آدرس ثابت برای دریافت جدیدترین نسخه با احراز هویت امن مدیرکل.
* **اعلان در بله و تلگرام:** گزارش لحظه‌ای اتمام بکاپ و مهاجرت با پیام‌رسان‌های محبوب و لینک‌های کلیک‌پذیر.
* **تقویم خورشیدی:** ثبت تاریخ‌ها و زمانبندی خودکار ماهانه در اول هر ماه شمسی.
* **معماری ۱۰۰٪ آفلاین-فرست:** بدون فراخوانی هیچ CDN خارجی، کاملاً امن و مستقل.

</div>

---

## 📚 Documentation

Comprehensive guides, step-by-step tutorials, troubleshooting playbooks, and comparisons are available in our official documentation repository:

👉 **[Backupino Documentation Portal (مستندات رسمی)](https://github.com/Backupino/documents)**

* [Getting Started & Installation (راهنمای شروع و نصب)](https://github.com/Backupino/documents/tree/main/01-getting-started)
* [User Guide & Migration Scenarios (راهنمای کاربری و مهاجرت)](https://github.com/Backupino/documents/tree/main/02-user-guide)
* [Telegram & Bale Bot Integrations (اتصال ربات‌ها)](https://github.com/Backupino/documents/tree/main/03-integrations)
* [Feature Matrix & Comparisons (مقایسه‌ها با رقبا)](https://github.com/Backupino/documents/tree/main/04-comparisons)
* [Troubleshooting & Host Playbooks (عیب‌یابی و رفع خطاها)](https://github.com/Backupino/documents/tree/main/05-troubleshooting)
* [Frequently Asked Questions (پرسش‌های متداول)](https://github.com/Backupino/documents/tree/main/06-faq)

---

## 🚀 Quick Start

### Installation
1. Download `backupino.zip` from [Releases](https://github.com/Backupino/plugin/releases).
2. Go to **Plugins → Add New → Upload Plugin** in your WordPress dashboard.
3. Choose the zip file and click **Install Now**, then **Activate**.
4. Navigate to the **Backupino** menu in the sidebar and click **Create Backup**.

### WP-CLI Support
```bash
# Create a full backup
wp backupino create

# Create a database-only backup
wp backupino create --db-only

# Check running status
wp backupino status

# List recent backups
wp backupino jobs
```

---

## 📄 License

Backupino is open-source software licensed under the [GNU General Public License v2.0 or later](https://www.gnu.org/licenses/gpl-2.0.html).

---

<div align="center">

Crafted with care by the [Backupino Team](https://backupino.com) • [Report Issues](https://github.com/Backupino/plugin/issues)

</div>
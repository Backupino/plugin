<div align="center">

# 📦 Backupino
### Fast, Resumable WordPress Backup, Cloning & Easy Migration
**A next-generation WordPress migration and disaster recovery platform with a self-contained offline installer**

[![Version](https://img.shields.io/badge/version-1.0.2--stable-blue.svg?style=for-the-badge)](https://github.com/Backupino/plugin)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%20--%207.1-21759b.svg?style=for-the-badge&logo=wordpress)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%20--%208.4-777bb4.svg?style=for-the-badge&logo=php)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg?style=for-the-badge)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Plugin Check](https://img.shields.io/badge/Plugin%20Check-100%25%20Passed-success.svg?style=for-the-badge)](https://wordpress.org)
[![Offline First](https://img.shields.io/badge/Architecture-100%25%20Offline--First-orange.svg?style=for-the-badge)](#100-offline-first-architecture)

[Documentation Portal](https://github.com/Backupino/documents) • [Source Repository](https://github.com/Backupino/plugin) • [WordPress Profile](https://profiles.wordpress.org/backupino/)

**Languages**: **English (Default)** | [راهنمای فارسی (Persian)](README.fa.md)

</div>

---

## 🌟 Overview

**Backupino** is an enterprise-grade WordPress backup, cloning, and migration plugin engineered specifically for real-world hosting environments with strict execution limits, memory constraints, and short timeouts.

It bundles your entire WordPress installation — database, plugins, themes, uploads, and core files — into a single secure archive accompanied by a standalone, lightweight `installer.php` wizard. You can restore or migrate your entire website onto any fresh, empty server in 7 intuitive steps without needing WordPress pre-installed.

---

### Why Backupino?

* **100% Free Forever**: Backing up, restoring, and cloning have zero arbitrary file size limits and zero fees. Restoring a site will never be locked behind a paywall.
* **Anti-Timeout Chunked Resumable Engine**: Operations are split into micro-batches with persistent database checkpoints. If your server hits a 30-second FastCGI timeout, it automatically resumes exactly from the last processed file.
* **Standalone Installer (`installer.php`)**: Rebuilds your website on fresh, empty hosting in 7 guided steps without WordPress installed.
* **Pure PHP SQL Streamer**: Dumps databases directly through native PDO and MySQLi APIs. No `mysqldump`, `exec()`, or shell permissions required.
* **Permanent Latest-Backup Link**: A secure, non-expiring download URL that always resolves to your newest backup, guarded by administrator authentication or a 32-byte secret key.
* **Telegram & Bale Messenger Alerts**: Instant dispatch of backup completion reports and direct download links to Telegram and Bale with an automatic 3-attempt retry queue.
* **Jalali (Solar Hijri) Calendar Support**: Native support for Persian calendar scheduling and formatting alongside Gregorian.
* **100% Offline-First Architecture**: Zero external CDNs, zero remote font calls, zero telemetry tracking. Fully compliant with intranet and isolated networks.

---

## 📚 Documentation

Comprehensive guides, step-by-step tutorials, troubleshooting playbooks, and comparisons are available in our official documentation repository:

👉 **[Backupino Documentation Portal (English)](https://github.com/Backupino/documents)** | **[مستندات فارسی](https://github.com/Backupino/documents/tree/main/fa)**

* [Getting Started & Installation](https://github.com/Backupino/documents/tree/main/en/01-getting-started)
* [User Guide & Migration Playbook](https://github.com/Backupino/documents/tree/main/en/02-user-guide)
* [Telegram & Bale Bot Integrations](https://github.com/Backupino/documents/tree/main/en/03-integrations)
* [Feature Matrix & Competitor Benchmarks](https://github.com/Backupino/documents/tree/main/en/04-comparisons)
* [Troubleshooting & Hosting Playbooks](https://github.com/Backupino/documents/tree/main/en/05-troubleshooting)
* [Frequently Asked Questions (FAQ)](https://github.com/Backupino/documents/tree/main/en/06-faq)

---

## 🚀 Quick Start

### Installation via WordPress Dashboard
1. Download `backupino.zip` from [Releases](https://github.com/Backupino/plugin/releases).
2. In your WordPress dashboard, navigate to **Plugins → Add New → Upload Plugin**.
3. Choose the zip file, click **Install Now**, and then **Activate**.
4. Open the **Backupino** menu in the sidebar and click **Create Backup**.

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

Crafted with care by the [Backupino Team](https://profiles.wordpress.org/backupino/) • [Report Issues](https://github.com/Backupino/plugin/issues)

</div>

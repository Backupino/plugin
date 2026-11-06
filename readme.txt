=== Backupino ===
Contributors: backupino
Tags: backup, restore, migration, clone, jalali
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fast, resumable WordPress backups, cloning and migration with a self-contained installer.

== Description ==

Backupino packs your entire WordPress site — files and database — into a single archive plus a self-contained `installer.php` script that rebuilds the site on an empty server without WordPress being pre-installed.

Designed specifically for real shared hosting environments with strict execution limits, Backupino ensures that your backup and migration operations run smoothly without timeouts or memory crashes.

= Key Features =

* **Resumable Chunk-Based Engine**: Backups are processed in small, restartable slices (scan, files, database, finalize). If execution hits a timeout or server hiccup, it automatically resumes from the last successful checkpoint.
* **Pure SQL Database Dumper**: Dumps tables with keyset pagination directly through WordPress database APIs. No `mysqldump`, `exec()`, or shell access required.
* **Standalone Installer (`installer.php`)**: Ultra-lightweight (~72 KB) installer generated dynamically. Extracts archives, streams SQL dumps, replaces URLs, and builds a fresh `wp-config.php` with unique security salts.
* **Deep URL Search & Replace**: Smart migration engine that accurately deserializes PHP structures (including nested arrays and objects) and rewrites JSON-encoded strings without corruption.
* **Permanent Latest-Backup Link**: A secure, non-expiring URL that points directly to the newest backup. Protected by WordPress administrator authentication (`manage_options`) or a dedicated secret access key (`?backupino_key=...`).
* **Telegram & Bale Notifications**: Instant alerts sent to your personal bot upon backup completion. Includes backup scope, formatted size, completion date, and direct download link. Equipped with an automatic 3-attempt retry queue.
* **Automated Monthly Backup**: Built-in scheduling triggered on the first day of each month. Native Jalali calendar calculation for Persian sites (`fa_IR`) and Gregorian for other locales.
* **Automatic Retention Management**: Retains the latest backup packages (default: 3) and automatically prunes older archives to conserve disk space.
* **100% Offline-First Architecture**: Zero external scripts, zero CDNs, and zero third-party fonts. All assets are served locally from your server.
* **Latin Digit Normalization**: All numbers in timestamps, sizes, and interfaces use standard Latin digits (`0-9`) across all languages.
* **WP-CLI Support**: Full command-line interface for sysadmins (`wp backupino create`, `wp backupino jobs`, `wp backupino status`).

= Free vs Pro =

The **free plugin** provides complete backup, migration, and clone capabilities with no arbitrary file size or database restrictions. Restoring or migrating an existing backup is 100% free forever.

The optional **Pro add-on** (distributed separately) adds advanced enterprise features such as cloud storage destinations (S3, Google Drive, ArvanCloud, etc.), in-place rollback restore, multisite network support, and incremental backups.

== External Services ==

This plugin can optionally connect to external third-party services to deliver backup notification alerts to the site administrator:

1. **Telegram Bot API**
* **Service**: Telegram Messenger (`https://api.telegram.org`)
* **Purpose**: Dispatches backup completion notifications and download links to your personal Telegram chat or channel.
* **Data Transmitted**: Site domain name, backup scope (files/database), formatted archive size, completion timestamp, and permanent download link. On migration restoration, sends new site URL and admin dashboard login URL.
* **When Sent**: Data is transmitted ONLY when the site administrator explicitly enables notifications in Backupino Settings, selects "Telegram", and inputs their personal bot token and chat ID (or when triggering a test alert or completing restoration in the standalone installer). If notification service is set to "None" (default), no requests are sent.
* **Terms of Service**: [Telegram Terms of Service](https://telegram.org/tos)
* **Privacy Policy**: [Telegram Privacy Policy](https://telegram.org/privacy)

2. **Bale Bot API**
* **Service**: Bale Messenger (`https://tapi.bale.ai`)
* **Purpose**: Dispatches backup completion notifications and download links to your personal Bale chat or channel.
* **Data Transmitted**: Site domain name, backup scope (files/database), formatted archive size, completion timestamp, and permanent download link. On migration restoration, sends new site URL and admin dashboard login URL.
* **When Sent**: Data is transmitted ONLY when the site administrator explicitly enables notifications in Backupino Settings, selects "Bale", and inputs their personal bot token and chat ID (or when triggering a test alert or completing restoration in the standalone installer). If notification service is set to "None" (default), no requests are sent.
* **Terms of Service**: [Bale Terms of Service](https://bale.ai/terms)
* **Privacy Policy**: [Bale Privacy Policy](https://bale.ai/terms)

== Installation ==

= From WordPress Admin =
1. Navigate to **Plugins** -> **Add New** in your WordPress dashboard.
2. Search for `Backupino`.
3. Click **Install Now**, then **Activate**.
4. Open the **Backupino** menu and click **Create Backup**.

= Manual Installation =
1. Download the plugin archive and extract it.
2. Upload the `backupino/` folder to your `/wp-content/plugins/` directory.
3. Activate Backupino from the **Plugins** screen in WordPress.

== Frequently Asked Questions ==

= Does the target server require WordPress to be installed for restore? =
No. Upload your backup archive along with `installer.php` into an empty web directory and open `installer.php` in your browser. The installer unpacks files, imports your database, and sets up WordPress cleanly.

= Where are backup packages stored? =
Packages are stored locally in `/wp-content/backupino/packages/` (outside of the public uploads directory for enhanced security and isolation). The directory is secured with `.htaccess`, `web.config`, and empty `index.php` guards to prevent direct public file browsing and execution.

= Does the permanent download link ever expire? =
No. The permanent link (`?backupino_download=latest`) never expires and always resolves to the most recent backup archive. For security, requests are protected by WordPress administrator authentication (`manage_options` capability) or by providing the secret download access key generated in settings (`?backupino_key=...`).

= Is there any file size or database limit in the free version? =
No. There are no artificial limits on package size or database rows. Backupino is designed to handle sites of any size through chunked, resumable processing.

= What happens if a backup process is interrupted? =
Backupino tracks its state per slice in the database. If a backup process is interrupted by a server timeout or network drop, opening the dashboard or triggering the next cron tick immediately resumes the process from the exact point of interruption.

= Does Backupino support the Persian / Jalali calendar? =
Yes. If your WordPress site language is set to Persian (`fa_IR`), Backupino automatically formats dates using the Jalali calendar and schedules monthly backups on the 1st day of the solar month. All numbers strictly use Latin digits (`0-9`).

= Can I run backups from the command line? =
Yes. Backupino includes built-in WP-CLI commands:
* `wp backupino create` — start a new backup job.
* `wp backupino jobs` — view active and historical jobs.
* `wp backupino status` — inspect the current running job.

= Are third-party notifications required? =
No. Notifications are completely optional and disabled by default. If you do not configure Telegram or Bale credentials, Backupino operates strictly locally without sending any outbound external requests.

== Upgrade Notice ==

= 1.0.3 =
Security, compliance, and bug-fix release addressing official WordPress.org review requirements, externalizing assets, redacting database credentials, and fixing installer form submission.

= 1.0.2 =
Feature and consistency update. Adds design token hooks for download page theming, unifies installer locale fallbacks, and standardizes file line endings.

= 1.0.1 =
Security and stability maintenance release. Adds wp-content execution protection for standalone installer, cryptographically random package name suffixes for Nginx safety, defensive directory handling in JobRunner, and proper job type option handling.

= 1.0.0 =
Initial production release of Backupino. Includes resumable backups, standalone installer, permanent login-gated download link, Telegram/Bale notifications, monthly scheduling with Jalali support, and 100% offline-first architecture.

== Changelog ==

= 1.0.3 =
* Security: Implemented `ConfigWriter::redact_secrets()` to strip credentials and salts from `wp-config.php` during package build, injecting clean `backupino-wp-config.tpl` template and generating fresh cryptographic salts on restore.
* Security: Replaced persistent physical installer PHP files in uploads storage with dynamic streaming download via `Fs::stream_string_download()`.
* Compliance: Streamlined default settings options and unified scheduling to standard monthly execution with extensible storage driver filters.
* Compliance: Externalized all inline CSS and JS assets into dedicated static cacheable files for both the public download landing page and the standalone installer.
* Compliance: Removed bundled `.po`/`.mo` translation files to rely exclusively on WordPress.org official language packs (`translate.wordpress.org`).
* Hardening: Added cryptographic `hash_equals()` download token comparison, strict table prefix regex validation (`/^[a-zA-Z0-9_]+$/`), URL validation, and eliminated internal path disclosures.
* Hardening: Standardized all text files to Unix line endings (`LF`) with `.gitattributes` to eliminate `Internal.LineEndings.Mixed` warnings.
* Fix: Fixed undefined variable `$backupino_settings` in `templates/settings.php` causing a fatal error on the options page.
* Fix: Prevented native form GET submission in Step 2 of the standalone installer and resolved button ID mismatch (`back-to-step1`) to ensure uninterrupted progression to extraction.

= 1.0.2 =
* Feature: Added `backupino_design_tokens` and `backupino_download_page_styles` extension hooks for public download landing page customization.
* Consistency: Unified standalone installer fallback locale to `en_US` across all extraction and notification stages.
* Hardening: Fully normalized line endings to LF across all plugin files to prevent mixed line ending warnings.

= 1.0.1 =
* Security: Standalone installer now rejects direct execution inside `wp-content/` with HTTP 403 Forbidden to protect Nginx servers against unauthorized calls.
* Security: Package filenames now include a 12-character random cryptographic suffix preventing URL enumeration or guessing on Nginx setups.
* Security: Removed static `installer.php` persistence from package storage; installer is safely served dynamically with clean download headers.
* Fix: Replaced invalid `Fs::mkdir()` call with `Fs::ensure_dir()` in JobRunner to eliminate fatal error on clean WordPress environments.
* Fix: Properly release and clean lock file on unexpected stage errors or job failures.
* Fix: Background cron scheduler now reliably identifies manual jobs via `$job->option('type')` avoiding concurrent runner collisions.
* Quality: 100% passed all 5 categories of the official WordPress Plugin Check (PCP).

= 1.0.0 =
* Initial public release.
* Resumable backup engine supporting files and pure SQL database dumps.
* Standalone installer (`installer.php`) with deep JSON & serialized search and replace.
* Permanent latest-backup download link with administrator authentication gate.
* Telegram Bot API and Bale Bot API notification channels with automated retry queue.
* Monthly automated backup scheduling (Jalali calendar for `fa_IR`, Gregorian for other locales).
* Retention management system with default 3-package threshold.
* Full internationalization (`backupino.pot` and Persian `fa_IR` catalog) with logical CSS for RTL/LTR.
* Latin digit normalization (`0-9`) across all interfaces and dates.
* Strict security hardening: zero dangerous functions, Zip-Slip prevention, and capability-checked REST API endpoints.
* 100% offline-first architecture with zero external CDN dependencies.
* WP-CLI command integration.

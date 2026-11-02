<div align="center">

# 📦 Backupino (بکاپینو)
### افزونه نسل جدید پشتیبان‌گیری، شبیه‌سازی و انتقال بی‌دردسر وب‌سایت‌های وردپرسی
**Fast, Resumable WordPress Backup, Cloning & Easy Migration**

[![Version](https://img.shields.io/badge/version-1.0.0--stable-blue.svg?style=for-the-badge)](https://github.com/Backupino/plugin)
[![WordPress](https://img.shields.io/badge/WordPress-6.0%20--%207.1-21759b.svg?style=for-the-badge&logo=wordpress)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%20--%208.4-777bb4.svg?style=for-the-badge&logo=php)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg?style=for-the-badge)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Plugin Check](https://img.shields.io/badge/Plugin%20Check-100%25%20Passed-success.svg?style=for-the-badge)](https://wordpress.org)
[![Offline First](https://img.shields.io/badge/Architecture-100%25%20Offline--First-orange.svg?style=for-the-badge)](#معماری-۱۰۰-آفلاین-فرست)

[مخزن مستندات رسمی](https://github.com/Backupino/documents) • [مخزن سورس کد](https://github.com/Backupino/plugin) • [پروفایل وردپرس](https://profiles.wordpress.org/backupino/)

**زبان‌ها**: [English README](README.md) | **فارسی (پیش‌فرض)**

</div>

---

<div dir="rtl" align="right">

## 🌟 معرفی بکاپینو

**بکاپینو (Backupino)** افزونه‌ای مدرن و پیشرفته برای پشتیبان‌گیری، شبیه‌سازی (Clone) و انتقال (Migration) کامل وب‌سایت‌های وردپرسی است که اختصاصاً برای کارکرد پایدار در هاست‌های واقعی و سرورهای اشتراکی با محدودیت‌های سخت‌گیرانه حافظه و زمان اجرا طراحی شده است.

بکاپینو تمام محتوای سایت شما — شامل دیتابیس، افزونه‌ها، پوسته‌ها، فایل‌های چندرسانه‌ای و هسته وردپرس — را در قالب یک بسته زیپ سبک فشرده کرده و همراه با یک فایل نصاب مستقل خودکفا به نام `installer.php` ارائه می‌دهد تا بتوانید سایت خود را روی هر هاست خالی، بدون نیاز به نصب اولیه وردپرس، در چند گام ساده بازسازی کنید.

---

### چرا بکاپینو؟

* **۱۰۰٪ رایگان برای همیشه:** ساخت بکاپ، بازگردانی و انتقال سایت هیچ سقف حجمی ندارد و بازگردانی سایت هرگز پولی نخواهد شد.
* **موتور قطعه‌بندی‌شده ضد تایم‌اوت (Anti-Timeout Resumable Engine):** عملیات در قطعات کوچک با ثبت مداوم وضعیت (Checkpoint) در دیتابیس انجام می‌شود؛ در صورت وقوع تایم‌اوت سرور، فرآیند دقیقاً از آخرین فایل متوقف‌شده ادامه می‌یابد.
* **نصاب مستقل خودکفا (`installer.php`):** بازسازی و مهاجرت کامل سایت روی هاست خام و بدون نیاز به نصب وردپرس در ۵ مرحله راهنما.
* **استخراج خالص دیتابیس بدون وابستگی:** استخراج ساختار و داده‌های SQL بدون نیاز به `mysqldump`، `exec()` یا دستورات شل، با استفاده از APIهای بومی دیتابیس.
* **لینک دائمی هوشمند:** آدرسی امن و پایدار برای دسترسی و دانلود آخرین نسخه پشتیبان محافظت‌شده با دسترسی مدیرکل یا کلید اختصاصی.
* **اعلان لحظه‌ای در پیام‌رسان‌ها:** ارسال گزارش اتمام بکاپ و لینک دانلود مستقیم به ربات شخصی تلگرام یا بله همراه با صف تلاش مجدد خودکار ۳ مرحله‌ای.
* **پشتیبانی کامل از تقویم خورشیدی (جلالی):** زمانبندی و فرمت‌بندی بومی تاریخ‌ها بر اساس تقویم شمسی در سایت‌های فارسی‌زبان.
* **معماری ۱۰۰٪ آفلاین-فرست:** بدون فراخوانی هیچ منبع خارجی، CDN، فونت‌های ریموت یا سیستم‌های تله‌متری؛ کاملاً سازگار با اینترانت و شبکه ملی.

---

## 📚 مستندات و راهنماهای جامع

پایگاه دانش کامل، آموزش‌های گام‌به‌گام و راهنماهای عیب‌یابی در مخزن رسمی مستندات در دسترس است:

👉 **[پرتال رسمی مستندات بکاپینو (Backupino Documents)](https://github.com/Backupino/documents)**

* [راهنمای شروع و نصب افزونه](https://github.com/Backupino/documents/tree/main/fa/01-getting-started)
* [راهنمای جامع کاربری و سناریوهای مهاجرت](https://github.com/Backupino/documents/tree/main/fa/02-user-guide)
* [راهنمای اتصال ربات‌های تلگرام و بله](https://github.com/Backupino/documents/tree/main/fa/03-integrations)
* [جدول مقایسه فنی با سایر افزونه‌ها](https://github.com/Backupino/documents/tree/main/fa/04-comparisons)
* [پلی‌بوک عیب‌یابی و رفع خطاهای هاستینگ](https://github.com/Backupino/documents/tree/main/fa/05-troubleshooting)
* [پرسش‌های متداول (FAQ)](https://github.com/Backupino/documents/tree/main/fa/06-faq)

---

## 🚀 شروع سریع

### نصب از طریق پیشخوان وردپرس
1. فایل `backupino.zip` را از بخش [Releases](https://github.com/Backupino/plugin/releases) دریافت کنید.
2. در پیشخوان وردپرس به مسیر **افزونه‌ها ← افزودن افزونه ← بارگذاری افزونه** بروید.
3. فایل را انتخاب کرده و روی **هم‌اکنون نصب کن** و سپس **فعال‌سازی** کلیک کنید.
4. از منوی کناری پیشخوان وارد **Backupino** شده و روی دکمه **ایجاد نسخه پشتیبان** کلیک کنید.

### پشتیبانی از خط فرمان (WP-CLI)
```bash
# ایجاد نسخه پشتیبان کامل
wp backupino create

# ایجاد نسخه پشتیبان فقط از دیتابیس
wp backupino create --db-only

# بررسی وضعیت عملیات در حال اجرا
wp backupino status

# نمایش لیست آخرین نسخه‌های پشتیبان
wp backupino jobs
```

---

## 📄 مجوز و لایسنس

افزونه بکاپینو نرم‌افزار متن‌باز است که تحت مجوز [GNU General Public License v2.0 یا نسخه‌های بالاتر](https://www.gnu.org/licenses/gpl-2.0.html) منتشر شده است.

---

<div align="center">

توسعه‌یافته توسط [تیم بکاپینو](https://profiles.wordpress.org/backupino/) • [ثبت گزارش خطا و باگ](https://github.com/Backupino/plugin/issues)

</div>

</div>

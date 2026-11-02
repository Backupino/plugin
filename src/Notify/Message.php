<?php
/**
 * Notification message.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Notify;

defined( 'ABSPATH' ) || exit;

use Backupino\Support\Bytes;

/**
 * Builds the plain text that is delivered to a messenger.
 */
final class Message {

	/**
	 * Message body.
	 *
	 * @var string
	 */
	private $text;

	/**
	 * Constructor.
	 *
	 * @param string $text Body.
	 */
	public function __construct( string $text ) {
		$this->text = trim( $text );
	}

	/**
	 * Body.
	 *
	 * @return string
	 */
	public function text(): string {
		return $this->text;
	}

	/**
	 * Whether there is anything to send.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return '' === $this->text;
	}

	/**
	 * Default template for backup notification based on site locale.
	 *
	 * @return string
	 */
	public static function default_backup_template(): string {
		$locale = function_exists( 'get_locale' ) ? get_locale() : 'en_US';
		if ( 0 === strpos( $locale, 'fa' ) ) {
			return "📦 پشتیبان‌گیری با موفقیت انجام شد:\n🔹 {site}\n\n   📁 محتویات: {kind}\n   💾 حجم فایل: {size}\n   📅 تاریخ: {date}\n\n🔗 لینک دانلود مستقیم:\n<a href=\"{link}\">دانلود نسخه پشتیبان</a>";
		}

		return "📦 Backup completed successfully:\n🔹 {site}\n\n   📁 Contents: {kind}\n   💾 File size: {size}\n   📅 Date: {date}\n\n🔗 Direct download link:\n<a href=\"{link}\">Download Backup Package</a>";
	}

	/**
	 * Default template for restore notification based on site locale.
	 *
	 * @return string
	 */
	public static function default_restore_template(): string {
		$locale = function_exists( 'get_locale' ) ? get_locale() : 'en_US';
		if ( 0 === strpos( $locale, 'fa' ) ) {
			return "🎉 بازگردانی وب‌سایت با موفقیت انجام شد:\n🔹 {site}\n\n   🌐 آدرس جدید سایت: {url}\n   📅 تاریخ بازگردانی: {date}\n\n🔑 ورود به مدیریت وردپرس:\n<a href=\"{admin_url}\">ورود به پیشخوان وردپرس</a>";
		}

		return "🎉 Site restored successfully:\n🔹 {site}\n\n   🌐 New site URL: {url}\n   📅 Restoration date: {date}\n\n🔑 WordPress Admin login:\n<a href=\"{admin_url}\">Log in to WordPress Admin</a>";
	}

	/**
	 * "Backup finished" message.
	 *
	 * @param string $site           Site name.
	 * @param string $kind           What was included.
	 * @param int    $size           Archive size in bytes.
	 * @param string $when           Human readable timestamp.
	 * @param string $link           Permanent download link or landing link.
	 * @param string $template       Optional custom template.
	 * @param string $installer_link Direct installer download link.
	 * @param string $archive_link   Direct archive download link.
	 * @return self
	 */
	public static function backup_completed( string $site, string $kind, int $size, string $when, string $link, string $template = '', string $installer_link = '', string $archive_link = '' ): self {
		if ( '' === trim( $template ) ) {
			$template = self::default_backup_template();
		}

		if ( '' === $archive_link ) {
			$archive_link = $link;
		}
		if ( '' === $installer_link ) {
			$installer_link = $link;
		}

		$text = str_replace(
			array( '{site}', '{kind}', '{size}', '{date}', '{link}', '{archive_link}', '{installer_link}' ),
			array( $site, $kind, Bytes::format( $size ), $when, $link, $archive_link, $installer_link ),
			$template
		);

		return new self( $text );
	}

	/**
	 * Message used by the "send test" button.
	 *
	 * @param string $site Site name.
	 * @param string $link Permanent download link.
	 * @return self
	 */
	public static function test( string $site, string $link ): self {
		$locale = function_exists( 'get_locale' ) ? get_locale() : 'en_US';
		$escaped_link = htmlspecialchars( $link, ENT_QUOTES, 'UTF-8' );

		if ( 0 === strpos( $locale, 'fa' ) ) {
			$lines = array(
				sprintf( "🔔 پیام آزمایشی Backupino از %s", $site ),
				"✅ سامانه اطلاع‌رسانی متصل است. گزارش‌های پشتیبان‌گیری اینجا ارسال خواهد شد.",
				"",
				'🔗 <a href="' . $escaped_link . '">مشاهده سایت</a>',
			);
		} else {
			$lines = array(
				sprintf( "🔔 Backupino test message from %s", $site ),
				"✅ Notifications are working. Backups will be announced here.",
				"",
				'🔗 <a href="' . $escaped_link . '">Visit site</a>',
			);
		}

		return new self( implode( "\n", $lines ) );
	}
}

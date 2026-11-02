<?php
/**
 * Database schema.
 *
 * @package Backupino
 */

declare( strict_types = 1 );

namespace Backupino\Core;

/**
 * Owns the custom tables used by the plugin.
 */
final class Schema {

	/**
	 * Table name without the site prefix.
	 */
	public const JOBS_TABLE = 'backupino_jobs';

	/**
	 * Schema version, bumped whenever the table definition changes.
	 */
	public const VERSION = '1';

	/**
	 * Option that stores the installed schema version.
	 */
	public const VERSION_OPTION = 'backupino_schema_version';

	/**
	 * Fully qualified jobs table name.
	 *
	 * @param Db $db Database adapter.
	 * @return string
	 */
	public static function jobs_table( Db $db ): string {
		return $db->prefix() . self::JOBS_TABLE;
	}

	/**
	 * `CREATE TABLE` statement for the jobs table.
	 *
	 * @param Db     $db              Database adapter.
	 * @param string $charset_collate Charset/collation clause.
	 * @return string
	 */
	public static function jobs_table_sql( Db $db, string $charset_collate ): string {
		$table = self::jobs_table( $db );

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			token varchar(32) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'queued',
			stage varchar(20) NOT NULL DEFAULT '',
			progress tinyint(3) unsigned NOT NULL DEFAULT 0,
			payload longtext NULL,
			message text NULL,
			created_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
			updated_at datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY token (token),
			KEY status (status)
		) {$charset_collate};";
	}

	/**
	 * Install or upgrade the schema using `dbDelta()`.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$adapter = new WpDbAdapter( $wpdb );

		dbDelta( self::jobs_table_sql( $adapter, $wpdb->get_charset_collate() ) );

		update_option( self::VERSION_OPTION, self::VERSION, false );
	}

	/**
	 * Whether the schema is present and current.
	 *
	 * @return bool
	 */
	public static function is_current(): bool {
		return (string) get_option( self::VERSION_OPTION, '' ) === self::VERSION;
	}
}

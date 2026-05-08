<?php
/**
 * Database table definitions and install/upgrade via dbDelta().
 *
 * @package UnOrder
 */

declare( strict_types=1 );

namespace UnOrder\Database;

/**
 * Installs and migrates the plugin schema. Option {@see self::OPTION_DB_VERSION} tracks applied migrations.
 */
final class Schema {

	/**
	 * Option name: schema version for migrations.
	 *
	 * @var string
	 */
	public const OPTION_DB_VERSION = 'un_order_db_version';

	/**
	 * Suffix of the requests table (after $wpdb->prefix).
	 *
	 * @var string
	 */
	public const TABLE_REQUESTS = 'un_order_requests';

	/**
	 * Current schema version written on install/activate and used by future migrations.
	 *
	 * @var string
	 */
	public const DB_VERSION = '1.1.0';

	/**
	 * Create or update all plugin tables.
	 *
	 * @return void
	 */
	public static function create_tables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name = self::get_requests_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dbDelta() expects raw DDL; no user input in this string.
		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NULL DEFAULT NULL,
			guest_token varchar(64) NULL DEFAULT NULL,
			items longtext NOT NULL,
			reason text NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			submitted_at datetime NOT NULL,
			processed_at datetime NULL DEFAULT NULL,
			decision_note text NULL,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY user_id (user_id),
			KEY guest_token (guest_token),
			KEY status (status)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Apply schema from {@see self::DB_VERSION} when the stored version is lower.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		$current = (string) get_option( self::OPTION_DB_VERSION, '0' );
		if ( version_compare( $current, self::DB_VERSION, '<' ) ) {
			self::create_tables();
			update_option( self::OPTION_DB_VERSION, self::DB_VERSION );
		}
	}

	/**
	 * Full table name for withdrawal requests.
	 *
	 * @return string
	 */
	public static function get_requests_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_REQUESTS;
	}
}

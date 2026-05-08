<?php
/**
 * Plugin activation: database tables and version option.
 *
 * @package UnOrder
 */

declare( strict_types=1 );

namespace UnOrder;

use UnOrder\Database\Schema;

/**
 * Handles install-time tasks.
 */
final class Activator {

	/**
	 * Run on plugin activation.
	 *
	 * @return void
	 */
	public static function activate(): void {
		Schema::create_tables();
		update_option( Schema::OPTION_DB_VERSION, Schema::DB_VERSION );
		// Re-register the My Account `withdrawal` endpoint rewrite rules.
		add_option( 'un_order_needs_flush_rewrite', '1', '', false );
	}
}

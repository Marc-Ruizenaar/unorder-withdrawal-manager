<?php
/**
 * Fired when the plugin is uninstalled (deleted).
 *
 * @package UnOrder
 */

declare( strict_types=1 );

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/*
 * Table name must stay in sync with UnOrder\Database\Schema::TABLE_REQUESTS
 * and $wpdb->prefix.
 */
global $wpdb;

$unordw_table_name = $wpdb->prefix . 'unordw_requests';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Uninstall only: drop this plugin's table; caching does not apply.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $unordw_table_name ) );
delete_option( 'unordw_db_version' );

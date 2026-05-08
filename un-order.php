<?php
/**
 * Plugin Name:       Un Order
 * Plugin URI:        https://github.com/your-org/un-order
 * Description:       EU-compliant order withdrawal for WooCommerce (Dutch Civil Code Article 230oa). Use Un Order Pro for guest lookup, custom withdrawal periods, and licensing.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * WC requires at least: 8.0
 * WC tested up to:  9.0
 * Author:            Un Order
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       un-order
 * Domain Path:       /languages
 *
 * @package UnOrder
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'UN_ORDER_VERSION', '0.1.0' );
define( 'UN_ORDER_PLUGIN_FILE', __FILE__ );
define( 'UN_ORDER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'UN_ORDER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'UN_ORDER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

$un_order_composer = UN_ORDER_PLUGIN_DIR . 'vendor/autoload.php';
if ( is_readable( $un_order_composer ) ) {
	require $un_order_composer;
} else {
	require_once UN_ORDER_PLUGIN_DIR . 'includes/Autoloader.php';
	UnOrder\Autoloader::register( UN_ORDER_PLUGIN_DIR . 'includes' );
}

/**
 * Load text domain, register hooks, and integrate with WooCommerce.
 *
 * Priority 25 so Un Order Pro (plugins_loaded 15) can register license and capability hooks first.
 *
 * @return void
 */
function un_order_bootstrap(): void {
	$plugin = new UnOrder\Plugin();
	$plugin->init();
}

add_action( 'plugins_loaded', 'un_order_bootstrap', 25 );

register_activation_hook( UN_ORDER_PLUGIN_FILE, array( UnOrder\Activator::class, 'activate' ) );

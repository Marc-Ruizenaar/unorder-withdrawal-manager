<?php
/**
 * Plugin Name:       UnOrder Withdrawal Manager
 * Plugin URI:        https://github.com/Marc-Ruizenaar/unorder-withdrawal-manager
 * Description:       EU-compliant order withdrawal for WooCommerce (Dutch Civil Code Article 230oa).
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * WC requires at least: 8.0
 * WC tested up to:  9.0
 * Author:            Marc Ruizenaar
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

define( 'UNORDW_VERSION', '0.1.0' );
define( 'UNORDW_PLUGIN_FILE', __FILE__ );
define( 'UNORDW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'UNORDW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'UNORDW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

$unordw_composer = UNORDW_PLUGIN_DIR . 'vendor/autoload.php';
if ( is_readable( $unordw_composer ) ) {
	require $unordw_composer;
} else {
	require_once UNORDW_PLUGIN_DIR . 'includes/Autoloader.php';
	UnOrder\Autoloader::register( UNORDW_PLUGIN_DIR . 'includes' );
}

/**
 * Load text domain, register hooks, and integrate with WooCommerce.
 *
 * Priority 25 so Un Order Pro (plugins_loaded 15) can register license and capability hooks first.
 *
 * @return void
 */
function unordw_bootstrap(): void {
	$plugin = new UnOrder\Plugin();
	$plugin->init();
}

add_action( 'plugins_loaded', 'unordw_bootstrap', 25 );

register_activation_hook( UNORDW_PLUGIN_FILE, array( UnOrder\Activator::class, 'activate' ) );

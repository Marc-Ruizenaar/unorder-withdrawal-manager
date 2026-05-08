<?php
/**
 * Main plugin bootstrap (Un Order — free core).
 *
 * @package UnOrder
 */

declare( strict_types=1 );

namespace UnOrder;

defined( 'ABSPATH' ) || exit;

use UnOrder\Admin\EuWithdrawalSettingsPage;
use UnOrder\Admin\WithdrawalRequestsPage;
use UnOrder\Ajax\WithdrawalSubmit;
use UnOrder\Email\WithdrawalAdminEmail;
use UnOrder\Email\WithdrawalApprovedEmail;
use UnOrder\Email\WithdrawalReceivedEmail;
use UnOrder\Email\WithdrawalRejectedEmail;
use UnOrder\Frontend\AccountOrdersWithdrawal;
use UnOrder\Frontend\AccountWithdrawalEndpoint;

/**
 * Wires WooCommerce integration and plugin hooks.
 */
final class Plugin {

	/**
	 * Whether WooCommerce is available.
	 *
	 * @var bool
	 */
	private bool $woocommerce_active = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->woocommerce_active = class_exists( 'WooCommerce' );

		add_filter( 'plugin_action_links_' . \UN_ORDER_PLUGIN_BASENAME, array( $this, 'add_plugin_action_links' ), 10, 2 );

		// Register before WC early-return: admin-post.php uses has_action(); missing hook yields HTTP 400 and no approval.
		$withdrawal_admin_list = new WithdrawalRequestsPage();
		$withdrawal_admin_list->register_request_handlers();

		if ( ! $this->woocommerce_active ) {
			add_action( 'admin_notices', array( $this, 'render_woocommerce_required_notice' ) );
			return;
		}

		add_filter( 'woocommerce_get_settings_pages', array( $this, 'register_woocommerce_settings_pages' ) );
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );

		add_filter(
			'pre_update_option_un_order_excluded_categories',
			array( $this, 'preserve_excluded_categories_when_locked' ),
			10,
			2
		);

		$withdraw_features = (bool) apply_filters( 'un_order_withdraw_features_enabled', true );

		if ( ! $withdraw_features ) {
			return;
		}

		add_filter( 'woocommerce_email_classes', array( $this, 'register_woocommerce_email_classes' ) );

		$withdrawal_admin_list->register_woocommerce_screens();

		$account_withdrawal = new AccountOrdersWithdrawal();
		$account_withdrawal->register();

		$withdrawal_endpoint = new AccountWithdrawalEndpoint();
		$withdrawal_endpoint->register();

		$withdrawal_ajax = new WithdrawalSubmit();
		$withdrawal_ajax->register();
	}

	/**
	 * Add “Settings” on the Plugins screen (opens WooCommerce → Settings → Un Order).
	 *
	 * @param array<int|string, string> $links Existing action links.
	 * @param string                    $file  Plugin basename (unused).
	 * @return array<int|string, string>
	 */
	public function add_plugin_action_links( array $links, string $file ): array {
		if ( ! $this->woocommerce_active || ! current_user_can( 'manage_woocommerce' ) ) {
			return $links;
		}

		$url = \admin_url( 'admin.php?page=wc-settings&tab=un_order_eu_withdrawal' );

		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				\esc_url( $url ),
				\esc_html__( 'Settings', 'un-order' )
			)
		);

		return $links;
	}

	/**
	 * Register custom WooCommerce settings pages.
	 *
	 * @param array<int, \WC_Settings_Page> $pages Existing settings page objects.
	 * @return array<int, \WC_Settings_Page>
	 */
	public function register_woocommerce_settings_pages( array $pages ): array {
		$pages[] = new EuWithdrawalSettingsPage();
		return $pages;
	}

	/**
	 * Register custom WooCommerce transactional emails.
	 *
	 * @param array<string, \WC_Email> $emails Keyed by class name.
	 * @return array<string, \WC_Email>
	 */
	public function register_woocommerce_email_classes( array $emails ): array {
		$emails[ WithdrawalReceivedEmail::class ]   = new WithdrawalReceivedEmail();
		$emails[ WithdrawalAdminEmail::class ]       = new WithdrawalAdminEmail();
		$emails[ WithdrawalApprovedEmail::class ]    = new WithdrawalApprovedEmail();
		$emails[ WithdrawalRejectedEmail::class ]     = new WithdrawalRejectedEmail();
		return $emails;
	}

	/**
	 * Mark this plugin as compatible with High-Performance order storage.
	 */
	public function declare_hpos_compatibility(): void {
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			return;
		}
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', UN_ORDER_PLUGIN_FILE, true );
	}

	/**
	 * Show an admin notice when WooCommerce is not active.
	 *
	 * @return void
	 */
	public function render_woocommerce_required_notice(): void {
		if ( ! is_admin() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Un Order requires WooCommerce to be installed and active.', 'un-order' )
		);
	}

	/**
	 * Keeps excluded categories unchanged when free UI locks the field (disabled selects are omitted from POST).
	 *
	 * @param mixed $value     Value WooCommerce intends to save.
	 * @param mixed $old_value Previous value.
	 * @return mixed
	 */
	public function preserve_excluded_categories_when_locked( $value, $old_value ) {
		if ( Capabilities::category_exclusions_supported() ) {
			return $value;
		}
		if ( false !== $old_value ) {
			return $old_value;
		}

		return is_array( $value ) ? $value : array();
	}
}

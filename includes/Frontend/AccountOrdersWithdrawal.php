<?php
/**
 * "Withdraw order" action on My Account > Orders.
 *
 * @package UnOrder
 */

declare( strict_types=1 );

namespace UnOrder\Frontend;

defined( 'ABSPATH' ) || exit;

use UnOrder\Capabilities;
use UnOrder\OrderWithdrawalAccess;
use WC_Order;

/**
 * Adds an eligible "Withdraw order" link to the orders table action column.
 */
final class AccountOrdersWithdrawal {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'add_withdrawal_action' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * @param array<string, array{url: string, name: string, 'aria-label'?: string}> $actions Order actions.
	 * @param mixed $order Order instance.
	 * @return array<string, array{url: string, name: string, 'aria-label'?: string}>
	 */
	public function add_withdrawal_action( array $actions, $order ): array {
		if ( ! $order instanceof WC_Order || ! $this->is_withdrawal_eligible( $order ) ) {
			return $actions;
		}

		$actions['unordw_withdraw'] = array(
			'url'        => $this->get_withdrawal_url( $order ),
			'name'       => __( 'Withdraw order', 'un-order' ),
			'aria-label' => sprintf(
				/* translators: %s: order number */
				__( 'Withdraw order %s', 'un-order' ),
				$order->get_order_number()
			),
		);

		return $actions;
	}

	/**
	 * Enqueue account orders styles only on the My Account > Orders view.
	 *
	 * @return void
	 */
	public function enqueue_styles(): void {
		if ( ! is_account_page() || ! is_wc_endpoint_url( 'orders' ) ) {
			return;
		}

		wp_enqueue_style(
			'un-order-account-orders',
			UNORDW_PLUGIN_URL . 'assets/css/account-orders.css',
			array(),
			UNORDW_VERSION
		);
	}

	/**
	 * Whether the order row should show the withdrawal action.
	 */
	private function is_withdrawal_eligible( WC_Order $order ): bool {
		// Guest toggle: the My Account orders list is only shown to logged-in users but keep
		// the check here as a safety net if the hook fires in an unexpected context.
		if ( ! is_user_logged_in() && ! Capabilities::guest_withdrawals_supported() ) {
			return false;
		}

		return OrderWithdrawalAccess::order_is_withdrawable( $order );
	}

	/**
	 * @return string Absolute URL: my-account/withdrawal/?order_id=…
	 */
	private function get_withdrawal_url( WC_Order $order ): string {
		$base = trailingslashit( wc_get_page_permalink( 'myaccount' ) ) . 'withdrawal/';
		return add_query_arg( 'order_id', (string) $order->get_id(), $base );
	}
}

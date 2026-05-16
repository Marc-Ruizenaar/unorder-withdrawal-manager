<?php
/**
 * My Account > Withdrawal endpoint and form.
 *
 * @package UnOrder
 */

declare( strict_types=1 );

namespace UnOrder\Frontend;

defined( 'ABSPATH' ) || exit;

use UnOrder\Ajax\WithdrawalSubmit;
use UnOrder\Capabilities;
use UnOrder\Database\RequestRepository;
use UnOrder\Frontend\GuestToken;
use UnOrder\OrderWithdrawalAccess;
use WC_Order;

/**
 * Registers the `withdrawal` account endpoint and renders the withdrawal form template.
 */
final class AccountWithdrawalEndpoint {

	/**
	 * My Account endpoint name (key and public URL segment).
	 */
	private const SLUG = 'withdrawal';

	/**
	 * URL suffix for the thank-you view (e.g. /my-account/withdrawal/success/).
	 */
	private const URL_SUCCESS = 'success';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'woocommerce_get_query_vars', array( $this, 'add_query_var' ) );
		add_filter( 'woocommerce_endpoint_' . self::SLUG . '_title', array( $this, 'set_endpoint_title' ), 10, 3 );
		add_action( 'woocommerce_account_' . self::SLUG . '_endpoint', array( $this, 'render_endpoint' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 99 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * @param array<string, string> $query_vars Key = internal name, value = public URL segment.
	 * @return array<string, string>
	 */
	public function add_query_var( array $query_vars ): array {
		$query_vars[ self::SLUG ] = self::SLUG;
		return $query_vars;
	}

	/**
	 * @param string $title    Default (empty) title.
	 * @param string $endpoint Endpoint name.
	 * @param mixed  $action   Sub-action, if any.
	 */
	public function set_endpoint_title( string $title, string $endpoint, $action = '' ): string { // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint
		if ( self::SLUG !== $endpoint ) {
			return $title;
		}
		if ( self::URL_SUCCESS === $this->get_withdrawal_path_var() ) {
			return __( 'Withdrawal request received', 'un-order' );
		}
		return __( 'Withdraw order', 'un-order' );
	}

	/**
	 * Output endpoint content. Path after /withdrawal/ is the endpoint value (e.g. `success`).
	 *
	 * @param mixed $value Endpoint path suffix from rewrite.
	 * @return void
	 */
	public function render_endpoint( $value = '' ): void {
		$sub = (string) $this->get_withdrawal_path_var();
		if ( $sub === self::URL_SUCCESS || ( is_string( $value ) && self::URL_SUCCESS === $value ) ) {
			$order = $this->get_validated_order_for_success();
			if ( is_string( $order ) ) {
				wc_get_template(
					'myaccount/withdrawal-error.php',
					array( 'unordw_message' => $order ),
					'',
					UNORDW_PLUGIN_DIR . 'templates/'
				);
				return;
			}
			/** @var WC_Order $order */
			wc_get_template(
				'myaccount/withdrawal-success.php',
				array( 'order' => $order ),
				'',
				UNORDW_PLUGIN_DIR . 'templates/'
			);
			return;
		}

		$order = $this->get_validated_order();
		if ( is_string( $order ) ) {
			wc_get_template(
				'myaccount/withdrawal-error.php',
				array( 'unordw_message' => $order ),
				'',
				UNORDW_PLUGIN_DIR . 'templates/'
			);
			return;
		}

		/** @var WC_Order $order */
		wc_get_template(
			'myaccount/form-withdrawal.php',
			array(
				'order'              => $order,
				'line_remaining'     => RequestRepository::get_remaining_quantities( $order ),
				'form_select_title'  => (string) get_option(
					'unordw_form_select_title',
					__( 'Select quantities to withdraw', 'un-order' )
				),
				'form_intro'         => wp_kses_post(
					(string) get_option(
						'unordw_form_intro',
						__( 'For each product, choose how many units to withdraw. The maximum is what you still have available after any earlier pending or approved withdrawal for this order. A rejected request does not use up your available quantity. Use 0 if you are not withdrawing a line in this request.', 'un-order' )
					)
				),
				'guest_token'        => $this->get_guest_token_from_request(),
			),
			'',
			UNORDW_PLUGIN_DIR . 'templates/'
		);
	}

	/**
	 * Current `withdrawal` query var (path segment), e.g. `success` or empty on the form URL.
	 */
	private function get_withdrawal_path_var(): string {
		global $wp;
		if ( empty( $wp->query_vars[ self::SLUG ] ) ) {
			return '';
		}
		$v = $wp->query_vars[ self::SLUG ];
		if ( is_array( $v ) || ! is_scalar( $v ) ) {
			return '';
		}
		return trim( (string) $v, " \t\n\r\0\x0B/" );
	}

	/**
	 * One-time flush after activation when the new endpoint is registered.
	 */
	public function maybe_flush_rewrite_rules(): void {
		$v = get_option( 'unordw_needs_flush_rewrite' );
		if ( '1' === (string) $v || 1 === $v || true === $v ) {
			flush_rewrite_rules( false );
			delete_option( 'unordw_needs_flush_rewrite' );
		}
	}

	/**
	 * Validate the order for the withdrawal form, accepting logged-in users or valid guest tokens.
	 *
	 * @return WC_Order|string Order or a translated error message.
	 */
	private function get_validated_order(): WC_Order|string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw = isset( $_GET['order_id'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['order_id'] ) ) : '';
		if ( '' === $raw ) {
			return __( 'No order was specified. Please return to your orders list and try again.', 'un-order' );
		}

		$order_id = absint( $raw );
		if ( $order_id < 1 ) {
			return __( 'The order you selected could not be found.', 'un-order' );
		}

		if ( ! is_user_logged_in() && ! Capabilities::guest_withdrawals_supported() ) {
			return __(
				'Withdrawal is available to signed-in customers only. Sign in with the account used for this order, then open the link from My Account → Orders.',
				'un-order'
			);
		}

		if ( ! is_user_logged_in() ) {
			$token = $this->get_guest_token_from_request();
			if ( '' === $token ) {
				return __( 'You must be signed in to continue.', 'un-order' );
			}
			if ( ! GuestToken::validate( $token, $order_id ) ) {
				return __( 'Your session has expired or the link is invalid. Please start the withdrawal process again.', 'un-order' );
			}
		} else {
			if ( ! current_user_can( 'view_order', $order_id ) ) {
				return __( 'You are not allowed to view this order.', 'un-order' );
			}
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return __( 'The order you selected could not be found.', 'un-order' );
		}

		if ( ! OrderWithdrawalAccess::order_is_withdrawable( $order ) ) {
			return __(
				'This order cannot be withdrawn. It may be outside the withdrawal period, not in a withdrawable status, fully withdrawn already, or excluded by your store settings.',
				'un-order'
			);
		}

		return $order;
	}

	/**
	 * Thank-you view: require a saved request for the order to reduce guessable URLs.
	 * Logged-in customers: {@see current_user_can( 'view_order' )}. Guests: same billing email as
	 * the order must be passed in {@see $_GET['billing_email']} (added to the redirect after submit).
	 *
	 * @return WC_Order|string
	 */
	private function get_validated_order_for_success(): WC_Order|string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw = isset( $_GET['order_id'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['order_id'] ) ) : '';
		if ( '' === $raw ) {
			return __( 'No order was specified.', 'un-order' );
		}

		$order_id = absint( $raw );
		if ( $order_id < 1 ) {
			return __( 'The order you selected could not be found.', 'un-order' );
		}

		if ( ! is_user_logged_in() && ! Capabilities::guest_withdrawals_supported() ) {
			return __(
				'We could not show this confirmation. Sign in with the account used for this order.',
				'un-order'
			);
		}

		if ( is_user_logged_in() && ! current_user_can( 'view_order', $order_id ) ) {
			return __( 'You are not allowed to view this order.', 'un-order' );
		}

		$guest_billing_email = '';
		if ( ! is_user_logged_in() ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$guest_billing_email = isset( $_GET['billing_email'] ) ? sanitize_email( wp_unslash( (string) $_GET['billing_email'] ) ) : '';
			if ( '' === $guest_billing_email || ! is_email( $guest_billing_email ) ) {
				return __(
					'We could not show this confirmation. Open the link from the page you saw right after submitting your withdrawal request.',
					'un-order'
				);
			}
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return __( 'The order you selected could not be found.', 'un-order' );
		}

		if ( ! is_user_logged_in() && strtolower( $guest_billing_email ) !== strtolower( (string) $order->get_billing_email() ) ) {
			return __(
				'We could not show this confirmation. Open the link from the page you saw right after submitting your withdrawal request.',
				'un-order'
			);
		}

		if ( ! RequestRepository::order_has_request( $order_id ) ) {
			return __( 'No withdrawal was found for this order. If you have just submitted a request, please check your order notes.', 'un-order' );
		}

		return $order;
	}

	/**
	 * Read and sanitize the guest token from the request (does not validate it).
	 *
	 * Checks `token` first (used by email-based links) then `guest_token` (used by the
	 * order-lookup shortcode). Both param names are normalised to the same internal value
	 * so the rest of the endpoint is agnostic about which was supplied.
	 */
	private function get_guest_token_from_request(): string {
		return GuestToken::read_from_request();
	}

	/**
	 * Enqueue withdrawal form styles/scripts on the form endpoint; minimal assets on the success sub-route.
	 */
	public function enqueue_assets(): void {
		if ( ! is_account_page() || ! is_wc_endpoint_url( self::SLUG ) ) {
			return;
		}

		$ver = ( defined( 'UNORDW_VERSION' ) && is_string( UNORDW_VERSION ) ) ? UNORDW_VERSION : '0';

		wp_enqueue_style(
			'un-order-withdrawal-form',
			UNORDW_PLUGIN_URL . 'assets/css/withdrawal-form.css',
			array(),
			$ver
		);

		if ( self::URL_SUCCESS === $this->get_withdrawal_path_var() ) {
			return;
		}

		wp_register_script(
			'un-order-withdrawal-form',
			UNORDW_PLUGIN_URL . 'assets/js/withdrawal-form.js',
			array(),
			$ver,
			true
		);
		wp_localize_script(
			'un-order-withdrawal-form',
			'unordwWithdrawal',
			array(
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'ajaxAction'      => WithdrawalSubmit::ACTION,
				'nonce'           => wp_create_nonce( WithdrawalSubmit::ACTION ),
				'selectItemError' => __( 'Set a quantity to withdraw for at least one product.', 'un-order' ),
				'itemQtySymbol'   => _x( '×', 'multiplication sign between product name and quantity (confirm step)', 'un-order' ),
				'noReason'        => __( 'No reason given', 'un-order' ),
				'submitError'     => __( 'Something went wrong. Please try again.', 'un-order' ),
			)
		);
		wp_enqueue_script( 'un-order-withdrawal-form' );
	}
}

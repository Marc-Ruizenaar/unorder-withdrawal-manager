<?php
/**
 * AJAX: customer confirms and submits a withdrawal request (Article 230oa).
 *
 * Physical table: wp_{prefix}un_order_requests (same data the spec sometimes labels eu_withdrawal_requests).
 *
 * @package UnOrder
 */

declare( strict_types=1 );

namespace UnOrder\Ajax;

defined( 'ABSPATH' ) || exit;

use UnOrder\Database\RequestRepository;
use UnOrder\Capabilities;
use UnOrder\Frontend\GuestToken;
use UnOrder\OrderWithdrawalAccess;
use WC_Order;
use WP_Error;

/**
 * Handles `wp_ajax_eu_withdrawal_submit` (authenticated) and
 * `wp_ajax_nopriv_eu_withdrawal_submit` (guest via single-use token).
 */
final class WithdrawalSubmit {

	/**
	 * WordPress nonce action and AJAX `action` parameter value.
	 */
	public const ACTION = 'eu_withdrawal_submit';

	/**
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Validate input, store request, add order note, return JSON.
	 */
	public function handle(): void {
		if ( ! check_ajax_referer( self::ACTION, 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Your session has expired. Please refresh the page and try again.', 'un-order' ) ),
				403
			);
		}

		if ( ! is_user_logged_in() && ! Capabilities::guest_withdrawals_supported() ) {
			wp_send_json_error(
				array( 'message' => __( 'Withdrawal is available to signed-in customers only. Please sign in and try again.', 'un-order' ) ),
				403
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		$order_id = isset( $_POST['order_id'] ) ? absint( wp_unslash( (string) $_POST['order_id'] ) ) : 0;
		if ( $order_id < 1 ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order.', 'un-order' ) ), 400 );
		}

		// Identity check: logged-in user or valid single-use guest token.
		$guest_token_used = null;
		if ( is_user_logged_in() ) {
			if ( ! current_user_can( 'view_order', $order_id ) ) {
				wp_send_json_error( array( 'message' => __( 'You are not allowed to modify this order.', 'un-order' ) ), 403 );
			}
		} else {
			// Accept `token` (email-link flow) or `guest_token` (shortcode-lookup flow).
			// phpcs:disable WordPress.Security.NonceVerification.Missing
			$raw_token = '';
			if ( isset( $_POST['token'] ) ) {
				$raw_token = sanitize_text_field( wp_unslash( (string) $_POST['token'] ) );
			} elseif ( isset( $_POST['guest_token'] ) ) {
				$raw_token = sanitize_text_field( wp_unslash( (string) $_POST['guest_token'] ) );
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing

			if ( '' === $raw_token ) {
				wp_send_json_error( array( 'message' => __( 'You must be signed in to submit a withdrawal.', 'un-order' ) ), 401 );
			}
			if ( ! GuestToken::consume( $raw_token, $order_id ) ) {
				wp_send_json_error(
					array( 'message' => __( 'Your session has expired or the link is invalid. Please start the withdrawal process again.', 'un-order' ) ),
					403
				);
			}
			$guest_token_used = $raw_token;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			wp_send_json_error( array( 'message' => __( 'The order could not be found.', 'un-order' ) ), 404 );
		}

		if ( ! OrderWithdrawalAccess::order_is_withdrawable( $order ) ) {
			wp_send_json_error(
				array(
					'message' => __(
						'This order cannot be withdrawn. It may be outside the withdrawal period, not in a withdrawable status, or have no remaining quantities.',
						'un-order'
					),
				),
				409
			);
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified via check_ajax_referer earlier.
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Qty keys/values sanitized in the foreach below (absint, sanitize_text_field).
		$posted_qty = isset( $_POST['un_order_item_qty'] ) && is_array( $_POST['un_order_item_qty'] )
			? wp_unslash( $_POST['un_order_item_qty'] )
			: array();
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$raw_qty = array();
		foreach ( $posted_qty as $line_key => $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			$line_id = absint( (string) $line_key );
			if ( $line_id < 1 ) {
				continue;
			}
			$raw_qty[ $line_id ] = sanitize_text_field( (string) $value );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$reason = isset( $_POST['un_order_withdrawal_reason'] )
			? sanitize_textarea_field( wp_unslash( (string) $_POST['un_order_withdrawal_reason'] ) )
			: '';

		$normalized = $this->normalize_item_quantities( $order, $raw_qty, RequestRepository::get_committed_quantities_by_line( $order_id ) );
		if ( \is_wp_error( $normalized ) ) {
			/** @var WP_Error $normalized */
			wp_send_json_error( array( 'message' => $normalized->get_error_message() ), 400 );
		}

		/** @var array<int, float> $items_map */
		$items_map = $normalized;
		if ( count( $items_map ) < 1 ) {
			wp_send_json_error( array( 'message' => __( 'Select at least one product quantity to withdraw.', 'un-order' ) ), 400 );
		}

		$reason_for_db = '' !== $reason ? $reason : null;
		$user_id       = is_user_logged_in() ? get_current_user_id() : 0;

		$inserted = RequestRepository::insert_request( $order_id, $user_id, $items_map, $reason_for_db, $guest_token_used );
		if ( $inserted < 1 ) {
			wp_send_json_error( array( 'message' => __( 'We could not save your request. Please try again or contact the store.', 'un-order' ) ), 500 );
		}

		$order->add_order_note( __( 'Withdrawal request submitted by customer', 'un-order' ), false, false );

		// Notify after the request ends so WooCommerce email/mailer state cannot suppress follow-up sends
		// (e.g. second partial withdrawal on the same order).
		$notify_order_id = $order_id;
		$notify_items    = $items_map;
		$notify_reason   = $reason_for_db;
		add_action(
			'shutdown',
			static function () use ( $notify_order_id, $notify_items, $notify_reason ): void {
				// Fires when the withdrawal is saved. Args: order_id, line_id => quantity, reason.
				do_action( 'un_order_withdrawal_submitted', $notify_order_id, $notify_items, $notify_reason );
			},
			5
		);

		$success_base = wc_get_endpoint_url( 'withdrawal', 'success', wc_get_page_permalink( 'myaccount' ) );
		$redirect     = add_query_arg( 'order_id', (string) $order_id, $success_base );
		if ( ! is_user_logged_in() ) {
			$bill = (string) $order->get_billing_email();
			if ( '' !== $bill ) {
				$redirect = add_query_arg( 'billing_email', $bill, $redirect );
			}
		}

		wp_send_json_success( array( 'redirect' => $redirect ) );
	}

	/**
	 * @param array<int|string, mixed>  $raw_qty   Post keys are line item ids.
	 * @param array<int, float>         $committed Per-line quantities already in pending/approved requests.
	 * @return array<int, float>|WP_Error
	 */
	private function normalize_item_quantities( WC_Order $order, array $raw_qty, array $committed ) {
		$out = array();

		foreach ( $raw_qty as $item_id => $value ) {
			$item_id = is_numeric( $item_id ) ? (int) $item_id : 0;
			if ( $item_id < 1 ) {
				continue;
			}

			$item = $order->get_item( $item_id );
			if ( ! $item || ! ( $item instanceof \WC_Order_Item_Product ) ) {
				return new WP_Error( 'un_order_bad_item', __( 'One of the selected products is not part of this order.', 'un-order' ) );
			}

			$line_max = (float) $item->get_quantity();
			$used     = (float) ( $committed[ $item_id ] ?? 0.0 );
			$max      = max( 0.0, $line_max - $used );

			$q_is_whole = abs( $line_max - (float) (int) round( $line_max ) ) < 0.000001;
			if ( $q_is_whole && $line_max >= 0.5 ) {
				$max = (float) max( 0, (int) round( $max ) );
			} else {
				$max = max( 0.0, round( $max, 4 ) );
			}

			$want = (float) wc_format_decimal( (string) ( is_scalar( $value ) ? $value : 0 ) );

			if ( $max < 0.0000001 || $want < 0.0000001 ) {
				continue;
			}

			if ( $q_is_whole && $line_max >= 0.5 ) {
				$want = (float) (int) round( $want );
			} else {
				$want = min( $max, max( 0.0, round( $want, 4 ) ) );
			}

			if ( $want < 0.0000001 ) {
				continue;
			}
			if ( $want - $max > 0.0001 ) {
				return new WP_Error(
					'un_order_qty',
					__( 'You cannot withdraw more than the remaining quantity for each line (your order line minus quantities already in a pending or approved withdrawal).', 'un-order' )
				);
			}

			$out[ $item_id ] = $want;
		}

		return $out;
	}
}

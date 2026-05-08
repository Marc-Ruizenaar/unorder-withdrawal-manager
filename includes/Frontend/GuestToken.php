<?php
/**
 * Single-use order-meta-backed token for guest withdrawal access.
 *
 * Security contract (from core rules):
 *   - 64-char hex string from bin2hex( random_bytes(32) ).
 *   - Stored as `_un_order_guest_token` on the WooCommerce order (HPOS-compatible).
 *   - Single-use: consumed (meta deleted) after a successful form submission.
 *   - No fixed TTL — valid as long as the withdrawal right is open; the endpoint
 *     already enforces the period via order status and business rules.
 *
 * @package UnOrder
 */

declare( strict_types=1 );

namespace UnOrder\Frontend;

use WC_Order;

/**
 * Generates and validates single-use tokens stored in WooCommerce order meta.
 */
final class GuestToken {

	/**
	 * Order meta key used to persist the active token.
	 */
	private const META_KEY = '_un_order_guest_token';

	/**
	 * Return the existing valid token for $order_id, or generate and persist a new one.
	 *
	 * Safe to call multiple times (e.g. once for the HTML email and once for the plain-text
	 * version): the same token is returned until it is consumed.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return string 64-char lowercase hex token.
	 */
	public static function get_or_create( int $order_id ): string {
		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return self::generate( $order_id );
		}

		$existing = (string) $order->get_meta( self::META_KEY, true );
		if ( 64 === strlen( $existing ) && ctype_xdigit( $existing ) ) {
			return $existing;
		}

		return self::generate( $order_id );
	}

	/**
	 * Create a fresh 64-char token, store it on the order, and return it.
	 *
	 * Overwrites any previously stored token for this order.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return string 64-char lowercase hex token.
	 */
	public static function generate( int $order_id ): string {
		$token = bin2hex( random_bytes( 32 ) );
		$order = wc_get_order( $order_id );
		if ( $order instanceof WC_Order ) {
			$order->update_meta_data( self::META_KEY, $token );
			$order->save_meta_data();
		}
		return $token;
	}

	/**
	 * Check whether $token is valid for $order_id without consuming it.
	 *
	 * @param string $token    64-char hex candidate.
	 * @param int    $order_id WooCommerce order ID.
	 * @return bool
	 */
	public static function validate( string $token, int $order_id ): bool {
		if ( 64 !== strlen( $token ) || ! ctype_xdigit( $token ) ) {
			return false;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		$stored = (string) $order->get_meta( self::META_KEY, true );
		if ( '' === $stored ) {
			return false;
		}

		return hash_equals( $stored, $token );
	}

	/**
	 * Validate and delete the token in one step (single-use enforcement).
	 *
	 * @param string $token    64-char hex candidate.
	 * @param int    $order_id WooCommerce order ID.
	 * @return bool True if the token was valid and has now been consumed.
	 */
	/**
	 * Read guest access token from the current request (email links use `token`, lookup flow uses `guest_token`).
	 *
	 * @return string Non-empty hex token or empty string.
	 */
	public static function read_from_request(): string {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['token'] ) ) {
			return sanitize_text_field( wp_unslash( (string) $_GET['token'] ) );
		}
		if ( isset( $_GET['guest_token'] ) ) {
			return sanitize_text_field( wp_unslash( (string) $_GET['guest_token'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return '';
	}

	public static function consume( string $token, int $order_id ): bool {
		if ( ! self::validate( $token, $order_id ) ) {
			return false;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		$order->delete_meta_data( self::META_KEY );
		$order->save_meta_data();
		return true;
	}
}

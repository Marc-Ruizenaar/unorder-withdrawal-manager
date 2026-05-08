<?php
/**
 * Whether an order may be withdrawn (status, time window, categories, remaining quantity).
 *
 * @package UnOrder
 */

declare( strict_types=1 );

namespace UnOrder;

use UnOrder\Database\RequestRepository;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

/**
 * Shared rules for showing and completing withdrawal flows.
 */
final class OrderWithdrawalAccess {

	/**
	 * Product and time rules only (no guest / login checks).
	 */
	public static function order_is_withdrawable( WC_Order $order ): bool {
		if ( ! in_array( $order->get_status(), array( 'processing', 'completed' ), true ) ) {
			return false;
		}

		$created = $order->get_date_created();
		if ( null === $created ) {
			return false;
		}

		$period_days = Capabilities::withdrawal_period_days();
		$tz          = wp_timezone();
		$now         = new \DateTimeImmutable( 'now', $tz );
		$cutoff      = $now->modify( '-' . $period_days . ' days' );
		if ( $created->getTimestamp() < $cutoff->getTimestamp() ) {
			return false;
		}

		if ( Capabilities::category_exclusions_supported() ) {
			$excluded = array_filter( array_map( 'absint', (array) get_option( 'un_order_excluded_categories', array() ) ) );
			if ( ! empty( $excluded ) && self::all_items_in_excluded_categories( $order, $excluded ) ) {
				return false;
			}
		}

		return RequestRepository::order_has_any_withdrawable_quantity( $order );
	}

	/**
	 * @param array<int, int> $excluded_ids
	 */
	private static function all_items_in_excluded_categories( WC_Order $order, array $excluded_ids ): bool {
		$items = $order->get_items( 'line_item' );
		if ( empty( $items ) ) {
			return false;
		}

		foreach ( $items as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}

			$product = $item->get_product();
			if ( ! $product instanceof WC_Product ) {
				return false;
			}

			$product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
			$terms      = get_the_terms( $product_id, 'product_cat' );

			if ( ! is_array( $terms ) ) {
				return false;
			}

			$term_ids = wp_list_pluck( $terms, 'term_id' );
			if ( empty( array_intersect( $term_ids, $excluded_ids ) ) ) {
				return false;
			}
		}

		return true;
	}
}

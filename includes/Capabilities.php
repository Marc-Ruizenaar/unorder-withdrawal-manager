<?php
/**
 * Free vs Pro feature boundaries. Un Order Pro hooks the filters below.
 *
 * @package UnOrder
 */

declare( strict_types=1 );

namespace UnOrder;

/**
 * Effective capabilities for withdrawal rules.
 */
final class Capabilities {

	/**
	 * Withdrawal window in days (free core: always 14 unless Pro filters).
	 */
	public static function withdrawal_period_days(): int {
		$days = (int) apply_filters( 'un_order_withdrawal_period_days', 14 );
		return max( 1, $days < 1 ? 14 : $days );
	}

	/**
	 * Guest / token flows (free core: off unless Pro enables).
	 */
	public static function guest_withdrawals_supported(): bool {
		return (bool) apply_filters( 'un_order_guest_withdrawals_supported', false );
	}

	/**
	 * Category-based withdrawal exclusions (free core: off unless Pro enables).
	 */
	public static function category_exclusions_supported(): bool {
		return (bool) apply_filters( 'un_order_supports_category_exclusions', false );
	}
}

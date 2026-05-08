<?php
/**
 * Shared data for withdrawal notification emails.
 *
 * @package UnOrder
 */

declare( strict_types=1 );

namespace UnOrder\Email\Helpers;

use UnOrder\Capabilities;
use UnOrder\Email\WithdrawalReceivedEmail;
use WC_Order;
use WC_Order_Item_Product;

/**
 * Builds template arguments and return-period text.
 */
final class WithdrawalEmailData {

	/**
	 * @return array<string, mixed>
	 */
	public static function get_customer_template_args( WithdrawalReceivedEmail $email ): array {
		/** @var WC_Order $order */
		$order = $email->object;

		return array(
			'order'              => $order,
			'email_heading'      => $email->get_heading(),
			'additional_content' => $email->get_additional_content(),
			'withdrawal_items'   => $email->withdrawal_items,
			'withdrawal_reason'  => $email->withdrawal_reason,
			'return_period_days' => self::get_return_period_days(),
			'withdrawal_lines'   => self::format_lines( $order, $email->withdrawal_items ),
			'sent_to_admin'      => false,
			'plain_text'         => false,
			'email'              => $email,
		);
	}

	/**
	 * Configured withdrawal period in days.
	 */
	public static function get_return_period_days(): int {
		return Capabilities::withdrawal_period_days();
	}

	/**
	 * Human-readable line descriptions for the withdrawal.
	 *
	 * @param array<int, float> $items_map
	 * @return list<array{ title: string, quantity: string }>
	 */
	public static function format_lines( WC_Order $order, array $items_map ): array {
		$out = array();
		foreach ( $items_map as $line_id => $qty ) {
			$item = $order->get_item( (int) $line_id );
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$title = $item->get_name();
			$out[] = array(
				'title'    => is_string( $title ) ? $title : (string) $line_id,
				'quantity' => wc_format_decimal( (float) $qty, false ),
			);
		}
		return $out;
	}
}

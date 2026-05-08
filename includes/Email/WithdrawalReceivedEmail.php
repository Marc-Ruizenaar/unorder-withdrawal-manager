<?php
/**
 * Customer email: withdrawal request received.
 *
 * @package UnOrder
 */

declare( strict_types=1 );

namespace UnOrder\Email;

defined( 'ABSPATH' ) || exit;

use UnOrder\Email\Helpers\WithdrawalEmailData;
use WC_Email;
use WC_Order;
use WC_Order_Item_Product;

/**
 * Sent to the customer when a withdrawal is submitted.
 */
final class WithdrawalReceivedEmail extends WC_Email {

	/**
	 * Line item id => quantity being withdrawn.
	 *
	 * @var array<int, float>
	 */
	public $withdrawal_items = array();

	/**
	 * Optional customer reason.
	 *
	 * @var string
	 */
	public $withdrawal_reason = '';

	/**
	 * Constructor: id, templates, and trigger.
	 */
	public function __construct() {
		$this->id             = 'eu_withdrawal_received';
		$this->customer_email = true;
		$this->title          = __( 'Withdrawal request received', 'un-order' );
		$this->description    = __( 'Sent to the customer when they submit a withdrawal request for an order.', 'un-order' );
		$this->email_group    = 'orders';
		$this->placeholders   = array(
			'{order_id}'     => '',
			'{order_date}'   => '',
			'{order_number}' => '',
		);

		$this->template_html  = 'emails/eu-withdrawal-received.php';
		$this->template_plain = 'emails/plain/eu-withdrawal-received.php';
		$this->template_base  = UN_ORDER_PLUGIN_DIR . 'templates/';

		add_action( 'un_order_withdrawal_submitted', array( $this, 'trigger' ), 10, 3 );

		parent::__construct();

		// Always use plugin HTML/plain templates with withdrawal line items. The block editor “general”
		// wrapper does not include this data and can yield incomplete or duplicate-suppressed sends.
		$this->block_email_editor_enabled = false;
	}

	/**
	 * Default subject.
	 */
	public function get_default_subject(): string {
		return __( 'We received your withdrawal request — Order #{order_id}', 'un-order' );
	}

	/**
	 * Default heading.
	 */
	public function get_default_heading(): string {
		return __( 'We received your withdrawal request', 'un-order' );
	}

	/**
	 * Default text below the main content.
	 */
	public function get_default_additional_content(): string {
		return __( 'If you have questions, reply to this email or contact the store.', 'un-order' );
	}

	/**
	 * Fires when a withdrawal is stored.
	 *
	 * @param int               $order_id     Order id.
	 * @param array<int, float> $items_map    Line id => quantity.
	 * @param string|null       $reason       Optional reason.
	 */
	public function trigger( $order_id, $items_map = array(), $reason = null ): void {
		$this->setup_locale();

		$order_id = absint( (string) $order_id );
		$order    = $order_id > 0 ? wc_get_order( $order_id ) : false;

		if ( ! $order instanceof WC_Order ) {
			$this->restore_locale();
			return;
		}

		/** @var array<int, float> $map */
		$map = is_array( $items_map ) ? $this->sanitize_withdrawal_map( $order, $items_map ) : array();
		if ( count( $map ) < 1 ) {
			$this->restore_locale();
			return;
		}

		$this->object            = $order;
		$this->withdrawal_items  = $map;
		$this->withdrawal_reason = is_string( $reason ) ? $reason : '';

		$this->placeholders['{order_id}']     = (string) $order->get_id();
		$this->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );
		$this->placeholders['{order_number}'] = $order->get_order_number();

		$this->recipient = $order->get_billing_email();

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	/**
	 * @param array<int|string, float|int|string> $raw Raw map from the action.
	 * @return array<int, float>
	 */
	private function sanitize_withdrawal_map( WC_Order $order, array $raw ): array {
		$out = array();
		foreach ( $raw as $line_id => $qty ) {
			$line_id = absint( (string) $line_id );
			if ( $line_id < 1 ) {
				continue;
			}
			$item = $order->get_item( $line_id );
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$q = (float) wc_format_decimal( is_scalar( $qty ) ? (string) $qty : '0' );
			if ( $q > 0.0000001 ) {
				$out[ $line_id ] = $q;
			}
		}
		return $out;
	}

	/**
	 * HTML body.
	 */
	public function get_content_html(): string {
		return wc_get_template_html(
			$this->template_html,
			WithdrawalEmailData::get_customer_template_args( $this ),
			'',
			$this->template_base
		);
	}

	/**
	 * Plain text body.
	 */
	public function get_content_plain(): string {
		return wc_get_template_html(
			$this->template_plain,
			WithdrawalEmailData::get_customer_template_args( $this ),
			'',
			$this->template_base
		);
	}
}

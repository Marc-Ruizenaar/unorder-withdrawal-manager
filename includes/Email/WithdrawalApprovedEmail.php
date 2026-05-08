<?php
/**
 * Customer email: withdrawal approved.
 *
 * @package UnOrder
 */

declare( strict_types=1 );

namespace UnOrder\Email;

defined( 'ABSPATH' ) || exit;

use UnOrder\Database\RequestRepository;
use UnOrder\Email\Helpers\WithdrawalEmailData;
use WC_Email;
use WC_Order;

/**
 * Sent when the store approves a withdrawal request.
 */
final class WithdrawalApprovedEmail extends WC_Email {

	/**
	 * Line item id => quantity.
	 *
	 * @var array<int, float>
	 */
	public $withdrawal_items = array();

	/**
	 * Request row id (for reference in templates).
	 *
	 * @var int
	 */
	public $request_id = 0;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'eu_withdrawal_approved';
		$this->customer_email = true;
		$this->title          = __( 'Withdrawal approved', 'un-order' );
		$this->description    = __( 'Sent to the customer when a withdrawal request is approved.', 'un-order' );
		$this->email_group    = 'orders';
		$this->placeholders   = array(
			'{order_id}'     => '',
			'{order_number}' => '',
		);

		$this->template_html  = 'emails/eu-withdrawal-approved.php';
		$this->template_plain = 'emails/plain/eu-withdrawal-approved.php';
		$this->template_base  = UN_ORDER_PLUGIN_DIR . 'templates/';

		add_action( 'un_order_withdrawal_approved', array( $this, 'trigger' ), 10, 1 );

		parent::__construct();
	}

	/**
	 * Default subject.
	 */
	public function get_default_subject(): string {
		return __( 'Your withdrawal request was approved — Order #{order_id}', 'un-order' );
	}

	/**
	 * Default heading.
	 */
	public function get_default_heading(): string {
		return __( 'Your withdrawal request was approved', 'un-order' );
	}

	/**
	 * Default text below the main content.
	 */
	public function get_default_additional_content(): string {
		return __( 'If you need help, reply to this email or contact us.', 'un-order' );
	}

	/**
	 * @param int $request_id Request id.
	 */
	public function trigger( $request_id ): void {
		$this->setup_locale();

		$request_id = absint( (string) $request_id );
		$row        = $request_id > 0 ? RequestRepository::get_by_id( $request_id ) : null;
		if ( null === $row || (string) $row['status'] !== RequestRepository::STATUS_APPROVED ) {
			$this->restore_locale();
			return;
		}

		$order_id = isset( $row['order_id'] ) ? absint( (string) $row['order_id'] ) : 0;
		$order    = $order_id > 0 ? wc_get_order( $order_id ) : false;
		if ( ! $order instanceof WC_Order ) {
			$this->restore_locale();
			return;
		}

		$items_raw = isset( $row['items'] ) && is_string( $row['items'] ) ? $row['items'] : '';
		/** @var array<int, float> $map */
		$map = array();
		if ( '' !== $items_raw ) {
			$decoded = json_decode( $items_raw, true );
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $k => $v ) {
					$map[ (int) $k ] = (float) wc_format_decimal( is_scalar( $v ) ? (string) $v : '0' );
				}
			}
		}
		if ( count( $map ) < 1 ) {
			$this->restore_locale();
			return;
		}

		$this->object            = $order;
		$this->request_id        = $request_id;
		$this->withdrawal_items  = $map;
		$this->placeholders['{order_id}']     = (string) $order->get_id();
		$this->placeholders['{order_number}'] = $order->get_order_number();
		$this->recipient = $order->get_billing_email();

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	/**
	 * HTML body.
	 */
	public function get_content_html(): string {
		/** @var WC_Order $order */
		$order = $this->object;
		return wc_get_template_html(
			$this->template_html,
			array(
				'order'              => $order,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'withdrawal_lines'   => WithdrawalEmailData::format_lines( $order, $this->withdrawal_items ),
				'return_period_days'   => WithdrawalEmailData::get_return_period_days(),
				'sent_to_admin'        => false,
				'plain_text'           => false,
				'email'                => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Plain body.
	 */
	public function get_content_plain(): string {
		/** @var WC_Order $order */
		$order = $this->object;
		return wc_get_template_html(
			$this->template_plain,
			array(
				'order'                => $order,
				'email_heading'        => $this->get_heading(),
				'additional_content'   => $this->get_additional_content(),
				'withdrawal_lines'     => WithdrawalEmailData::format_lines( $order, $this->withdrawal_items ),
				'return_period_days'   => WithdrawalEmailData::get_return_period_days(),
				'sent_to_admin'        => false,
				'plain_text'           => true,
				'email'                => $this,
			),
			'',
			$this->template_base
		);
	}
}

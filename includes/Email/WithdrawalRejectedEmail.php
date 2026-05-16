<?php
/**
 * Customer email: withdrawal rejected.
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
 * Sent when the store rejects a withdrawal request.
 */
final class WithdrawalRejectedEmail extends WC_Email {

	/**
	 * Line item id => quantity.
	 *
	 * @var array<int, float>
	 */
	public $withdrawal_items = array();

	/**
	 * Admin explanation for the customer.
	 *
	 * @var string
	 */
	public $rejection_message = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id             = 'unordw_withdrawal_rejected';
		$this->customer_email = true;
		$this->title          = __( 'Withdrawal not approved', 'un-order' );
		$this->description    = __( 'Sent to the customer when a withdrawal request is rejected.', 'un-order' );
		$this->email_group    = 'orders';
		$this->placeholders   = array(
			'{order_id}'     => '',
			'{order_number}' => '',
		);

		$this->template_html  = 'emails/eu-withdrawal-rejected.php';
		$this->template_plain = 'emails/plain/eu-withdrawal-rejected.php';
		$this->template_base  = UNORDW_PLUGIN_DIR . 'templates/';

		add_action( 'unordw_withdrawal_rejected', array( $this, 'trigger' ), 10, 1 );

		parent::__construct();
	}

	/**
	 * Default subject.
	 */
	public function get_default_subject(): string {
		return __( 'Update on your withdrawal request — Order #{order_id}', 'un-order' );
	}

	/**
	 * Default heading.
	 */
	public function get_default_heading(): string {
		return __( 'We could not approve this withdrawal request', 'un-order' );
	}

	/**
	 * Default text below the main content.
	 */
	public function get_default_additional_content(): string {
		return __( 'If you have questions, reply to this email or contact us.', 'un-order' );
	}

	/**
	 * @param int $request_id Request id.
	 */
	public function trigger( $request_id ): void {
		$this->setup_locale();

		$request_id = absint( (string) $request_id );
		$row        = $request_id > 0 ? RequestRepository::get_by_id( $request_id ) : null;
		if ( null === $row || (string) $row['status'] !== RequestRepository::STATUS_REJECTED ) {
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

		$this->object            = $order;
		$this->withdrawal_items  = $map;
		$note                    = isset( $row['decision_note'] ) && is_string( $row['decision_note'] ) ? $row['decision_note'] : '';
		$this->rejection_message = $note;
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
				'rejection_message'  => $this->rejection_message,
				'sent_to_admin'      => false,
				'plain_text'         => false,
				'email'              => $this,
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
				'order'              => $order,
				'email_heading'      => $this->get_heading(),
				'additional_content'   => $this->get_additional_content(),
				'withdrawal_lines'     => WithdrawalEmailData::format_lines( $order, $this->withdrawal_items ),
				'rejection_message'    => $this->rejection_message,
				'sent_to_admin'        => false,
				'plain_text'           => true,
				'email'                => $this,
			),
			'',
			$this->template_base
		);
	}
}

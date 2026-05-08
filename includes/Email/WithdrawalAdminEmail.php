<?php
/**
 * Admin email: new withdrawal request (plain text by default).
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
 * Sent to the store when a customer submits a withdrawal.
 */
final class WithdrawalAdminEmail extends WC_Email {

	/**
	 * Line item id => quantity.
	 *
	 * @var array<int, float>
	 */
	public $withdrawal_items = array();

	/**
	 * @var string
	 */
	public $withdrawal_reason = '';

	/**
	 * Constructor: id, templates, and trigger.
	 */
	public function __construct() {
		$this->id             = 'eu_withdrawal_admin';
		$this->title          = __( 'Withdrawal request (admin)', 'un-order' );
		$this->description    = __( 'Receive a plain-text notification when a customer submits a withdrawal request.', 'un-order' );
		$this->email_group    = 'orders';
		$this->placeholders   = array(
			'{order_id}'     => '',
			'{order_date}'   => '',
			'{order_number}' => '',
		);

		$this->template_html  = 'emails/eu-withdrawal-admin.php';
		$this->template_plain = 'emails/plain/eu-withdrawal-admin.php';
		$this->template_base  = UN_ORDER_PLUGIN_DIR . 'templates/';

		add_action( 'un_order_withdrawal_submitted', array( $this, 'trigger' ), 10, 3 );

		parent::__construct();

		// Prefer the plugin-level admin email setting over the global admin_email.
		$plugin_email        = (string) get_option( 'un_order_admin_email', '' );
		$default_recipient   = '' !== $plugin_email ? $plugin_email : (string) get_option( 'admin_email', '' );
		$this->recipient     = $this->get_option( 'recipient', $default_recipient );

		$this->block_email_editor_enabled = false;
	}

	/**
	 * Default subject.
	 */
	public function get_default_subject(): string {
		return __( '[{site_title}] Withdrawal request for order #{order_id}', 'un-order' );
	}

	/**
	 * Default heading (used if HTML is selected).
	 */
	public function get_default_heading(): string {
		return __( 'New withdrawal request', 'un-order' );
	}

	/**
	 * No extra block by default.
	 */
	public function get_default_additional_content(): string {
		return '';
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

		if ( $this->is_enabled() && $this->get_recipient() ) {
			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

		$this->restore_locale();
	}

	/**
	 * @param array<int|string, float|int|string> $raw Raw map.
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
	 * Admin HTML body (if merchant switches email type to HTML).
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
				'withdrawal_reason'  => $this->withdrawal_reason,
				'return_period_days' => WithdrawalEmailData::get_return_period_days(),
				'sent_to_admin'      => true,
				'plain_text'         => false,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Plain text body.
	 */
	public function get_content_plain(): string {
		/** @var WC_Order $order */
		$order = $this->object;
		return wc_get_template_html(
			$this->template_plain,
			array(
				'order'              => $order,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'withdrawal_lines'   => WithdrawalEmailData::format_lines( $order, $this->withdrawal_items ),
				'withdrawal_reason'  => $this->withdrawal_reason,
				'return_period_days' => WithdrawalEmailData::get_return_period_days(),
				'plain_text'         => true,
				'email'              => $this,
			),
			'',
			$this->template_base
		);
	}

	/**
	 * Add recipient; default email type is plain.
	 */
	public function init_form_fields(): void {
		$placeholder_text = sprintf(
			/* translators: %s: list of placeholder tags */
			__( 'Available placeholders: %s', 'un-order' ),
			'<code>' . esc_html( implode( '</code>, <code>', array_keys( $this->placeholders ) ) ) . '</code>'
		);
		$this->form_fields = array(
			'enabled'            => array(
				'title'   => __( 'Enable/Disable', 'un-order' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'un-order' ),
				'default' => 'yes',
			),
			'recipient'          => array(
				'title'       => __( 'Recipient(s)', 'un-order' ),
				'type'        => 'text',
				'description' => sprintf(
					/* translators: %s: default WordPress admin email address */
					__( 'Enter recipient(s) (comma separated). Defaults to the address set under WooCommerce > Settings > Un Order, or %s if not set.', 'un-order' ),
					esc_html( (string) get_option( 'admin_email', '' ) )
				),
				'placeholder' => '',
				'default'     => (string) get_option( 'un_order_admin_email', get_option( 'admin_email', '' ) ),
				'desc_tip'    => true,
			),
			'subject'            => array(
				'title'       => __( 'Subject', 'un-order' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_subject(),
				'default'     => '',
			),
			'heading'            => array(
				'title'       => __( 'Email heading', 'un-order' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_heading(),
				'default'     => '',
			),
			'additional_content' => array(
				'title'       => __( 'Additional content', 'un-order' ),
				'description' => __( 'Text to appear below the main email content (HTML emails only).', 'un-order' ) . ' ' . $placeholder_text,
				'css'         => 'width:400px; height: 75px;',
				'placeholder' => '',
				'type'        => 'textarea',
				'default'     => $this->get_default_additional_content(),
				'desc_tip'    => true,
			),
			'email_type'         => array(
				'title'       => __( 'Email type', 'un-order' ),
				'type'        => 'select',
				'description' => __( 'Plain text is recommended for this admin notice.', 'un-order' ),
				'default'     => 'plain',
				'class'       => 'email_type wc-enhanced-select',
				'options'     => $this->get_email_type_options(),
				'desc_tip'    => true,
			),
		);
	}
}

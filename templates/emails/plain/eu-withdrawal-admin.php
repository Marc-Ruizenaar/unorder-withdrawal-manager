<?php
/**
 * Admin email: withdrawal request (plain).
 *
 * @package UnOrder
 * @var \WC_Order     $order
 * @var string        $email_heading
 * @var list<array{ title: string, quantity: string }> $withdrawal_lines
 * @var string        $withdrawal_reason
 * @var int           $return_period_days
 * @var \WC_Email     $email
 */

defined( 'ABSPATH' ) || exit;

$un_order_plain_lines = array();
foreach ( $withdrawal_lines as $un_order_line_row ) {
	$un_order_plain_lines[] = '- ' . $un_order_line_row['title'] . ' x ' . $un_order_line_row['quantity'];
}

$un_order_plain_body  = esc_html__( 'A customer submitted a withdrawal request.', 'un-order' ) . "\n\n";
$un_order_plain_body .= 'Order #' . (string) $order->get_id() . "\n" . $order->get_formatted_billing_full_name() . ' (' . $order->get_billing_email() . ")\n\n";
$un_order_plain_body .= esc_html__( 'Items and quantities', 'un-order' ) . "\n";
$un_order_plain_body .= implode( "\n", $un_order_plain_lines ) . "\n\n";

if ( '' !== $withdrawal_reason ) {
	$un_order_plain_body .= esc_html__( 'Customer message:', 'un-order' ) . "\n";
	$un_order_plain_body .= $withdrawal_reason . "\n\n";
}

$un_order_plain_body .= sprintf(
	/* translators: %d: days */
	__( "The return period for this request has started (%d day(s)).\n", 'un-order' ),
	(int) $return_period_days
);
$un_order_plain_body .= "\n" . $order->get_edit_order_url() . "\n";

/**
 * Filter admin plain withdrawal notification text.
 *
 * @param string    $un_order_plain_body Message body.
 * @param \WC_Order $order               Order.
 * @param \WC_Email $email               Email.
 */
$un_order_plain_body = (string) apply_filters( 'un_order_eu_withdrawal_admin_plain_body', $un_order_plain_body, $order, $email );

// Do not cut long words: true would split URLs (e.g. ...&id then =18 on the next line).
echo esc_html( wordwrap( $un_order_plain_body, 70, "\n", false ) ) . "\n";

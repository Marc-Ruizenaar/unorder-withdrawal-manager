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

$unordw_plain_lines = array();
foreach ( $withdrawal_lines as $unordw_line_row ) {
	$unordw_plain_lines[] = '- ' . $unordw_line_row['title'] . ' x ' . $unordw_line_row['quantity'];
}

$unordw_plain_body  = esc_html__( 'A customer submitted a withdrawal request.', 'un-order' ) . "\n\n";
$unordw_plain_body .= 'Order #' . (string) $order->get_id() . "\n" . $order->get_formatted_billing_full_name() . ' (' . $order->get_billing_email() . ")\n\n";
$unordw_plain_body .= esc_html__( 'Items and quantities', 'un-order' ) . "\n";
$unordw_plain_body .= implode( "\n", $unordw_plain_lines ) . "\n\n";

if ( '' !== $withdrawal_reason ) {
	$unordw_plain_body .= esc_html__( 'Customer message:', 'un-order' ) . "\n";
	$unordw_plain_body .= $withdrawal_reason . "\n\n";
}

$unordw_plain_body .= sprintf(
	/* translators: %d: days */
	__( "The return period for this request has started (%d day(s)).\n", 'un-order' ),
	(int) $return_period_days
);
$unordw_plain_body .= "\n" . $order->get_edit_order_url() . "\n";

/**
 * Filter admin plain withdrawal notification text.
 *
 * @param string    $unordw_plain_body Message body.
 * @param \WC_Order $order               Order.
 * @param \WC_Email $email               Email.
 */
$unordw_plain_body = (string) apply_filters( 'unordw_withdrawal_admin_plain_body', $unordw_plain_body, $order, $email );

// Do not cut long words: true would split URLs (e.g. ...&id then =18 on the next line).
echo esc_html( wordwrap( $unordw_plain_body, 70, "\n", false ) ) . "\n";

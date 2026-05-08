<?php
/**
 * Customer email: withdrawal received (plain).
 *
 * @package UnOrder
 * @var \WC_Order     $order
 * @var string        $email_heading
 * @var string        $additional_content
 * @var int           $return_period_days
 * @var list<array{ title: string, quantity: string }> $withdrawal_lines
 * @var string        $withdrawal_reason
 * @var \WC_Email     $email
 */

defined( 'ABSPATH' ) || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

if ( $order->get_billing_first_name() ) {
	/* translators: %s: first name */
	echo sprintf( esc_html__( 'Hi %s,', 'un-order' ), esc_html( (string) $order->get_billing_first_name() ) ) . "\n\n";
} else {
	echo esc_html__( 'Hi,', 'un-order' ) . "\n\n";
}
printf(
	// translators: %s: number of days.
	esc_html__( 'We have received your withdrawal request for the items below. The withdrawal period of %1$s day(s) has started. We will use your order details on file to process this request.', 'un-order' ) . "\n\n",
	esc_html( (string) (int) $return_period_days )
);

echo esc_html__( 'Items you are withdrawing', 'un-order' ) . "\n";
foreach ( $withdrawal_lines as $un_order_line_row ) {
	echo '- ' . esc_html( $un_order_line_row['title'] . ' x ' . $un_order_line_row['quantity'] ) . "\n";
}
echo "\n";

if ( '' !== $withdrawal_reason ) {
	echo esc_html__( 'Your message', 'un-order' ) . "\n";
	echo esc_html( $withdrawal_reason ) . "\n\n";
}

echo esc_html__( 'As a reminder, here is your order:', 'un-order' ) . "\n\n";

/*
 * @hooked WC_Emails::order_details() Shows the order details table.
 */
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

echo "\n----------------------------------------\n\n";

/*
 * @hooked WC_Emails::order_meta() Shows order meta data.
 */
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );

/*
 * @hooked WC_Emails::customer_details() Shows customer details
 * @hooked WC_Emails::email_address() Shows email address
 */
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

if ( $additional_content ) {
	echo "\n\n" . esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

echo "\n----------------------------------------\n\n";
echo esc_html( wp_strip_all_tags( wptexturize( (string) apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) ) );

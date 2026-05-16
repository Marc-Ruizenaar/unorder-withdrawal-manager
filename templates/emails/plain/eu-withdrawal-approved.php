<?php
/**
 * Customer email: withdrawal approved (plain).
 *
 * @package UnOrder
 */

defined( 'ABSPATH' ) || exit;

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

if ( $order->get_billing_first_name() ) {
	echo sprintf(
		/* translators: %s: customer first name */
		esc_html__( 'Hi %s,', 'un-order' ),
		esc_html( (string) $order->get_billing_first_name() )
	) . "\n\n";
} else {
	echo esc_html__( 'Hi,', 'un-order' ) . "\n\n";
}

echo esc_html__( 'Your withdrawal request for the following items has been approved:', 'un-order' ) . "\n";
foreach ( $withdrawal_lines as $unordw_line_row ) {
	echo '- ' . esc_html( $unordw_line_row['title'] . ' x ' . $unordw_line_row['quantity'] ) . "\n";
}
echo "\n" . esc_html__( 'How to return your items', 'un-order' ) . "\n\n";
echo esc_html__( 'Please pack the items securely and return them to us. Use the original packaging where possible. Contact us if you need a return label or the return address.', 'un-order' ) . "\n\n";
echo esc_html__( 'View order:', 'un-order' ) . ' ' . esc_url( $order->get_view_order_url() ) . "\n";

if ( $additional_content ) {
	echo "\n\n" . esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

echo "\n----------------------------------------\n\n";
echo esc_html( wp_strip_all_tags( wptexturize( (string) apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) ) );

<?php
/**
 * Customer email: withdrawal rejected (plain).
 *
 * @package UnOrder
 * @var string $rejection_message
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

echo esc_html__( 'We have reviewed your withdrawal request, and we are not able to approve it for the following reason:', 'un-order' ) . "\n\n";
$un_order_rejection_body = '' !== trim( $rejection_message )
	? $rejection_message
	: __( 'This withdrawal request does not meet the conditions for approval. If you believe this is a mistake, please contact us.', 'un-order' );
echo esc_html( $un_order_rejection_body ) . "\n\n";
echo esc_html__( 'The request applied to these items:', 'un-order' ) . "\n";
foreach ( $withdrawal_lines as $un_order_line_row ) {
	echo '- ' . esc_html( $un_order_line_row['title'] . ' x ' . $un_order_line_row['quantity'] ) . "\n";
}

if ( $additional_content ) {
	echo "\n\n" . esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

echo "\n----------------------------------------\n\n";
echo esc_html( wp_strip_all_tags( wptexturize( (string) apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) ) ) );

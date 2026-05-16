<?php
/**
 * Customer email: withdrawal rejected (HTML).
 *
 * @package UnOrder
 * @var string $rejection_message
 */

defined( 'ABSPATH' ) || exit;

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p>
	<?php
	if ( $order->get_billing_first_name() ) {
		printf(
			/* translators: %s: customer first name */
			esc_html__( 'Hi %s,', 'un-order' ),
			esc_html( (string) $order->get_billing_first_name() )
		);
	} else {
		esc_html_e( 'Hi,', 'un-order' );
	}
	?>
</p>
<p><?php esc_html_e( 'We have reviewed your withdrawal request, and we are not able to approve it for the following reason:', 'un-order' ); ?></p>
<blockquote>
	<?php
	$unordw_rejection_body = '' !== trim( $rejection_message )
		? $rejection_message
		: __( 'This withdrawal request does not meet the conditions for approval. If you believe this is a mistake, please contact us.', 'un-order' );
	echo wp_kses_post( wpautop( wptexturize( $unordw_rejection_body ) ) );
	?>
</blockquote>

<p><?php esc_html_e( 'The request applied to these items:', 'un-order' ); ?></p>
<ul>
	<?php foreach ( $withdrawal_lines as $unordw_line_row ) : ?>
		<li><?php echo esc_html( $unordw_line_row['title'] . ' &times; ' . $unordw_line_row['quantity'] ); ?></li>
	<?php endforeach; ?>
</ul>
<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );

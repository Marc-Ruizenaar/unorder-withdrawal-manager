<?php
/**
 * Customer email: withdrawal approved (HTML).
 *
 * @package UnOrder
 * @var \WC_Order $order
 * @var list<array{ title: string, quantity: string }> $withdrawal_lines
 * @var int $return_period_days
 * @var \WC_Email $email
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
<p><?php esc_html_e( 'Your withdrawal request for the following items has been approved:', 'un-order' ); ?></p>
<ul>
	<?php foreach ( $withdrawal_lines as $un_order_line_row ) : ?>
		<li><?php echo esc_html( $un_order_line_row['title'] . ' &times; ' . $un_order_line_row['quantity'] ); ?></li>
	<?php endforeach; ?>
</ul>

<p><strong><?php esc_html_e( 'How to return your items', 'un-order' ); ?></strong></p>
<?php
$un_order_store_mail = sanitize_email( (string) get_option( 'woocommerce_email_from_address' ) );
?>
<p>
	<?php
	printf(
		/* translators: %d: number of days to return items */
		esc_html__( 'Please return the products within %d day(s) where applicable.', 'un-order' ),
		(int) $return_period_days
	);
	?>
</p>
<p>
	<?php esc_html_e( 'Please pack the items securely and return them to us. Use the original packaging where possible.', 'un-order' ); ?>
	<?php if ( $un_order_store_mail ) : ?>
		<?php
		echo ' ';
		printf(
			wp_kses(
				/* translators: %s: store email address (linked) */
				__( 'Contact us if you need a return label or the return address: %s', 'un-order' ),
				array( 'a' => array( 'href' => true ) )
			),
			'<a href="mailto:' . esc_attr( $un_order_store_mail ) . '">' . esc_html( $un_order_store_mail ) . '</a>'
		);
		?>
	<?php endif; ?>
</p>
<p>
	<?php
	printf(
		/* translators: %s: order view URL */
		esc_html__( 'You can review your order here: %s', 'un-order' ),
		'<a href="' . esc_url( $order->get_view_order_url() ) . '">' . esc_html__( 'View order', 'un-order' ) . '</a>'
	);
	?>
</p>
<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );

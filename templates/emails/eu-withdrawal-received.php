<?php
/**
 * Customer email: withdrawal received (HTML).
 *
 * @package UnOrder
 * @var \WC_Order                                    $order
 * @var string                                       $email_heading
 * @var string                                       $additional_content
 * @var array<int, float>                            $withdrawal_items
 * @var string                                       $withdrawal_reason
 * @var int                                          $return_period_days
 * @var list<array{ title: string, quantity: string }> $withdrawal_lines
 * @var \WC_Email                                    $email
 */

defined( 'ABSPATH' ) || exit;

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p>
	<?php
	if ( $order->get_billing_first_name() ) {
		/* translators: %s: Customer first name */
		printf( esc_html__( 'Hi %s,', 'un-order' ), esc_html( (string) $order->get_billing_first_name() ) );
	} else {
		esc_html_e( 'Hi,', 'un-order' );
	}
	?>
</p>
<p>
	<?php
	printf(
		/* translators: %s: number of days */
		esc_html__( 'We have received your withdrawal request for the items below. The withdrawal period of %1$s day(s) has started. We will use your order details on file to process this request.', 'un-order' ),
		esc_html( (string) (int) $return_period_days )
	);
	?>
</p>

<p><strong><?php esc_html_e( 'Items you are withdrawing', 'un-order' ); ?></strong></p>
<ul>
	<?php foreach ( $withdrawal_lines as $unordw_line_row ) : ?>
		<li>
			<?php
			echo esc_html( $unordw_line_row['title'] . ' &times; ' . $unordw_line_row['quantity'] );
			?>
		</li>
	<?php endforeach; ?>
</ul>

	<?php if ( '' !== $withdrawal_reason ) : ?>
	<p>
		<strong><?php esc_html_e( 'Your message', 'un-order' ); ?></strong>
	</p>
	<blockquote>
		<?php echo wp_kses_post( wpautop( wptexturize( $withdrawal_reason ) ) ); ?>
	</blockquote>
<?php endif; ?>

<p><?php esc_html_e( 'As a reminder, here is your order:', 'un-order' ); ?></p>
<?php
/*
 * @hooked WC_Emails::order_details() Shows the order details table.
 */
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

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
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );

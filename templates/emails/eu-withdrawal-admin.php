<?php
/**
 * Admin email: withdrawal request (HTML fallback).
 *
 * @package UnOrder
 * @var \WC_Order $order
 * @var list<array{ title: string, quantity: string }> $withdrawal_lines
 * @var string    $withdrawal_reason
 * @var int       $return_period_days
 * @var \WC_Email $email
 */

defined( 'ABSPATH' ) || exit;

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p><?php esc_html_e( 'A customer submitted a withdrawal request.', 'un-order' ); ?></p>
<p>
	<?php
	printf(
		/* translators: 1: order id, 2: customer name, 3: email */
		esc_html__( 'Order #%1$s &mdash; %2$s (%3$s)', 'un-order' ),
		esc_html( (string) $order->get_id() ),
		esc_html( $order->get_formatted_billing_full_name() ),
		esc_html( (string) $order->get_billing_email() )
	);
	?>
</p>
<p><strong><?php esc_html_e( 'Items and quantities', 'un-order' ); ?></strong></p>
<ul>
	<?php foreach ( $withdrawal_lines as $unordw_line_row ) : ?>
		<li><?php echo esc_html( $unordw_line_row['title'] . ' &times; ' . $unordw_line_row['quantity'] ); ?></li>
	<?php endforeach; ?>
</ul>
<?php if ( '' !== $withdrawal_reason ) : ?>
	<p><strong><?php esc_html_e( 'Customer message:', 'un-order' ); ?></strong></p>
	<blockquote><?php echo wp_kses_post( wpautop( wptexturize( $withdrawal_reason ) ) ); ?></blockquote>
<?php endif; ?>
<p>
	<?php
	printf(
		/* translators: %s: number of days */
		esc_html__( 'The return period for this request has started (%s day(s)).', 'un-order' ),
		esc_html( (string) (int) $return_period_days )
	);
	?>
</p>
<p>
	<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">
		<?php esc_html_e( 'View this order in the dashboard', 'un-order' ); ?>
	</a>
</p>
<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}
do_action( 'woocommerce_email_footer', $email );

<?php
/**
 * Shown at my-account/withdrawal/success/?order_id=… after a successful submission.
 * Guests must also pass billing_email=… (added to the redirect URL); logged-in customers do not.
 *
 * @package UnOrder
 * @version 1.0.0
 *
 * @var WC_Order $order Order the withdrawal refers to.
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $order ) || ! ( $order instanceof \WC_Order ) ) {
	return;
}
?>
<div class="un-order-withdrawal un-order-withdrawal--success woocommerce">
	<div class="woocommerce-notices-wrapper">
		<div class="woocommerce-message" role="status">
			<?php
			printf(
				/* translators: %s: order number (may include #) */
				esc_html__( 'Your withdrawal request for order %s has been received. We will process it as soon as possible.', 'un-order' ),
				esc_html( $order->get_order_number() )
			);
			?>
		</div>
	</div>
	<p>
		<a class="woocommerce-button button" href="<?php echo esc_url( wc_get_endpoint_url( 'orders', '', wc_get_page_permalink( 'myaccount' ) ) ); ?>">
			<?php esc_html_e( 'Back to orders', 'un-order' ); ?>
		</a>
		<a class="button" href="<?php echo esc_url( $order->get_view_order_url() ); ?>">
			<?php esc_html_e( 'View this order', 'un-order' ); ?>
		</a>
	</p>
</div>

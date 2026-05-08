<?php
/**
 * Error state for the My Account withdrawal endpoint.
 *
 * Override by copying to yourtheme/woocommerce/myaccount/withdrawal-error.php.
 *
 * @package UnOrder
 * @version 1.0.0
 *
 * @var string $un_order_message User-facing error text.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="woocommerce un-order-withdrawal un-order-withdrawal--error">
	<div class="woocommerce-notices-wrapper">
		<ul class="woocommerce-error" role="alert">
			<li><?php echo esc_html( isset( $un_order_message ) ? (string) $un_order_message : '' ); ?></li>
		</ul>
		<p>
			<a class="woocommerce-button button" href="<?php echo esc_url( wc_get_endpoint_url( 'orders', '', wc_get_page_permalink( 'myaccount' ) ) ); ?>">
				<?php esc_html_e( 'View orders', 'un-order' ); ?>
			</a>
		</p>
	</div>
</div>

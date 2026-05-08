<?php
/**
 * Withdrawal form: quantities, review, confirm (AJAX submit to eu_withdrawal_submit).
 *
 * Override by copying to yourtheme/woocommerce/myaccount/form-withdrawal.php.
 *
 * @package UnOrder
 * @version 1.0.0
 *
 * @var WC_Order $order Order the customer is withdrawing.
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $order ) || ! ( $order instanceof \WC_Order ) ) {
	return;
}

$un_order_line_items = $order->get_items( 'line_item' );
$un_order_line_items = is_array( $un_order_line_items ) ? $un_order_line_items : array();
$un_order_line_rem   = ( isset( $line_remaining ) && is_array( $line_remaining ) ) ? $line_remaining : array();
$un_order_drawable_count = 0;
foreach ( $un_order_line_items as $un_order_li_id => $un_order_li_item ) {
	$un_order_li_id  = (int) $un_order_li_id;
	$un_order_li_qty = (float) $un_order_li_item->get_quantity();
	$un_order_li_rem = array_key_exists( $un_order_li_id, $un_order_line_rem ) ? (float) $un_order_line_rem[ $un_order_li_id ] : $un_order_li_qty;
	if ( $un_order_li_qty > 0.00001 && $un_order_li_rem > 0.00001 ) {
		++$un_order_drawable_count;
	}
}
$un_order_id_for_query = (string) $order->get_id();
$un_order_return_url   = add_query_arg( 'order_id', $un_order_id_for_query, wc_get_endpoint_url( 'withdrawal', '', wc_get_page_permalink( 'myaccount' ) ) );
$un_order_form_select_title = isset( $form_select_title ) && '' !== (string) $form_select_title
	? (string) $form_select_title
	: __( 'Select quantities to withdraw', 'un-order' );
$un_order_form_intro_html = isset( $form_intro ) ? (string) $form_intro : '';
$un_order_guest_token_val = isset( $guest_token ) ? (string) $guest_token : '';

/**
 * Fires before the withdrawal form.
 *
 * @param WC_Order $order Order.
 */
do_action( 'un_order_withdrawal_before_form', $order );
?>

<div class="un-order-withdrawal woocommerce" data-order-id="<?php echo esc_attr( $un_order_id_for_query ); ?>">

	<section class="un-order-withdrawal__summary" aria-labelledby="un-order-summary-heading">
		<h2 id="un-order-summary-heading" class="un-order-withdrawal__heading"><?php esc_html_e( 'Order summary', 'un-order' ); ?></h2>
		<?php
		/**
		 * Fires after the order summary title.
		 *
		 * @param WC_Order $order Order.
		 */
		do_action( 'un_order_withdrawal_after_summary_heading', $order );
		?>
		<table class="shop_table un-order-withdrawal__summary-table" cellspacing="0">
			<thead>
				<tr>
					<th class="un-order-withdrawal__th-product"><?php esc_html_e( 'Product', 'un-order' ); ?></th>
					<th class="un-order-withdrawal__th-qty"><?php esc_html_e( 'Quantity', 'un-order' ); ?></th>
					<th class="un-order-withdrawal__th-total"><?php esc_html_e( 'Total', 'un-order' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr class="un-order-withdrawal__row-meta">
					<td colspan="3">
						<?php
						printf(
							/* translators: 1: order number 2: order date (formatted) */
							esc_html__( 'Order %1$s — %2$s', 'un-order' ),
							esc_html( $order->get_order_number() ),
							esc_html( $order->get_date_created() ? wc_format_datetime( $order->get_date_created() ) : '—' )
						);
						?>
					</td>
				</tr>
				<?php
				/** @var \WC_Order_Item_Product $un_order_sum_item */
				foreach ( $un_order_line_items as $un_order_sum_item_id => $un_order_sum_item ) {
					$un_order_q_line = (float) $un_order_sum_item->get_quantity();
					if ( $un_order_q_line < 0.00001 ) {
						continue;
					}
					$un_order_subtotal = $order->get_formatted_line_subtotal( $un_order_sum_item );
					$un_order_q_is_wh  = abs( $un_order_q_line - (float) (int) round( $un_order_q_line ) ) < 0.000001;
					$un_order_q_show   = $un_order_q_is_wh
						? (string) ( (int) round( $un_order_q_line ) )
						: (string) wc_format_decimal( $un_order_q_line, 4, true );
					?>
					<tr>
						<td class="un-order-withdrawal__td-product" data-title="<?php esc_attr_e( 'Product', 'un-order' ); ?>">
							<?php echo esc_html( wp_strip_all_tags( $un_order_sum_item->get_name() ) ); ?>
						</td>
						<td class="un-order-withdrawal__td-qty" data-title="<?php esc_attr_e( 'Quantity', 'un-order' ); ?>"><?php echo esc_html( $un_order_q_show ); ?></td>
						<td class="un-order-withdrawal__td-total" data-title="<?php esc_attr_e( 'Total', 'un-order' ); ?>"><?php echo wp_kses_post( $un_order_subtotal ); ?></td>
					</tr>
					<?php
				}
				?>
			</tbody>
		</table>
		<?php if ( $un_order_drawable_count < 1 ) : ?>
			<div class="woocommerce-notices-wrapper">
				<div class="woocommerce-info" role="status">
					<?php esc_html_e( 'This order has no products available to withdraw. Please contact the store if you need help.', 'un-order' ); ?>
				</div>
			</div>
		<?php endif; ?>
	</section>

	<?php if ( $un_order_drawable_count > 0 ) : ?>
	<form class="un-order-withdrawal__form" id="un-order-withdrawal-form" method="post" action="<?php echo esc_url( $un_order_return_url ); ?>">

		<?php wp_nonce_field( 'un_order_withdrawal', 'un_order_withdrawal_nonce' ); ?>

		<?php if ( '' !== $un_order_guest_token_val ) : ?>
		<input type="hidden" name="guest_token" value="<?php echo esc_attr( $un_order_guest_token_val ); ?>" />
		<?php endif; ?>

		<div class="un-order-withdrawal__step" id="un-order-step-select" data-un-order-step="select">
			<fieldset class="un-order-withdrawal__items-fieldset">
				<legend class="un-order-withdrawal__legend"><?php echo esc_html( $un_order_form_select_title ); ?></legend>
				<?php if ( '' !== $un_order_form_intro_html ) : ?>
				<p class="un-order-withdrawal__help">
					<?php echo $un_order_form_intro_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post applied by caller. ?>
				</p>
				<?php endif; ?>
				<ul class="un-order-withdrawal__item-list">
					<?php
					/** @var \WC_Order_Item_Product $un_order_line_item */
					foreach ( $un_order_line_items as $un_order_item_id => $un_order_line_item ) {
						$un_order_q_raw = (float) $un_order_line_item->get_quantity();
						if ( $un_order_q_raw < 0.00001 ) {
							continue;
						}
						$un_order_rem = array_key_exists( (int) $un_order_item_id, $un_order_line_rem ) ? (float) $un_order_line_rem[ (int) $un_order_item_id ] : $un_order_q_raw;
						if ( $un_order_rem < 0.00001 ) {
							continue;
						}
						$un_order_labels     = wp_strip_all_tags( (string) $un_order_line_item->get_name() );
						$un_order_q_is_whole = abs( $un_order_q_raw - (float) (int) round( $un_order_q_raw ) ) < 0.000001;
						$un_order_step_attr  = ( $un_order_q_is_whole && $un_order_q_raw >= 0.5 ) ? '1' : 'any';
						$un_order_max_str    = $un_order_q_is_whole
							? (string) ( (int) round( $un_order_rem ) )
							: (string) wc_format_decimal( $un_order_rem, 4, true );
						$un_order_ordered_show = $un_order_q_is_whole
							? (string) ( (int) round( $un_order_q_raw ) )
							: (string) wc_format_decimal( $un_order_q_raw, 4, true );
						$un_order_committed   = max( 0.0, $un_order_q_raw - $un_order_rem );
						$un_order_committed_s = ( $un_order_q_is_whole && $un_order_q_raw >= 0.5 )
							? (string) (int) max( 0, (int) round( $un_order_committed ) )
							: (string) wc_format_decimal( $un_order_committed, 4, true );
						$un_order_input_id = 'un_order_qty_' . (string) $un_order_item_id;
						$un_order_desc_id  = 'un_order_item_ordered_' . (string) $un_order_item_id;
						?>
						<li class="un-order-withdrawal__item" data-un-order-item-id="<?php echo esc_attr( (string) $un_order_item_id ); ?>">
							<div class="un-order-withdrawal__item-main">
								<span class="un-order-withdrawal__item-text"><?php echo esc_html( $un_order_labels ); ?></span>
								<p class="un-order-withdrawal__item-ordered" id="<?php echo esc_attr( $un_order_desc_id ); ?>">
									<?php
									printf(
										/* translators: %s: quantity ordered on the order line */
										esc_html__( 'Ordered: %s', 'un-order' ),
										esc_html( $un_order_ordered_show )
									);
									?>
									<?php
									if ( $un_order_committed > 0.00001 ) {
										echo ' ';
										printf(
											/* translators: 1: quantity already in another withdrawal, 2: max you can enter on this form */
											esc_html__( '(%1$s of these are already in a pending or approved withdrawal. You can request up to %2$s this time.)', 'un-order' ),
											esc_html( $un_order_committed_s ),
											esc_html( $un_order_max_str )
										);
									}
									?>
								</p>
							</div>
							<div class="un-order-withdrawal__item-qty-wrap">
								<label for="<?php echo esc_attr( $un_order_input_id ); ?>" class="un-order-withdrawal__item-qty-label">
									<?php esc_html_e( 'Quantity to withdraw', 'un-order' ); ?>
								</label>
								<input
									type="number"
									name="un_order_item_qty[<?php echo esc_attr( (string) $un_order_item_id ); ?>]"
									id="<?php echo esc_attr( $un_order_input_id ); ?>"
									class="un-order-withdrawal__item-qty input-text"
									min="0"
									max="<?php echo esc_attr( $un_order_max_str ); ?>"
									step="<?php echo esc_attr( $un_order_step_attr ); ?>"
									value="0"
									inputmode="decimal"
									data-un-order-item-label="<?php echo esc_attr( $un_order_labels ); ?>"
									data-un-order-item-max="<?php echo esc_attr( $un_order_max_str ); ?>"
									data-un-order-item-step="<?php echo esc_attr( $un_order_step_attr ); ?>"
									aria-describedby="<?php echo esc_attr( $un_order_desc_id ); ?>"
								/>
							</div>
						</li>
						<?php
					}
					?>
				</ul>
			</fieldset>

			<p class="form-row form-row-wide un-order-withdrawal__field-reason" id="un_order_reason_field">
				<label for="un_order_withdrawal_reason"><?php esc_html_e( 'Reason (optional)', 'un-order' ); ?></label>
				<textarea
					rows="4"
					cols="40"
					name="un_order_withdrawal_reason"
					id="un_order_withdrawal_reason"
					class="input-text un-order-withdrawal__reason"
					autocomplete="off"
				></textarea>
			</p>

			<div class="un-order-withdrawal__error-inline" id="un-order-continue-error" role="alert" hidden></div>

			<p class="un-order-withdrawal__actions">
				<button type="button" class="button alt woocommerce-button un-order-withdrawal__btn-continue" id="un-order-continue-confirm">
					<?php esc_html_e( 'Continue to confirm', 'un-order' ); ?>
				</button>
			</p>
		</div>

		<div class="un-order-withdrawal__step" id="un-order-step-confirm" data-un-order-step="confirm" hidden>
			<h2 class="un-order-withdrawal__heading"><?php esc_html_e( 'Review and confirm withdrawal', 'un-order' ); ?></h2>
			<p class="un-order-withdrawal__help">
				<?php esc_html_e( 'This is the final step of your right of withdrawal. When you confirm, we will register your request and you will be taken to a confirmation page.', 'un-order' ); ?>
			</p>
			<div class="un-order-withdrawal__confirm-box">
				<h3 class="un-order-withdrawal__subheading"><?php esc_html_e( 'What you are withdrawing', 'un-order' ); ?></h3>
				<ul class="un-order-withdrawal__confirm-list" id="un-order-confirm-list"></ul>
				<h3 class="un-order-withdrawal__subheading"><?php esc_html_e( 'Reason (optional)', 'un-order' ); ?></h3>
				<div class="un-order-withdrawal__confirm-reason" id="un-order-confirm-reason"></div>
			</div>
			<div class="un-order-withdrawal__error-inline" id="un-order-submit-error" role="alert" hidden></div>
			<p class="un-order-withdrawal__back-wrap">
				<a href="#" class="un-order-withdrawal__go-back" id="un-order-back-to-edit">
					<?php esc_html_e( 'Go back to edit the selection', 'un-order' ); ?>
				</a>
			</p>
			<p class="un-order-withdrawal__actions un-order-withdrawal__actions--final">
				<button type="button" class="button alt woocommerce-button un-order-withdrawal__btn-final" id="un-order-confirm-withdrawal">
					<?php esc_html_e( 'Confirm withdrawal', 'un-order' ); ?>
				</button>
			</p>
		</div>
	</form>
	<?php endif; ?>
</div>
<?php
/**
 * Fires after the withdrawal form.
 *
 * @param WC_Order $order Order.
 */
do_action( 'un_order_withdrawal_after_form', $order );
?>

<?php
/**
 * Withdrawal lookup form rendered by the [unordw_withdrawal_lookup] shortcode.
 *
 * Customers enter their order number and billing email to find their order and
 * proceed to the standard withdrawal flow. Theme can override by copying this
 * file to yourtheme/woocommerce/shortcode/withdrawal-lookup.php.
 *
 * @package UnOrder
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="un-order-lookup woocommerce" id="un-order-lookup">

	<p class="un-order-lookup__intro">
		<?php esc_html_e( 'To exercise your right of withdrawal, please enter the order number from your confirmation email and the email address you used when placing the order.', 'un-order' ); ?>
	</p>

	<form
		class="un-order-lookup__form"
		id="un-order-lookup-form"
		method="post"
		novalidate
	>
		<p class="form-row form-row-wide un-order-lookup__field">
			<label for="unordw_lookup_order_number">
				<?php esc_html_e( 'Order number', 'un-order' ); ?>
				<abbr class="required" title="<?php esc_attr_e( 'required', 'un-order' ); ?>">*</abbr>
			</label>
			<input
				type="text"
				name="unordw_lookup_order_number"
				id="unordw_lookup_order_number"
				class="input-text un-order-lookup__input"
				autocomplete="off"
				inputmode="numeric"
				required
			/>
		</p>

		<p class="form-row form-row-wide un-order-lookup__field">
			<label for="unordw_lookup_email">
				<?php esc_html_e( 'Billing email address', 'un-order' ); ?>
				<abbr class="required" title="<?php esc_attr_e( 'required', 'un-order' ); ?>">*</abbr>
			</label>
			<input
				type="email"
				name="unordw_lookup_email"
				id="unordw_lookup_email"
				class="input-text un-order-lookup__input"
				autocomplete="email"
				required
			/>
		</p>

		<div
			class="un-order-lookup__error woocommerce-error"
			id="un-order-lookup-error"
			role="alert"
			hidden
		></div>

		<p class="form-row un-order-lookup__actions">
			<button
				type="submit"
				class="button woocommerce-button un-order-lookup__btn"
				id="un-order-lookup-submit"
			>
				<?php esc_html_e( 'Find my order', 'un-order' ); ?>
			</button>
		</p>
	</form>

	<div
		class="un-order-lookup__results"
		id="un-order-lookup-results"
		hidden
		aria-live="polite"
	></div>

</div>

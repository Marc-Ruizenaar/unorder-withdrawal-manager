<?php
/**
 * WooCommerce > Settings > Un Order (withdrawal).
 *
 * @package UnOrder
 */

declare( strict_types=1 );

namespace UnOrder\Admin;

use UnOrder\Capabilities;

/**
 * Un Order settings tab under WooCommerce settings (withdrawal options).
 */
final class UnordwWithdrawalSettingsPage extends \WC_Settings_Page {

	/**
	 * Constructor: tab id and label.
	 */
	public function __construct() {
		$this->id    = 'unordw_withdrawal';
		$this->label = __( 'Un Order', 'un-order' );
		parent::__construct();
	}

	/**
	 * Return settings field definitions.
	 *
	 * @param string $current_section Current section.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_settings( $current_section = '' ): array {
		$settings = array(
			array(
				'type'  => 'title',
				'title' => __( 'Un Order settings', 'un-order' ),
				'desc'  => __( 'Configure the right-of-withdrawal flow (Dutch Civil Code Art. 230oa / EU Directive).', 'un-order' ),
				'id'    => 'unordw_settings_section',
			),
			array(
				'type'              => 'number',
				'id'                => 'unordw_period_days',
				'title'             => __( 'Withdrawal period (days)', 'un-order' ),
				'desc'              => __(
					'Days after purchase during which the customer may exercise the right of withdrawal. Statutory minimum is 14 days.',
					'un-order'
				) . ' ' . __( 'Un Order keeps a 14-day window unless Un Order Pro is active and configures a different period.',
					'un-order'
				),
				'default'           => '14',
				'css'               => 'width:80px;',
				'custom_attributes' => array(
					'min'      => '1',
					'step'     => '1',
					'readonly' => 'readonly',
				),
				'desc_tip'          => true,
			),
		);

		$categories_field = array(
			'type'     => 'multiselect',
			'id'       => 'unordw_excluded_categories',
			'title'    => __( 'Disable withdrawal for categories', 'un-order' ),
			'desc'     => __( 'Orders whose items all belong to the selected categories will not show the withdrawal button. Leave empty to allow withdrawal for all categories.', 'un-order' ),
			'options'  => $this->get_product_category_options(),
			'default'  => array(),
			'class'    => 'wc-enhanced-select',
			'css'      => 'min-width:350px;',
			'desc_tip' => true,
		);

		if ( ! Capabilities::category_exclusions_supported() ) {
			$categories_field['desc'] .= ' ' . __(
				'Editing requires Un Order Pro. While using Un Order only, exclusions are not applied on the storefront.',
				'un-order'
			);
			$categories_field['custom_attributes'] = array(
				'disabled' => 'disabled',
			);
			$categories_field['class'] .= ' un-order-setting-locked-select';
			$categories_field['css']  .= 'pointer-events:none;opacity:0.88;';
		}

		$settings[] = $categories_field;

		$settings = array_merge(
			$settings,
			array(
				array(
					'type'     => 'text',
					'id'       => 'unordw_form_select_title',
					'title'    => __( 'Quantity selection title', 'un-order' ),
					'desc'     => __( 'Heading shown inside the withdrawal form above the product list (the fieldset legend).', 'un-order' ),
					'default'  => __( 'Select quantities to withdraw', 'un-order' ),
					'css'      => 'width:100%;',
					'desc_tip' => true,
				),
				array(
					'type'     => 'textarea',
					'id'       => 'unordw_form_intro',
					'title'    => __( 'Withdrawal form help text', 'un-order' ),
					'desc'     => __( 'Explanatory paragraph shown below the quantity selection title. Basic HTML is allowed. Leave blank to hide.', 'un-order' ),
					'default'  => __( 'For each product, choose how many units to withdraw. The maximum is what you still have available after any earlier pending or approved withdrawal for this order. A rejected request does not use up your available quantity. Use 0 if you are not withdrawing a line in this request.', 'un-order' ),
					'css'      => 'width:100%;height:80px;',
					'desc_tip' => true,
				),
				array(
					'type'        => 'email',
					'id'          => 'unordw_admin_email',
					'title'       => __( 'Admin notification email', 'un-order' ),
					'desc'        => __( 'Override the recipient for withdrawal admin notifications. Leave blank to use the WooCommerce store email.', 'un-order' ),
					'default'     => '',
					/* translators: %s: current admin email address */
					'placeholder' => sprintf( __( 'Defaults to %s', 'un-order' ), (string) get_option( 'admin_email', '' ) ),
					'desc_tip'    => true,
				),
				array(
					'type' => 'title',
					'id'   => 'unordw_pro_guest_notice',
					'desc' => __(
						'Customers withdraw from <strong>My Account → Orders</strong>. Guest order lookup and the <code>[unordw_withdrawal_lookup]</code> shortcode are added by <strong>Un Order Pro</strong>.',
						'un-order'
					),
				),
				array(
					'type' => 'sectionend',
					'id'   => 'unordw_settings_section',
				),
			)
		);

		// Passes $settings (array) and $current_section (string) to the filter.
		return (array) apply_filters( 'unordw_get_settings', $settings, $current_section );
	}

	/**
	 * Build a term_id => name map of all product categories.
	 *
	 * @return array<string, string>
	 */
	private function get_product_category_options(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);

		if ( ! is_array( $terms ) || is_wp_error( $terms ) ) {
			return array();
		}

		$options = array();
		foreach ( $terms as $term ) {
			if ( $term instanceof \WP_Term ) {
				$options[ (string) $term->term_id ] = esc_html( $term->name );
			}
		}

		return $options;
	}
}

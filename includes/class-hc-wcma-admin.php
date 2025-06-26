<?php
/**
 * Class HC_WCMA_Admin
 *
 * @package happycoders-multiple-addresses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class HC_WCMA_Admin
 */
class HC_WCMA_Admin {

	/**
	 * Initializes the plugin for admin-side functionality.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'woocommerce_settings_tabs_array', array( __CLASS__, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_tabs_hc_wcma', array( __CLASS__, 'settings_tab_content' ) );
		add_action( 'woocommerce_update_options_hc_wcma', array( __CLASS__, 'update_settings' ) );
	}

	/**
	 * Add the settings tab to the WooCommerce settings page.
	 *
	 * @param array $settings_tabs WooCommerce settings tabs.
	 * @return array WooCommerce settings tabs with our tab added.
	 */
	public static function add_settings_tab( array $settings_tabs ): array {
		$settings_tabs['hc_wcma'] = __( 'HC Multiple Addresses', 'happycoders-multiple-addresses' );
		return $settings_tabs;
	}

	/**
	 * Renders the content for the settings tab.
	 *
	 * @return void
	 */
	public static function settings_tab_content(): void {
		woocommerce_admin_fields( self::get_settings() );
	}

	/**
	 * Handles saving the settings for the plugin.
	 *
	 * @return void
	 */
	public static function update_settings(): void {
		woocommerce_update_options( self::get_settings() );
	}

	/**
	 * Defines the settings fields for the plugin.
	 *
	 * @return array The array of settings fields.
	 */
	public static function get_settings(): array {
		$settings = array(
			'section_title_display'          => array(
				'name' => __( 'Checkout Display Options', 'happycoders-multiple-addresses' ),
				'type' => 'title',
				'desc' => '',
				'id'   => 'hc_wcma_checkout_options_display_title',
			),
			'checkout_selector_style'        => array(
				'name'     => __( 'Address Selector Style', 'happycoders-multiple-addresses' ),
				'type'     => 'select',
				'desc'     => __( 'Choose how the list of saved addresses is presented (dropdown or list).', 'happycoders-multiple-addresses' ),
				'id'       => 'hc_wcma_checkout_selector_style',
				'options'  => array(
					'dropdown' => __( 'Dropdown Select Box', 'happycoders-multiple-addresses' ),
					'list'     => __( 'List (Radio Buttons)', 'happycoders-multiple-addresses' ),
				),
				'default'  => 'dropdown',
				'desc_tip' => true,
			),
			'checkout_saved_address_display' => array(
				'name'     => __( 'Saved Address Display Format', 'happycoders-multiple-addresses' ),
				'type'     => 'select',
				'desc'     => __( 'For Classic Checkout: When a saved address is selected, show the full editable form fields or just a formatted address block?', 'happycoders-multiple-addresses' ),
				'id'       => 'hc_wcma_checkout_saved_address_display',
				'options'  => array(
					'fields' => __( 'Show Editable Form Fields', 'happycoders-multiple-addresses' ),
					'block'  => __( 'Show Formatted Address Block (Hide Fields)', 'happycoders-multiple-addresses' ),
				),
				'default'  => 'block',
				'desc_tip' => true,
			),
			'checkout_allow_new_address'     => array(
				'name'     => __( 'Allow New Address Entry On Checkout Page', 'happycoders-multiple-addresses' ),
				'type'     => 'select',
				'desc'     => __( 'Allow customers to enter a new address directly on the checkout page?', 'happycoders-multiple-addresses' ),
				'id'       => 'hc_wcma_checkout_allow_new_address',
				'options'  => array(
					'yes' => __( 'Yes', 'happycoders-multiple-addresses' ),
					'no'  => __( 'No', 'happycoders-multiple-addresses' ),
				),
				'default'  => 'yes',
				'desc_tip' => true,
			),
			'section_end'                    => array(
				'type' => 'sectionend',
				'id'   => 'hc_wcma_checkout_options_end',
			),
			'section_title_limits'           => array(
				'name' => __( 'Address Book Limits', 'happycoders-multiple-addresses' ),
				'type' => 'title',
				'desc' => __( 'Set maximum number of addresses users can save. Leave blank or set to 0 for unlimited.', 'happycoders-multiple-addresses' ),
				'id'   => 'hc_wcma_address_book_limits_title',
			),
			'limit_max_billing_addresses'    => array(
				'name'              => __( 'Max Billing Addresses', 'happycoders-multiple-addresses' ),
				'type'              => 'number',
				'desc'              => __( 'Maximum total number of billing addresses allowed per user.', 'happycoders-multiple-addresses' ),
				'id'                => 'hc_wcma_limit_max_billing_addresses',
				'css'               => 'width:80px;',
				'default'           => '0',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'min'  => 0,
					'step' => 1,
				),
			),
			'limit_max_shipping_addresses'   => array(
				'name'              => __( 'Max Shipping Addresses', 'happycoders-multiple-addresses' ),
				'type'              => 'number',
				'desc'              => __( 'Maximum total number of shipping addresses allowed per user.', 'happycoders-multiple-addresses' ),
				'id'                => 'hc_wcma_limit_max_shipping_addresses',
				'css'               => 'width:80px;',
				'default'           => '0',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'min'  => 0,
					'step' => 1,
				),
			),
			'section_end_limits'             => array(
				'type' => 'sectionend',
				'id'   => 'hc_wcma_address_book_limits_end',
			),
		);
		return apply_filters( 'hc_wcma_settings', $settings );
	}
}

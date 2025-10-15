<?php
/**
 * HappyCoders Multiple Addresses Blocks.
 *
 * @package happycoders-multiple-addresses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue script and localize data for AUTOMATIC Checkout Block JS DOM Injection.
 */
function hc_wcma_enqueue_checkout_dom_injection_scripts() {
	if ( ! is_admin() && function_exists( 'is_checkout' ) && is_checkout() && is_user_logged_in() ) {

		$is_block_checkout = false;
		global $post;
		// ... (Keep your block detection logic using WC_Blocks_Utils or has_blocks) ...
		if ( $post && class_exists( '\Automattic\WooCommerce\Blocks\Utils\WC_Blocks_Utils' ) && method_exists( '\Automattic\WooCommerce\Blocks\Utils\WC_Blocks_Utils', 'has_block_in_page' ) ) {
			if ( WC_Blocks_Utils::has_block_in_page( $post->ID, 'woocommerce/checkout' ) ) {
				$is_block_checkout = true;
			}
		} elseif ( $post && has_blocks( $post->post_content ) && strpos( $post->post_content, '<!-- wp:woocommerce/checkout ' ) !== false ) {
			$is_block_checkout = true;
		}

		if ( ! $is_block_checkout ) {
			return;
		}
		// --- End Block Detection ---

		// --- Define paths and handle for block-frontend.js ---
		$script_handle  = 'hc-wcma-frontend-integration';
		$build_dir_path = HC_WCMA_PLUGIN_PATH . 'build/';
		$build_dir_url  = HC_WCMA_PLUGIN_URL . 'build/';

		$script_asset_path = $build_dir_path . 'block-frontend.asset.php';
		$script_url        = $build_dir_url . 'block-frontend.js';
		$style_url         = $build_dir_url . 'style.css';
		// --- End Definitions ---

		if ( file_exists( $script_asset_path ) ) {
			$script_asset = require $script_asset_path;
			wp_enqueue_script(
				$script_handle,
				$script_url,
				array_merge( array( 'jquery' ), $script_asset['dependencies'] ),
				$script_asset['version'],
				true
			);

			if ( file_exists( $build_dir_path . 'style.css' ) ) {
				wp_enqueue_style(
					$script_handle . '-style',
					$build_dir_url . 'style.css',
					array(),
					$script_asset['version']
				);
			} elseif ( file_exists( $build_dir_path . 'style-block-frontend.css' ) ) {
					wp_enqueue_style(
						$script_handle . '-style',
						$build_dir_url . 'style-block-frontend.css',
						array(),
						$script_asset['version']
					);
			}

			wp_set_script_translations( 'hc-wcma-frontend-integration', 'happycoders-multiple-addresses', HC_WCMA_PLUGIN_PATH . 'languages' );

			// --- Localize necessary data ---
			$user_id = get_current_user_id();
			// ... (Get addresses, settings, defaults etc.) ...
			$checkout_addresses = array();
			$billing_addresses  = hc_wcma_get_user_addresses( $user_id, 'billing' );
			$shipping_addresses = hc_wcma_get_user_addresses( $user_id, 'shipping' );
			foreach ( $billing_addresses as $key => $addr ) {
				$addr['key']                           = $key;
				$checkout_addresses['billing'][ $key ] = $addr; }
			foreach ( $shipping_addresses as $key => $addr ) {
				$addr['key']                            = $key;
				$checkout_addresses['shipping'][ $key ] = $addr; }
			$default_billing_key  = hc_wcma_get_default_address_key( $user_id, 'billing' );
			$default_shipping_key = hc_wcma_get_default_address_key( $user_id, 'shipping' );

			wp_localize_script(
				$script_handle,
				'hc_wcma_block_params',
				array(
					'userId'             => $user_id,
					'addresses'          => $checkout_addresses,
					'selector_style'     => get_option( 'hc_wcma_checkout_selector_style', 'dropdown' ),
					'saved_display'      => get_option( 'hc_wcma_checkout_saved_address_display', 'block' ),
					'allow_new'          => get_option( 'hc_wcma_checkout_allow_new_address', 'yes' ),
					'defaultKeys'        => array(
						'billing'  => $default_billing_key,
						'shipping' => $default_shipping_key,
					),
					'nonce'              => wp_create_nonce( 'wp_rest' ),
					'i18n'               => array(
						'default_label'         => __( '(Default)', 'happycoders-multiple-addresses' ),
						'new_address'           => __( 'Enter a new address', 'happycoders-multiple-addresses' ),
						'loading'               => __( 'Loading...', 'happycoders-multiple-addresses' ),
						'select_billing'        => __( '-- Select Billing Address --', 'happycoders-multiple-addresses' ),
						'select_shipping'       => __( '-- Select Shipping Address --', 'happycoders-multiple-addresses' ),
						'select_nickname'       => __( 'Select Nickname', 'happycoders-multiple-addresses' ),
						'home'                  => __( 'Home', 'happycoders-multiple-addresses' ),
						'work'                  => __( 'Work', 'happycoders-multiple-addresses' ),
						'other'                 => __( 'Other', 'happycoders-multiple-addresses' ),
						'address_nickname_type' => __( 'Address Nickname Type', 'happycoders-multiple-addresses' ),
						'custom_nickname'       => __( 'Custom Nickname', 'happycoders-multiple-addresses' ),
					),
					'existing_nicknames' => hc_wcma_get_existing_nicknames( $user_id ),
				)
			);

		}
	}
}
add_action( 'wp_enqueue_scripts', 'hc_wcma_enqueue_checkout_dom_injection_scripts', 20 );

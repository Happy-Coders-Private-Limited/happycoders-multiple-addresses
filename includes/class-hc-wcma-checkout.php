<?php
/**
 * Class HC_WCMA_Checkout
 *
 * @package happycoders-multiple-addresses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class HC_WCMA_Checkout
 */
class HC_WCMA_Checkout {

	/**
	 * Initializes the plugin. Hooks into the following:
	 * - Adds a menu item to the My Account page
	 * - Adds content for the endpoint
	 * - Adds query vars if needed (though endpoint usually handles it)
	 */
	public static function init() {
		add_action( 'woocommerce_before_checkout_billing_form', array( __CLASS__, 'display_billing_address_selector' ), 5 );
		add_action( 'woocommerce_before_checkout_shipping_form', array( __CLASS__, 'display_shipping_address_selector' ), 5 );
		add_action( 'woocommerce_checkout_update_order_meta', array( __CLASS__, 'save_selected_address_to_order' ), 10, 2 );
	}

	/**
	 * Displays the billing address selector on the checkout page.
	 *
	 * This function checks if the user is logged in before displaying
	 * the billing address selector. It calls the generic address selector
	 * display function with 'billing' as the address type.
	 */
	public static function display_billing_address_selector() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		self::display_address_selector( 'billing' );
	}

	/**
	 * Displays the shipping address selector on the checkout page.
	 *
	 * This function checks if the user is logged in and if shipping is needed
	 * before displaying the shipping address selector. It calls the generic
	 * address selector display function with 'shipping' as the address type.
	 */
	public static function display_shipping_address_selector() {
		if ( ! is_user_logged_in() || ! WC()->cart->needs_shipping_address() ) {
			return;
		}

		self::display_address_selector( 'shipping' );
	}

	/**
	 * Displays the address selector on the checkout page.
	 *
	 * This function checks if the WooCommerce checkout is using blocks and
	 * exits early if it is. Otherwise, it retrieves the user's saved addresses
	 * and displays them in a dropdown or list, depending on the settings.
	 * An option to enter a new address is displayed if allowed.
	 *
	 * @param string $type     The type of address to display (billing or shipping).
	 */
	private static function display_address_selector( $type ) {

		$is_block_checkout = false;

		global $post;

		if ( is_singular() && $post ) {
			if ( class_exists( 'WC_Blocks_Utils' ) && method_exists( 'WC_Blocks_Utils', 'has_block_in_page' ) ) {
				if ( WC_Blocks_Utils::has_block_in_page( $post->ID, 'woocommerce/checkout' ) ) {
					$is_block_checkout = true;
				}
			}
		}

		if ( $is_block_checkout ) {
			return;
		}

		$user_id        = get_current_user_id();
		$addresses      = hc_wcma_get_user_addresses( $user_id, $type );
		$default_key    = hc_wcma_get_default_address_key( $user_id, $type );
		$selector_style = get_option( 'hc_wcma_checkout_selector_style', 'dropdown' );
		$allow_new      = get_option( 'hc_wcma_checkout_allow_new_address', 'yes' );

		if ( empty( $addresses ) && 'yes' !== $allow_new ) {
			return;
		}

		$field_id            = 'hc_wcma_select_' . $type . '_address';
		$selector_wrapper_id = 'hc_wcma_' . esc_attr( $type ) . '_selector';
		$address_block_id    = 'hc_wcma_' . esc_attr( $type ) . '_address_block';

		echo '<div class="hc-wcma-checkout-selector" id="' . esc_attr( $selector_wrapper_id ) . '">';
		echo '<h4>' . sprintf( /* translators: %s = address type */ esc_html__( 'Select Saved %s Address', 'happycoders-multiple-addresses' ), esc_attr( ucfirst( $type ) ) ) . '</h4>';

		if ( 'dropdown' === $selector_style ) {
			echo '<p class="form-row form-row-wide">';
			echo '<label for="' . esc_attr( $field_id ) . '">' . sprintf( /* translators: %s = address type */ esc_html__( 'Choose %s address', 'happycoders-multiple-addresses' ), esc_attr( $type ) ) . '…</label>';
			echo '<select name="' . esc_attr( $field_id ) . '" id="' . esc_attr( $field_id ) . '" class="hc-wcma-address-select" data-address-type="' . esc_attr( $type ) . '">';
			echo '<option value="">' . esc_html__( '-- Select --', 'happycoders-multiple-addresses' ) . '</option>';
			if ( $default_key && isset( $addresses[ $default_key ] ) ) {
				echo '<option value="' . esc_attr( $default_key ) . '" selected="selected">' . esc_html( $addresses[ $default_key ]['nickname'] ?? $default_key ) . ' ' . esc_html__( '(Default)', 'happycoders-multiple-addresses' ) . '</option>';
			}
			foreach ( $addresses as $key => $address ) {
				if ( $key === $default_key ) {
					continue;
				}
				$label = $address['nickname'] ?? ( $address['address_1'] ?? $key );
				echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
			}
			if ( 'yes' === $allow_new ) {
				echo '<option value="new">' . esc_html__( 'Enter a new address', 'happycoders-multiple-addresses' ) . '</option>';
			}
			echo '</select>';
			echo '</p>';
		} else {
			echo '<ul class="hc-wcma-address-list">';
			if ( $default_key && isset( $addresses[ $default_key ] ) ) {
				echo '<li><input type="radio" name="' . esc_attr( $field_id ) . '" id="' . esc_attr( $field_id ) . '_' . esc_attr( $default_key ) . '" value="' . esc_attr( $default_key ) . '" checked="checked" class="input-radio hc-wcma-address-select" data-address-type="' . esc_attr( $type ) . '"> ';
				echo '<label for="' . esc_attr( $field_id ) . '_' . esc_attr( $default_key ) . '">' . esc_html( $addresses[ $default_key ]['nickname'] ?? $default_key ) . ' ' . esc_html__( '(Default)', 'happycoders-multiple-addresses' ) . '</label></li>';
			}
			$is_first_radio = ! $default_key && ! empty( $addresses );
			foreach ( $addresses as $key => $address ) {
				if ( $key === $default_key ) {
					continue;
				}
				$label   = $address['nickname'] ?? ( $address['address_1'] ?? $key );
				$checked = $is_first_radio ? 'checked="checked"' : '';
				echo '<li><input type="radio" name="' . esc_attr( $field_id ) . '" id="' . esc_attr( $field_id ) . '_' . esc_attr( $key ) . '" value="' . esc_attr( $key ) . '" ' . esc_attr( $checked ) . ' class="input-radio hc-wcma-address-select" data-address-type="' . esc_attr( $type ) . '"> ';
				echo '<label for="' . esc_attr( $field_id ) . '_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></li>';
				$is_first_radio = false;
			}
			if ( 'yes' === $allow_new ) {
				$checked = ( empty( $addresses ) && ! $default_key ) ? 'checked="checked"' : '';
				echo '<li><input type="radio" name="' . esc_attr( $field_id ) . '" id="' . esc_attr( $field_id ) . '_new" value="new" ' . esc_attr( $checked ) . ' class="input-radio hc-wcma-address-select" data-address-type="' . esc_attr( $type ) . '"> ';
				echo '<label for="' . esc_attr( $field_id ) . '_new">' . esc_html__( 'Enter a new address', 'happycoders-multiple-addresses' ) . '</label></li>';
			}
			echo '</ul>';
		}
		echo '<div class="hc-wcma-formatted-address-block" id="' . esc_attr( $address_block_id ) . '" style="display:none; margin-bottom: 1em; padding: 10px; border: 1px solid black; background: #f9f9f9;"></div>';

		echo '</div>';
	}

	/**
	 * Saves the selected address to the order metadata.
	 *
	 * This function checks if the user is logged in and retrieves the selected
	 * billing and shipping addresses from the posted data. If a valid address
	 * key is provided and the key is not 'new', it retrieves the address
	 * details and saves them as a snapshot in the order's metadata.
	 *
	 * @param int   $order_id    The ID of the order.
	 * @param array $posted_data The posted data containing selected address keys.
	 */
	public static function save_selected_address_to_order( $order_id, $posted_data ) {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( isset( $posted_data['hc_wcma_select_billing_address'] ) ) {
			$selected_key = wc_clean( $posted_data['hc_wcma_select_billing_address'] );
			if ( $selected_key && 'new' !== $selected_key ) {
				$address = hc_wcma_get_address_by_key( $user_id, $selected_key, 'billing' );
				if ( $address ) {
					update_post_meta( $order_id, '_hc_wcma_selected_billing_address_snapshot', $address );
				}
			}
		}

		if ( isset( $posted_data['hc_wcma_select_shipping_address'] ) && WC()->cart->needs_shipping_address() ) {
			$selected_key = wc_clean( $posted_data['hc_wcma_select_shipping_address'] );
			if ( $selected_key && 'new' !== $selected_key ) {
				$address = hc_wcma_get_address_by_key( $user_id, $selected_key, 'shipping' );
				if ( $address ) {
					update_post_meta( $order_id, '_hc_wcma_selected_shipping_address_snapshot', $address );
				}
			}
		}
	}
}

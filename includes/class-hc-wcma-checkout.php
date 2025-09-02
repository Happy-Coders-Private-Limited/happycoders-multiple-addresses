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
		add_action( 'woocommerce_new_order', array( __CLASS__, 'save_new_address_from_order' ), 10, 1 );
		self::init_fields();
		add_action( 'woocommerce_checkout_process', array( __CLASS__, 'validate_nickname_field' ) );
		add_action(
			'woocommerce_after_order_notes',
			function () {
				wp_nonce_field( 'hc_wcma_checkout_action', 'hc_wcma_checkout_nonce' );
			}
		);
	}

	/**
	 * Validates the nickname fields during checkout.
	 * Ensures that if a new address is being entered, the nickname type is selected,
	 * and if 'Other' is selected, a custom nickname is provided.
	 * Adds error notices if validation fails.
	 * This function is hooked into 'woocommerce_checkout_process'.
	 */
	public static function validate_nickname_field() {

		if ( ! self::hc_wcma_is_block_checkout() ) {
			if ( ! isset( $_POST['hc_wcma_checkout_nonce'] ) ||
				! wp_verify_nonce(
					sanitize_text_field( wp_unslash( $_POST['hc_wcma_checkout_nonce'] ) ),
					'hc_wcma_checkout_action'
				)
			) {
				return;
			}
		}

		$billing_selected_address  = isset( $_POST['hc_wcma_select_billing_address'] ) ? sanitize_text_field( wp_unslash( $_POST['hc_wcma_select_billing_address'] ) ) : '';
		$shipping_selected_address = isset( $_POST['hc_wcma_select_shipping_address'] ) ? sanitize_text_field( wp_unslash( $_POST['hc_wcma_select_shipping_address'] ) ) : '';

		// Validate billing nickname only if a new billing address is being entered.
		if ( 'new' === $billing_selected_address ) {
			if ( empty( $_POST['billing_nickname_type'] ) ) {
				wc_add_notice( __( 'Please select a nickname type for your billing address.', 'happycoders-multiple-addresses' ), 'error' );
			}
			if ( isset( $_POST['billing_nickname_type'] ) && 'Other' === $_POST['billing_nickname_type'] && empty( $_POST['billing_nickname'] ) ) {
				wc_add_notice( __( 'Please enter a custom nickname for your billing address.', 'happycoders-multiple-addresses' ), 'error' );
			}
		}

		// Validate shipping nickname only if a new shipping address is being entered.
		if ( isset( $_POST['ship_to_different_address'] ) ) {
			if ( 'new' === $shipping_selected_address ) {
				if ( empty( $_POST['shipping_nickname_type'] ) ) {
					wc_add_notice( __( 'Please select a nickname type for your shipping address.', 'happycoders-multiple-addresses' ), 'error' );
				}
				if ( isset( $_POST['shipping_nickname_type'] ) && 'Other' === $_POST['shipping_nickname_type'] && empty( $_POST['shipping_nickname'] ) ) {
					wc_add_notice( __( 'Please enter a custom nickname for your shipping address.', 'happycoders-multiple-addresses' ), 'error' );
				}
			}
		}
	}

	/**
	 * Initializes the custom fields for billing and shipping addresses.
	 * Hooks into WooCommerce filters to add nickname type and custom nickname fields.
	 */
	public static function init_fields() {
		add_filter( 'woocommerce_billing_fields', array( __CLASS__, 'add_nickname_fields_to_checkout' ) );
		add_filter( 'woocommerce_shipping_fields', array( __CLASS__, 'add_nickname_fields_to_checkout' ) );
	}

	/**
	 * Adds the nickname type and custom nickname fields to the checkout form.
	 *
	 * @param array $fields The existing checkout fields.
	 * @return array The modified checkout fields.
	 */
	public static function add_nickname_fields_to_checkout( $fields ) {
		$nickname_type_field = array(
			'type'     => 'select',
			'label'    => __( 'Address Nickname Type', 'happycoders-multiple-addresses' ),
			'required' => false,
			'class'    => array( 'form-row-wide', 'hc-wcma-nickname-type-select' ),
			'priority' => 5,
			'options'  => array(
				''      => __( 'Select an option...', 'happycoders-multiple-addresses' ),
				'Home'  => __( 'Home', 'happycoders-multiple-addresses' ),
				'Work'  => __( 'Work', 'happycoders-multiple-addresses' ),
				'Other' => __( 'Other', 'happycoders-multiple-addresses' ),
			),
		);

		$nickname_field = array(
			'label'       => __( 'Custom Nickname', 'happycoders-multiple-addresses' ),
			'placeholder' => __( 'e.g., Shipping Place', 'happycoders-multiple-addresses' ),
			'required'    => false,
			'class'       => array( 'form-row-wide', 'hc-wcma-nickname-other-field' ),
			'priority'    => 6,
			'type'        => 'text',
		);

		// Determine if it's billing or shipping fields.
		$prefix = '';
		if ( isset( $fields['billing_first_name'] ) ) {
			$prefix = 'billing_';
		} elseif ( isset( $fields['shipping_first_name'] ) ) {
			$prefix = 'shipping_';
		}

		$fields[ $prefix . 'nickname_type' ] = $nickname_type_field;
		$fields[ $prefix . 'nickname' ]      = $nickname_field;

		// Reorder fields to place nickname at the top.
		$new_fields = array();
		foreach ( $fields as $key => $field ) {
			if ( $key === $prefix . 'nickname_type' || $key === $prefix . 'nickname' ) {
				continue;
			}
			$new_fields[ $key ] = $field;
		}

		// Insert nickname fields at the beginning.
		$fields = array_merge(
			array(
				$prefix . 'nickname_type' => $nickname_type_field,
				$prefix . 'nickname'      => $nickname_field,
			),
			$new_fields
		);

		return $fields;
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
			// echo '<option value="">' . esc_html__( '-- Select --', 'happycoders-multiple-addresses' ) . '</option>';
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
	 * Get the value of a session or post variable.
	 * 	
	 * @param string $session_key The key of the session variable.
	 * @param string $post_key The key of the post variable.
	 * @return string The value of the variable.
	 * 	
	 */
	private static function hc_wcma_get_session_or_post( $session_key, $post_key ) {
		$value = WC()->session->get( $session_key );
		if ( ! empty( $value ) ) {
			return $value;
		}
		return isset( $_POST[ $post_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) ) : '';
	}

	/**
	 * UNIVERSAL handler called after a new order is created.
	 * It checks the order's addresses and saves them if they are new.
	 * This function now handles BOTH Classic and Block checkouts.
	 *
	 * @param int $order_id The ID of the newly created order.
	 */
	public static function save_new_address_from_order( $order_id ) {

		if ( ! self::hc_wcma_is_block_checkout() ) {
			if ( ! isset( $_POST['hc_wcma_checkout_nonce'] ) ||
				! wp_verify_nonce(
					sanitize_text_field( wp_unslash( $_POST['hc_wcma_checkout_nonce'] ) ),
					'hc_wcma_checkout_action'
				)
			) {
				return;
			}
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$customer_id = $order->get_customer_id();

		if ( ! $customer_id || 0 === $customer_id ) {
			return;
		}

		$stored_billing_nickname_type   = self::hc_wcma_get_session_or_post( 'hc_wcma_billing_nickname_type', 'billing_nickname_type' );
		$stored_billing_nickname_custom = self::hc_wcma_get_session_or_post( 'hc_wcma_billing_nickname', 'billing_nickname' );
		if ( isset( $_POST['ship_to_different_address'] ) ) {
			error_log( 'Classic checkout' );
			$stored_shipping_nickname_type  = self::hc_wcma_get_session_or_post( 'hc_wcma_shipping_nickname_type', 'shipping_nickname_type' );
			$stored_shipping_nickname_custom= self::hc_wcma_get_session_or_post( 'hc_wcma_shipping_nickname', 'shipping_nickname' );
		} elseif ( self::hc_wcma_is_block_checkout() ) {
			error_log( 'Block checkout' );
			$stored_shipping_nickname_type  = self::hc_wcma_get_session_or_post( 'hc_wcma_shipping_nickname_type', 'shipping_nickname_type' );
			$stored_shipping_nickname_custom= self::hc_wcma_get_session_or_post( 'hc_wcma_shipping_nickname', 'shipping_nickname' );
			$stored_billing_nickname_type   = ! empty($stored_billing_nickname_type) ? $stored_billing_nickname_type : 	$stored_shipping_nickname_type;
			$stored_billing_nickname_custom = ! empty($stored_billing_nickname_custom) ? $stored_billing_nickname_custom : $stored_shipping_nickname_custom;
		} elseif ( self::hc_wcma_is_block_checkout() === false || ! isset( $_POST['ship_to_different_address'] ) ) {
			error_log( 'No shipping address' );
			$stored_shipping_nickname_type  = $stored_billing_nickname_type;
			$stored_shipping_nickname_custom= $stored_billing_nickname_custom;
		}
			
		error_log( 'Stored billing nickname type: ' . $stored_billing_nickname_type );
		error_log( 'Stored billing nickname: ' . $stored_billing_nickname_custom );
		error_log( 'Stored shipping nickname type: ' . $stored_shipping_nickname_type );
		error_log( 'Stored shipping nickname: ' . $stored_shipping_nickname_custom );

		// Clear the session data.
		WC()->session->set( 'hc_wcma_billing_nickname_type', '' );
		WC()->session->set( 'hc_wcma_billing_nickname', '' );
		WC()->session->set( 'hc_wcma_shipping_nickname_type', '' );
		WC()->session->set( 'hc_wcma_shipping_nickname', '' );

		// $billing  = $order->get_address( 'billing' );
		// $shipping = $order->get_address( 'shipping' );

		// // Compare only address-related fields, not email/phone.
		// $billing_for_compare  = $billing;
		// $shipping_for_compare = $shipping;

		// // Remove fields you don’t want in the comparison (like email, phone, company if irrelevant).
		// unset( $billing_for_compare['email'], $billing_for_compare['phone'] );
		// unset( $shipping_for_compare['email'], $shipping_for_compare['phone'] );

		// if ( $billing_for_compare === $shipping_for_compare ) {
		// 	error_log( 'Addresses are the same' );
		// 	$shipping_nickname_type = $stored_shipping_nickname_type ?? $stored_billing_nickname_type;
		// 	error_log( 'Shipping nickname type: ' . $shipping_nickname_type );
		// 	$billing_nickname_type  = $stored_billing_nickname_type ?? $stored_shipping_nickname_type;
		// 	error_log( 'Billing nickname type: ' . $billing_nickname_type );

		// 	$shipping_nickname_custom = $stored_shipping_nickname_custom ?? $stored_billing_nickname_custom;
		// 	error_log( 'Shipping nickname custom: ' . $shipping_nickname_custom );
		// 	$billing_nickname_custom  = $stored_billing_nickname_custom ?? $stored_shipping_nickname_custom;
		// 	error_log( 'Billing nickname custom: ' . $billing_nickname_custom );
		// } else {
		// 	error_log( 'Addresses are different' );
		// 	$billing_nickname_type    = isset( $_POST['billing_nickname_type'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_nickname_type'] ) ) : $stored_shipping_nickname_type;
		// 	$billing_nickname_custom  = isset( $_POST['billing_nickname'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_nickname'] ) ) : $stored_billing_nickname_custom;
		// 	$shipping_nickname_type   = isset( $_POST['shipping_nickname_type'] ) ? sanitize_text_field( wp_unslash( $_POST['shipping_nickname_type'] ) ) : $stored_shipping_nickname_type;
		// 	$shipping_nickname_custom = isset( $_POST['shipping_nickname'] ) ? sanitize_text_field( wp_unslash( $_POST['shipping_nickname'] ) ) : $stored_shipping_nickname_custom;
		// }

		self::process_order_address( $customer_id, $order, 'billing', $stored_billing_nickname_type, $stored_billing_nickname_custom );

		if ( $order->has_shipping_address() ) {
			self::process_order_address( $customer_id, $order, 'shipping', $stored_shipping_nickname_type, $stored_shipping_nickname_custom );
		}
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

	/**
	 * Helper function to process and save a single address type from a WC_Order object.
	 *
	 * @param int      $customer_id The customer ID.
	 * @param WC_Order $order       The order object.
	 * @param string   $type        'billing' or 'shipping'.
	 * @param string   $nickname_type The type of nickname ('Home', 'Work', 'Other', or '').
	 * @param string   $nickname_custom The custom nickname if 'Other' is selected.
	 */
	private static function process_order_address( $customer_id, $order, $type, $nickname_type = '', $nickname_custom = '' ) {
		$limit             = (int) get_option( 'hc_wcma_limit_max_' . $type . '_addresses', 0 );
		$current_addresses = hc_wcma_get_user_addresses( $customer_id, $type );
		if ( $limit > 0 && count( $current_addresses ) >= $limit ) {
			return;
		}

		$new_address = array();
		$fields      = hc_wcma_get_address_fields( $type );

		foreach ( $fields as $key => $field_config ) {
			$getter_method = 'get_' . $type . '_' . $key;
			if ( method_exists( $order, $getter_method ) ) {
				$value = $order->{$getter_method}();
				if ( 'email' === $key ) {
					$new_address[ $key ] = sanitize_email( $value ); } elseif ( 'phone' === $key ) {
					$new_address[ $key ] = wc_sanitize_phone_number( $value ); } else {
						$new_address[ $key ] = sanitize_text_field( $value ); }
			}
		}

		if ( empty( $new_address['first_name'] ) && empty( $new_address['address_1'] ) ) {
			return;
		}

		$temp_new_address = $new_address;
		unset( $temp_new_address['nickname'] );

		foreach ( $current_addresses as $existing_address ) {
			$temp_existing_address = $existing_address;
			unset( $temp_existing_address['nickname'] );
			if ( $temp_new_address === $temp_existing_address ) {
				return;
			}
		}

		// Construct the nickname based on type and custom value.
		$submitted_nickname = '';
		if ( 'Other' === $nickname_type ) {
			$submitted_nickname = $nickname_custom;
		} elseif ( in_array( $nickname_type, array( 'Home', 'Work' ), true ) ) {
			$submitted_nickname = $nickname_type;
		}

		// Use submitted nickname if available, otherwise fall back to first/last name.
		if ( ! empty( $submitted_nickname ) ) {
			$new_address['nickname'] = $submitted_nickname;
		} else {
			$new_address['nickname'] = ! empty( $new_address['first_name'] ) ? $new_address['first_name'] . ' ' . $new_address['last_name'] : $new_address['address_1'];
		}

		$address_key                       = hc_wcma_generate_address_key();
		$current_addresses[ $address_key ] = $new_address;

		hc_wcma_save_user_addresses( $customer_id, $current_addresses, $type );
		hc_wcma_set_default_address_key( $customer_id, $address_key, $type );
	}

	/**
	 * Checks if the current checkout is a block-based checkout.
	 *
	 * @return bool True if it's a block checkout, false otherwise.
	 */
	private static function hc_wcma_is_block_checkout() {

		if ( WC_Blocks_Utils::has_block_in_page( wc_get_page_id( 'checkout' ), 'woocommerce/checkout' ) ) {
			return true;
		} else {
			return false;
		}
	}
}

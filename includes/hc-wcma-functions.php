<?php
/**
 * HappyCoders Multiple Addresses Helper Functions.
 *
 * @package happycoders-multiple-addresses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Get all addresses for a user for a specific type, or all types.
 *
 * @since 1.0.0
 * @param int    $user_id The ID of the user.
 * @param string $type Optional. The address type ('billing' or 'shipping'). Default is empty string.
 * @return array An array of addresses. If $type is specified, returns addresses for that type.
 *               Otherwise, returns an array with 'billing' and 'shipping' keys.
 */
function hc_wcma_get_user_addresses( int $user_id, string $type = '' ): array {
	if ( ! $user_id ) {
		return array();
	}

	$billing_addresses  = get_user_meta( $user_id, '_hc_wcma_billing_addresses', true );
	$shipping_addresses = get_user_meta( $user_id, '_hc_wcma_shipping_addresses', true );

	$billing_addresses  = is_array( $billing_addresses ) ? $billing_addresses : array();
	$shipping_addresses = is_array( $shipping_addresses ) ? $shipping_addresses : array();

	if ( 'billing' === $type ) {
		return $billing_addresses;
	}

	if ( 'shipping' === $type ) {
		return $shipping_addresses;
	}

	return array(
		'billing'  => $billing_addresses,
		'shipping' => $shipping_addresses,
	);
}

/**
 * Save an array of addresses for a user.
 *
 * @since 1.0.0
 * @param int    $user_id   The ID of the user.
 * @param array  $addresses The array of addresses to save.
 * @param string $type      The address type ('billing' or 'shipping').
 * @return bool True on success, false on failure.
 */
function hc_wcma_save_user_addresses( int $user_id, array $addresses, string $type ): bool {
	if ( ! $user_id || ! in_array( $type, array( 'billing', 'shipping' ), true ) ) {
		return false;
	}
	$addresses = array_map( 'wc_clean', $addresses );

	$meta_key = '_hc_wcma_' . $type . '_addresses';
	return update_user_meta( $user_id, $meta_key, $addresses );
}

/**
 * Get a specific address by its key.
 *
 * @since 1.0.0
 *
 * @param int    $user_id User ID.
 * @param string $address_key Unique key for the address.
 * @param string $type 'billing' or 'shipping'.
 * @return array|null Address array or null if not found.
 */
function hc_wcma_get_address_by_key( $user_id, $address_key, $type ) {
	$addresses = hc_wcma_get_user_addresses( $user_id, $type );
	return isset( $addresses[ $address_key ] ) ? $addresses[ $address_key ] : null;
}

/**
 * Get the default address key for a user and type.
 *
 * @since 1.0.0
 *
 * @param int    $user_id User ID.
 * @param string $type 'billing' or 'shipping'.
 * @return string|null Unique key for the default address, or null if not set.
 */
function hc_wcma_get_default_address_key( $user_id, $type ) {
	if ( ! $user_id || ! in_array( $type, array( 'billing', 'shipping' ), true ) ) {
		return null;
	}
	return get_user_meta( $user_id, '_hc_wcma_default_' . $type . '_key', true );
}

/**
 * Set the default address key for a user.
 *
 * @since 1.0.0
 *
 * @param int    $user_id User ID.
 * @param string $address_key Unique key for the address.
 * @param string $type 'billing' or 'shipping'.
 * @return bool True on success, false if the user ID or address type is invalid, or the address does not exist.
 */
function hc_wcma_set_default_address_key( $user_id, $address_key, $type ) {
	if ( ! $user_id || ! in_array( $type, array( 'billing', 'shipping' ), true ) ) {
		return false;
	}
	$address = hc_wcma_get_address_by_key( $user_id, $address_key, $type );
	if ( ! $address ) {
		return false;
	}
	return update_user_meta( $user_id, '_hc_wcma_default_' . $type . '_key', sanitize_text_field( $address_key ) );
}

/**
 * Generate a unique key for a new address.
 *
 * @since 1.0.0
 * @return string Unique address key.
 */
function hc_wcma_generate_address_key() {
	return 'addr_' . wp_generate_password( 12, false );
}

/**
 * Retrieve the address fields for a given type.
 *
 * @since 1.0.0
 *
 * The function adds a custom field called 'nickname' which is a text field with a label and placeholder.
 *
 * @param string $type 'billing' or 'shipping'. Defaults to 'billing'.
 * @return array Array of address fields.
 */
function hc_wcma_get_address_fields( $type = 'billing' ) {
	$fields       = WC()->countries->get_address_fields( WC()->customer->get_billing_country(), $type . '_' );
	$clean_fields = array();
	foreach ( $fields as $key => $field_data ) {
		$clean_key                  = str_replace( $type . '_', '', $key );
		$clean_fields[ $clean_key ] = $field_data;
	}
	$clean_fields['nickname'] = array(
		'label'       => __( 'Address Nickname', 'happycoders-multiple-addresses' ),
		'placeholder' => __( 'e.g., Home, Work', 'happycoders-multiple-addresses' ),
		'required'    => false,
		'class'       => array( 'form-row-wide' ),
		'priority'    => 5,
	);

	// Explicitly add phone field and determine its required status from WooCommerce settings.
	$phone_field_setting = get_option( 'woocommerce_checkout_phone_field', 'required' );
	if ( 'hidden' !== $phone_field_setting ) {
		$clean_fields['phone'] = array(
			'label'       => __( 'Phone', 'woocommerce' ),
			'required'    => ( 'required' === $phone_field_setting ),
			'class'       => array( 'form-row-wide', 'woocommerce-validated' ),
			'type'        => 'tel',
			'priority'    => 100,
		);
	}
	return $clean_fields;
}


/**
 * Format an address array into a string, suitable for display.
 *
 * @since 1.0.0
 *
 * @param array  $address          Address data array.
 * @param string $separator        Separator to use between address lines. Defaults to '<br/>'.
 * @return string                  Formatted address string.
 */
function hc_wcma_format_address_for_display( $address, $separator = '<br/>' ) {
	if ( empty( $address ) || ! is_array( $address ) ) {
		return '';
	}

	$address_components = array(
		'first_name' => $address['first_name'] ?? '',
		'last_name'  => $address['last_name'] ?? '',
		'company'    => $address['company'] ?? '',
		'address_1'  => $address['address_1'] ?? '',
		'address_2'  => $address['address_2'] ?? '',
		'city'       => $address['city'] ?? '',
		'state'      => $address['state'] ?? '',
		'postcode'   => $address['postcode'] ?? '',
		'country'    => $address['country'] ?? '',
		'phone'      => $address['phone'] ?? '',
		'email'      => $address['email'] ?? '',
	);

	$address_components = array_filter( $address_components );

	return WC()->countries->get_formatted_address( $address_components, $separator );
}

/**
 * Get existing nicknames for a user.
 *
 * @since 1.0.7
 *
 * @param int $user_id User ID.
 * @return array Array of existing nicknames for billing and shipping.
 */
function hc_wcma_get_existing_nicknames( $user_id ) {
	$nicknames = array(
		'billing'  => array(),
		'shipping' => array(),
	);

	$billing_addresses = hc_wcma_get_user_addresses( $user_id, 'billing' );
	foreach ( $billing_addresses as $address ) {
		if ( ! empty( $address['nickname'] ) ) {
			$nicknames['billing'][] = $address['nickname'];
		}
	}

	$shipping_addresses = hc_wcma_get_user_addresses( $user_id, 'shipping' );
	foreach ( $shipping_addresses as $address ) {
		if ( ! empty( $address['nickname'] ) ) {
			$nicknames['shipping'][] = $address['nickname'];
		}
	}

	return $nicknames;
}

/**
 * Applies the saved field settings to an array of address fields.
 *
 * @since 1.0.12
 * @param array  $fields The array of address fields.
 * @param string $type   The address type ('billing' or 'shipping').
 * @return array The modified array of address fields.
 */
function hc_wcma_apply_field_settings( $fields, $type ) {
	$field_settings = array(
		'company'   => get_option( 'woocommerce_checkout_company_field', 'optional' ),
		'address_2' => get_option( 'woocommerce_checkout_address_2_field', 'optional' ),
		'phone'     => get_option( 'woocommerce_checkout_phone_field', 'required' ),
	);

	$default_labels = array(
		'company'   => __( 'Company', 'woocommerce' ),
		'address_2' => __( 'Apartment, suite, unit, etc. (optional)', 'woocommerce' ),
		'phone'     => __( 'Phone', 'woocommerce' ),
	);

	foreach ( $field_settings as $field_key => $setting ) {
		$field_name = $type . '_' . $field_key;

		// If the field is hidden, ensure it's removed.
		if ( 'hidden' === $setting ) {
			unset( $fields[ $field_name ] );
			continue;
		}

		// If the field doesn't exist, add it.
		if ( ! isset( $fields[ $field_name ] ) ) {
			$fields[ $field_name ] = array(
				'label' => $default_labels[ $field_key ] ?? '',
			);
		}

		// Set the required status.
		$fields[ $field_name ]['required'] = ( 'required' === $setting );
	}

	return $fields;
}

/**
 * Checks if a saved address is missing any required fields.
 *
 * @since 1.0.12
 * @param array  $address The address array.
 * @param string $type    The address type ('billing' or 'shipping').
 * @return bool True if complete, false if incomplete.
 */
function hc_wcma_is_address_complete( $address, $type ) {
	$all_fields = WC()->countries->get_address_fields( $address['country'] ?? '', $type . '_' );
	$all_fields = hc_wcma_apply_field_settings( $all_fields, $type );

	foreach ( $all_fields as $key => $field ) {
		if ( ! empty( $field['required'] ) ) {
			$clean_key = str_replace( $type . '_', '', $key );
			if ( empty( $address[ $clean_key ] ) ) {
				return false; // Found a missing required field
			}
		}
	}
	return true;
}

/**
 * Formats an address for display, showing all non-empty fields.
 *
 * @since 1.0.12
 * @param array $address The address array.
 * @return string The formatted address HTML.
 */
function hc_wcma_format_address_for_display_full( $address ) {
	$output = '';
	$name   = trim( ( $address['first_name'] ?? '' ) . ' ' . ( $address['last_name'] ?? '' ) );
	if ( $name ) {
		$output .= '<strong>' . esc_html( $name ) . '</strong>';
	}

	$order = array( 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'email', 'phone' );

	foreach ( $order as $field_key ) {
		if ( ! empty( $address[ $field_key ] ) ) {
			$output .= '<br/>' . esc_html( $address[ $field_key ] );
		}
	}
	return $output;
}
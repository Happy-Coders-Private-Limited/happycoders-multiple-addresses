<?php
/**
 * Class HC_WCMA_AJAX
 *
 * @package happycoders-multiple-addresses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class HC_WCMA_AJAX
 */
class HC_WCMA_AJAX {
	/**
	 * Register AJAX handlers for address operations.
	 *
	 * Registers 'wp_ajax_hc_wcma_{action}' actions for the following actions:
	 * - add_address
	 * - update_address
	 * - delete_address
	 * - set_default_address
	 * - get_address_details_for_checkout (not currently handled)
	 */
	public static function init() {
		$ajax_actions = array(
			'add_address',
			'update_address',
			'delete_address',
			'set_default_address',
		);

		foreach ( $ajax_actions as $action ) {
			add_action( 'wp_ajax_hc_wcma_' . $action, array( __CLASS__, 'handle_' . $action ) );
		}
	}

	/**
	 * Handle adding a new address.
	 */
	public static function handle_add_address() {

		$nonce_action     = 'hc_wcma_save_address_action';
		$nonce_field_name = 'hc_wcma_save_address_nonce';

		$nonce_value = isset( $_POST[ $nonce_field_name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $nonce_field_name ] ) ) : '';

		if ( empty( $nonce_value ) || ! wp_verify_nonce( $nonce_value, $nonce_action ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'happycoders-multiple-addresses' ) . ' (Action: ' . esc_html( $nonce_action ) . ')' ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'happycoders-multiple-addresses' ) ), 401 );
		}
		$user_id = get_current_user_id();

		$address_data           = isset( $_POST['address_data'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['address_data'] ) ) : array();
		$address_type_selection = isset( $_POST['address_type_selection'] ) ? sanitize_text_field( wp_unslash( $_POST['address_type_selection'] ) ) : '';
		$shipping_same_as_billing = isset( $address_data['shipping_same_as_billing'] ) && '1' === $address_data['shipping_same_as_billing'];

		if ( empty( $address_type_selection ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select an address type.', 'happycoders-multiple-addresses' ) ) );
		}

		$types_to_save = array();
		if ( 'billing' === $address_type_selection || 'both' === $address_type_selection ) {
			$types_to_save[] = 'billing';
		}
		if ( 'shipping' === $address_type_selection || 'both' === $address_type_selection ) {
			$types_to_save[] = 'shipping';
		}


		$errors                   = array();
		$new_keys                 = array();
		$saved_successfully_count = 0;
		$limit_reached_messages   = array();

		foreach ( $types_to_save as $type ) {
			$prefix = $type . '_';

			$limit_option_key = 'hc_wcma_limit_max_' . $type . '_addresses';
			$limit            = (int) get_option( $limit_option_key, 0 );

			if ( $limit > 0 ) {
				$current_addresses = hc_wcma_get_user_addresses( $user_id, $type );
				$current_count     = count( $current_addresses );

				if ( $current_count >= $limit ) {
					$limit_error_message = sprintf(
						/* translators: 1: Limit, 2: Address type */
						__( 'You have reached the maximum limit of %1$d saved %2$s addresses.', 'happycoders-multiple-addresses' ),
						$limit,
						$type
					);
					$errors[]                 = $limit_error_message;
					$limit_reached_messages[] = $limit_error_message;
					continue;
				}
			}
			$fields      = WC()->countries->get_address_fields( '', $prefix );
			$new_address = array();
			$address_key = hc_wcma_generate_address_key();

			$billing_address_for_shipping = array();
				if ( 'shipping' === $type && $shipping_same_as_billing ) {
					foreach ( $address_data as $key => $value ) {
						if ( strpos( $key, 'billing_' ) === 0 ) {
							$billing_address_for_shipping[ str_replace( 'billing_', 'shipping_', $key ) ] = $value;
						}
					}
				}


			foreach ( $fields as $key => $field_config ) {
				$post_key  = $key;
				$clean_key = str_replace( $prefix, '', $key );

				if ( 'shipping' === $type && $shipping_same_as_billing ) {
					$value = isset( $billing_address_for_shipping[ $post_key ] ) ? sanitize_text_field( $billing_address_for_shipping[ $post_key ] ) : '';
				} else {
					$value = isset( $address_data[ $post_key ] ) ? sanitize_text_field( $address_data[ $post_key ] ) : '';
				}

				if ( 'email' === $clean_key ) {
					$sanitized_value = sanitize_email( $value );
				} elseif ( 'phone' === $clean_key ) {
					$sanitized_value = wc_sanitize_phone_number( $value );
				} else {
					$sanitized_value = sanitize_text_field( $value );
				}

				if ( 'nickname' === $clean_key ) {
					$value           = isset( $address_data[ $prefix . 'nickname' ] ) ? sanitize_text_field( $address_data[ $prefix . 'nickname' ] ) : $sanitized_value;
					$sanitized_value = $value;
				}

				if ( 'company' === $clean_key ) {
					$value           = isset( $address_data[ $prefix . 'company' ] ) ? sanitize_text_field( $address_data[ $prefix . 'company' ] ) : $sanitized_value;
					$sanitized_value = $value;
				}

				if ( 'email' === $clean_key ) {
					$value           = isset( $address_data[ $prefix . 'email' ] ) ? sanitize_email( $address_data[ $prefix . 'email' ] ) : $sanitized_value;
					$sanitized_value = $value;
				}

				if ( 'phone' === $clean_key ) {
					$value           = isset( $address_data[ $prefix . 'phone' ] ) ? wc_sanitize_phone_number( $address_data[ $prefix . 'phone' ] ) : $sanitized_value;
					$sanitized_value = $value;
				}

				if ( 'postcode' === $clean_key ) {
					$country          = '';
					$country_post_key = $prefix . 'country';
					if ( isset( $address_data[ $country_post_key ] ) ) {
						$country = sanitize_text_field( $address_data[ $country_post_key ] );
					}

					if ( $country && ! empty( $sanitized_value ) ) {
						if ( ! WC_Validation::is_postcode( $sanitized_value, $country ) ) {
							$errors[] = sprintf(
								/* translators: %s: Country name */
								__( 'Please enter a valid postcode / ZIP for %s.', 'happycoders-multiple-addresses' ),
								WC()->countries->get_states( $country ) ? __( 'the selected state', 'happycoders-multiple-addresses' ) : WC()->countries->countries[ $country ]
							);
						}
					}
				}

				if ( ! empty( $field_config['required'] ) && '' === $sanitized_value ) {
					$field_label = $field_config['label'] ?? ucfirst( str_replace( '_', ' ', $clean_key ) );
					$errors[]    = sprintf(
						/* translators: %1$s: Field label, %2$s: Address type */
						__( '%1$s is a required field for %2$s address.', 'happycoders-multiple-addresses' ),
						$field_label,
						$type
					);
				}
				$new_address[ $clean_key ] = $sanitized_value;
			}

			// Handle nickname
			$nickname_type_key  = $prefix . 'nickname_type';
			$nickname_field_key = $prefix . 'nickname';
			if ( 'shipping' === $type && $shipping_same_as_billing ) {
				$nickname_type_key  = 'billing_nickname_type';
				$nickname_field_key = 'billing_nickname';
			}

			$nickname_type = isset( $address_data[ $nickname_type_key ] ) ? $address_data[ $nickname_type_key ] : '';
			$nickname      = '';
			if ( 'Other' === $nickname_type ) {
				$nickname = isset( $address_data[ $nickname_field_key ] ) ? sanitize_text_field( $address_data[ $nickname_field_key ] ) : '';
			} elseif ( in_array( $nickname_type, array( 'Home', 'Work' ), true ) ) {
				$nickname = $nickname_type;
			}

			if ( empty( $nickname ) ) {
				$errors[] = __( 'Address nickname is a required field.', 'happycoders-multiple-addresses' );
			}
			$new_address['nickname'] = $nickname;

			// Handle other extra fields
			if ( 'shipping' === $type && $shipping_same_as_billing ) {
				if ( ! isset( $new_address['company'] ) ) {
					$new_address['company'] = isset( $address_data['billing_company'] ) ? sanitize_text_field( $address_data['billing_company'] ) : '';
				}
				if ( ! isset( $new_address['email'] ) ) {
					$new_address['email'] = isset( $address_data['billing_email'] ) ? sanitize_email( $address_data['billing_email'] ) : '';
				}
				if ( ! isset( $new_address['phone'] ) ) {
					$new_address['phone'] = isset( $address_data['billing_phone'] ) ? wc_sanitize_phone_number( $address_data['billing_phone'] ) : '';
				}
			} else {
				if ( ! isset( $new_address['company'] ) ) {
					$new_address['company'] = isset( $address_data[ $prefix . 'company' ] ) ? sanitize_text_field( $address_data[ $prefix . 'company' ] ) : '';
				}
				if ( ! isset( $new_address['email'] ) ) {
					$new_address['email'] = isset( $address_data[ $prefix . 'email' ] ) ? sanitize_email( $address_data[ $prefix . 'email' ] ) : '';
				}
				if ( ! isset( $new_address['phone'] ) ) {
					$new_address['phone'] = isset( $address_data[ $prefix . 'phone' ] ) ? wc_sanitize_phone_number( $address_data[ $prefix . 'phone' ] ) : '';
				}
			}

			if ( empty( $errors ) ) {
				$addresses                 = hc_wcma_get_user_addresses( $user_id, $type );
				$addresses[ $address_key ] = $new_address;

				if ( hc_wcma_save_user_addresses( $user_id, $addresses, $type ) ) {
					$new_keys[ $type ] = $address_key;
					++$saved_successfully_count;
				}

				if ( hc_wcma_set_default_address_key( $user_id, $address_key, $type ) ) {
					$prefix_core      = $type . '_';
					$standard_wc_keys = array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone', 'email' );
					foreach ( $standard_wc_keys as $field_key ) {
						if ( isset( $new_address[ $field_key ] ) ) {
							$wp_meta_key = $prefix_core . $field_key;
							$value       = $new_address[ $field_key ];
							update_user_meta( $user_id, $wp_meta_key, $value );
						}
					}
				}
			} else {
				$errors[] = sprintf(
					/* translators: %s: Address type */
					__( 'Failed to save %s address.', 'happycoders-multiple-addresses' ),
					$type
				);
			}
		}

		if ( $saved_successfully_count > 0 && empty( $limit_reached_messages ) ) {
			wp_send_json_success(
				array(
					'message' => __( 'Address added successfully.', 'happycoders-multiple-addresses' ),
					'reload'  => true,
				)
			);
		} elseif ( $saved_successfully_count > 0 && ! empty( $limit_reached_messages ) ) {
			wp_send_json_success(
				array(
					'message' => __( 'Address saved successfully for one type.', 'happycoders-multiple-addresses' ) . ' ' . implode( ' ', $limit_reached_messages ),
					'reload'  => true,
				)
			);
		} elseif ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'message' => implode( '<br>', $errors ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Could not save address. An unknown error occurred.', 'happycoders-multiple-addresses' ) ) );
		}
	}

	/**
	 * Handle updating an existing address.
	 */
	public static function handle_update_address() {

		$nonce_action     = 'hc_wcma_save_address_action';
		$nonce_field_name = 'hc_wcma_edit_address_nonce';

		$nonce_value = isset( $_POST[ $nonce_field_name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $nonce_field_name ] ) ) : '';

		if ( empty( $nonce_value ) || ! wp_verify_nonce( $nonce_value, $nonce_action ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'happycoders-multiple-addresses' ) . ' (Action: ' . esc_html( $nonce_action ) . ')' ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'happycoders-multiple-addresses' ) ), 401 );
		}
		$user_id = get_current_user_id();

		$address_data = isset( $_POST['address_data'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['address_data'] ) ) : array();
		$address_key  = isset( $_POST['address_key'] ) ? sanitize_text_field( wp_unslash( $_POST['address_key'] ) ) : '';
		$address_type = isset( $_POST['address_type'] ) ? sanitize_text_field( wp_unslash( $_POST['address_type'] ) ) : '';

		if ( ! $address_key || ! $address_type || ! in_array( $address_type, array( 'billing', 'shipping' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing address identifier.', 'happycoders-multiple-addresses' ) ) );
		}

		$addresses = hc_wcma_get_user_addresses( $user_id, $address_type );
		if ( ! isset( $addresses[ $address_key ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Address not found.', 'happycoders-multiple-addresses' ) ) );
		}

		$original_address = $addresses[ $address_key ];

		$prefix          = $address_type . '_';
		$fields          = WC()->countries->get_address_fields( '', $prefix );
		$updated_address = array();
		$errors          = array();

		foreach ( $fields as $key => $field_config ) {
			$post_key  = $key;
			$clean_key = str_replace( $prefix, '', $key );

			$value = isset( $address_data[ $post_key ] ) ? sanitize_text_field( $address_data[ $post_key ] ) : '';

			if ( 'email' === $clean_key ) {
				$sanitized_value = sanitize_email( $value );
			} elseif ( 'phone' === $clean_key ) {
					$sanitized_value = wc_sanitize_phone_number( $value );
			} else {
					$sanitized_value = sanitize_text_field( $value );
			}

			if ( 'nickname' === $clean_key ) {
				$value           = isset( $address_data[ $prefix . 'nickname' ] ) ? sanitize_text_field( $address_data[ $prefix . 'nickname' ] ) : $sanitized_value;
				$sanitized_value = $value;
			}

			if ( 'company' === $clean_key ) {
				$value           = isset( $address_data[ $prefix . 'company' ] ) ? sanitize_text_field( $address_data[ $prefix . 'company' ] ) : $sanitized_value;
				$sanitized_value = $value;
			}

			if ( 'email' === $clean_key ) {
				$value           = isset( $address_data[ $prefix . 'email' ] ) ? sanitize_email( $address_data[ $prefix . 'email' ] ) : $sanitized_value;
				$sanitized_value = $value;
			}

			if ( 'phone' === $clean_key ) {
				$value           = isset( $address_data[ $prefix . 'phone' ] ) ? wc_sanitize_phone_number( $address_data[ $prefix . 'phone' ] ) : $sanitized_value;
				$sanitized_value = $value;
			}

			if ( 'postcode' === $clean_key ) {
				$country          = '';
				$country_post_key = $prefix . 'country';
				if ( isset( $address_data[ $country_post_key ] ) ) {
					$country = sanitize_text_field( $address_data[ $country_post_key ] );
				}

				if ( $country && ! empty( $sanitized_value ) ) {
					if ( ! WC_Validation::is_postcode( $sanitized_value, $country ) ) {
						$errors[] = sprintf(
							/* Translators: %s is the country name */
							__( 'Please enter a valid postcode / ZIP for %s.', 'happycoders-multiple-addresses' ),
							WC()->countries->get_states( $country ) ? __( 'the selected state', 'happycoders-multiple-addresses' ) : WC()->countries->countries[ $country ]
						);
					}
				}
			}

			if ( ! empty( $field_config['required'] ) && '' === $sanitized_value ) {
				$field_label = $field_config['label'] ?? ucfirst( str_replace( '_', ' ', $clean_key ) );
				$errors[]    = sprintf(
					/* Translators: %s is the field label */
					__( '%s is a required field.', 'happycoders-multiple-addresses' ),
					$field_label
				);
			}
			$updated_address[ $clean_key ] = $sanitized_value;
		}

		// Handle nickname
		$nickname_type = isset( $address_data[ $prefix . 'nickname_type' ] ) ? $address_data[ $prefix . 'nickname_type' ] : '';
		$nickname      = '';
		if ( 'Other' === $nickname_type ) {
			$nickname = isset( $address_data[ $prefix . 'nickname' ] ) ? sanitize_text_field( $address_data[ $prefix . 'nickname' ] ) : '';
		} elseif ( in_array( $nickname_type, array( 'Home', 'Work' ), true ) ) {
			$nickname = $nickname_type;
		}

		if ( empty( $nickname ) ) {
			$errors[] = __( 'Address nickname is a required field.', 'happycoders-multiple-addresses' );
		}
		$updated_address['nickname'] = $nickname;

		if ( ! isset( $updated_address['company'] ) ) {
			$updated_address['company'] = isset( $address_data[ $prefix . 'company' ] ) ? sanitize_text_field( $address_data[ $prefix . 'company' ] ) : '';
		}

		if ( ! isset( $updated_address['email'] ) ) {
			$updated_address['email'] = isset( $address_data[ $prefix . 'email' ] ) ? sanitize_email( $address_data[ $prefix . 'email' ] ) : '';
		}

		if ( ! isset( $updated_address['phone'] ) ) {
			$updated_address['phone'] = isset( $address_data[ $prefix . 'phone' ] ) ? wc_sanitize_phone_number( $address_data[ $prefix . 'phone' ] ) : '';
		}

		if ( empty( $errors ) ) {

			if ( $updated_address === $original_address ) {
				wp_send_json_error(
					array(
						'message' => __( 'No changes detected.', 'happycoders-multiple-addresses' ),
						'reload'  => false,
					)
				);
			}

			$addresses[ $address_key ] = $updated_address;
			if ( hc_wcma_save_user_addresses( $user_id, $addresses, $address_type ) ) {

				$was_default = ( hc_wcma_get_default_address_key( $user_id, $address_type ) === $address_key );

				if ( $was_default ) {
					$prefix_core      = $address_type . '_';
					$standard_wc_keys = array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone', 'email' );
					foreach ( $standard_wc_keys as $field_key ) {
						if ( isset( $updated_address[ $field_key ] ) ) {
							$wp_meta_key = $prefix_core . $field_key;
							$value       = $updated_address[ $field_key ];
							update_user_meta( $user_id, $wp_meta_key, $value );
						}
					}
				}
				wp_send_json_success(
					array(
						'message' => __( 'Address updated successfully.', 'happycoders-multiple-addresses' ),
						'reload'  => true,
					)
				);
			} else {
				$errors[] = __( 'Failed to save updated address.', 'happycoders-multiple-addresses' );
				wp_send_json_error( array( 'message' => implode( '<br>', $errors ) ) );
			}
		} else {
			wp_send_json_error( array( 'message' => implode( '<br>', $errors ) ) );
		}
	}

	/**
	 * Handle deleting an address.
	 */
	public static function handle_delete_address() {

		$nonce_action     = 'hc_wcma_ajax_nonce';
		$nonce_field_name = 'nonce';

		$nonce_value = isset( $_POST[ $nonce_field_name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $nonce_field_name ] ) ) : '';

		if ( empty( $nonce_value ) || ! wp_verify_nonce( $nonce_value, $nonce_action ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'happycoders-multiple-addresses' ) . ' (Action: ' . esc_html( $nonce_action ) . ')' ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'happycoders-multiple-addresses' ) ), 401 );
		}
		$user_id = get_current_user_id();

		$address_key  = isset( $_POST['address_key'] ) ? sanitize_text_field( wp_unslash( $_POST['address_key'] ) ) : '';
		$address_type = isset( $_POST['address_type'] ) ? sanitize_text_field( wp_unslash( $_POST['address_type'] ) ) : '';

		if ( ! $address_key || ! $address_type || ! in_array( $address_type, array( 'billing', 'shipping' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing address identifier.', 'happycoders-multiple-addresses' ) ) );
		}

		$addresses = hc_wcma_get_user_addresses( $user_id, $address_type );

		if ( isset( $addresses[ $address_key ] ) ) {
			$was_default = ( hc_wcma_get_default_address_key( $user_id, $address_type ) === $address_key );

			unset( $addresses[ $address_key ] );

			$new_default_set = false;

			if ( $was_default ) {
				delete_user_meta( $user_id, '_hc_wcma_default_' . $address_type . '_key' );

				if ( ! empty( $addresses ) ) {
					if ( function_exists( 'array_key_first' ) ) {
						$new_default_key = array_key_first( $addresses );
					} else {
						reset( $addresses );
						$new_default_key = key( $addresses );
					}

					if ( $new_default_key ) {
						$new_default_set  = hc_wcma_set_default_address_key( $user_id, $new_default_key, $address_type );
						$prefix_core      = $address_type . '_';
						$standard_wc_keys = array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone', 'email' );
						$new_address      = hc_wcma_get_address_by_key( $user_id, $new_default_key, $address_type );
						foreach ( $standard_wc_keys as $field_key ) {
							if ( isset( $new_address[ $field_key ] ) ) {
								$wp_meta_key = $prefix_core . $field_key;
								$value       = $new_address[ $field_key ];
								update_user_meta( $user_id, $wp_meta_key, $value );
							}
						}
					}
				}
			}

			if ( hc_wcma_save_user_addresses( $user_id, $addresses, $address_type ) ) {
				wp_send_json_success(
					array(
						'message' => __( 'Address deleted.', 'happycoders-multiple-addresses' ) . ( $new_default_set ? ' ' . __( 'A new default address has been set.', 'happycoders-multiple-addresses' ) : '' ),
						'reload'  => true,
					)
				);
			} else {
				wp_send_json_error( array( 'message' => __( 'Could not delete address.', 'happycoders-multiple-addresses' ) ) );
			}
		} else {
			wp_send_json_error( array( 'message' => __( 'Address not found.', 'happycoders-multiple-addresses' ) ) );
		}
	}

	/**
	 * Handle setting an address as default.
	 */
	public static function handle_set_default_address() {
		$nonce_action     = 'hc_wcma_ajax_nonce';
		$nonce_field_name = 'nonce';

		$nonce_value = isset( $_POST[ $nonce_field_name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $nonce_field_name ] ) ) : '';

		if ( empty( $nonce_value ) || ! wp_verify_nonce( $nonce_value, $nonce_action ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'happycoders-multiple-addresses' ) . ' (Action: ' . esc_html( $nonce_action ) . ')' ), 403 );
		}

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in.', 'happycoders-multiple-addresses' ) ), 401 );
		}
		$user_id = get_current_user_id();

		$address_key  = isset( $_POST['address_key'] ) ? sanitize_text_field( wp_unslash( $_POST['address_key'] ) ) : '';
		$address_type = isset( $_POST['address_type'] ) ? sanitize_text_field( wp_unslash( $_POST['address_type'] ) ) : '';

		if ( ! $address_key || ! $address_type || ! in_array( $address_type, array( 'billing', 'shipping' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing address identifier.', 'happycoders-multiple-addresses' ) ) );
		}

		$address = hc_wcma_get_address_by_key( $user_id, $address_key, $address_type );
		if ( ! $address ) {
			wp_send_json_error( array( 'message' => __( 'Invalid address selected.', 'happycoders-multiple-addresses' ) ) );
		}

		if ( hc_wcma_set_default_address_key( $user_id, $address_key, $address_type ) ) {
			$prefix            = $address_type . '_';
			$updated_core_meta = false;

			$standard_wc_keys = array(
				'first_name',
				'last_name',
				'company',
				'address_1',
				'address_2',
				'city',
				'state',
				'postcode',
				'country',
				'phone',
				'email',
			);

			foreach ( $standard_wc_keys as $field_key ) {
				if ( isset( $address[ $field_key ] ) ) {
					$wp_meta_key = $prefix . $field_key;
					$value       = $address[ $field_key ];
					if ( update_user_meta( $user_id, $wp_meta_key, $value ) !== false ) {
						$updated_core_meta = true;
					}
				}
			}
			wp_send_json_success(
				array(
					'message' => sprintf(
					/* translators: %s is the address type */
						__( 'Default %s address updated.', 'happycoders-multiple-addresses' ),
						$address_type
					),
					'reload'  => true,
				)
			);
		} else {
			wp_send_json_error( array( 'message' => __( 'Could not update default address.', 'happycoders-multiple-addresses' ) ) );
		}
	}
}

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
	 * Hooks:
	 * - `woocommerce_settings_tabs_array` to add settings tab
	 * - `woocommerce_settings_tabs_hc_wcma` to render settings tab content
	 * - `woocommerce_update_options_hc_wcma` to update settings
	 * - `show_user_profile` and `edit_user_profile` to add address book section to user profile
	 * - `personal_options_update` and `edit_user_profile_update` to save address book changes
	 * - `wp_ajax_hc_wcma_admin_delete_address` to handle AJAX address deletion (example)
	 */
	public static function init() {
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
	public static function add_settings_tab( $settings_tabs ) {
		$settings_tabs['hc_wcma'] = __( 'HC Multiple Addresses', 'happycoders-multiple-addresses' );
		return $settings_tabs;
	}

	/**
	 * Display settings content.
	 */
	public static function settings_tab_content() {
		woocommerce_admin_fields( self::get_settings() );
	}

	/**
	 * Save settings.
	 */
	public static function update_settings() {
		woocommerce_update_options( self::get_settings() );
	}

	/**
	 * Define settings fields.
	 */
	public static function get_settings() {
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
			// --- NEW SETTING 1 ---
			'checkout_saved_address_display' => array(
				'name'     => __( 'Saved Address Display Format', 'happycoders-multiple-addresses' ),
				'type'     => 'select',
				'desc'     => __( 'For Classic Checkout: When a saved address is selected, show the full editable form fields or just a formatted address block?', 'happycoders-multiple-addresses' ),
				'id'       => 'hc_wcma_checkout_saved_address_display',
				'options'  => array(
					'fields' => __( 'Show Editable Form Fields', 'happycoders-multiple-addresses' ),
					'block'  => __( 'Show Formatted Address Block (Hide Fields)', 'happycoders-multiple-addresses' ),
				),
				'default'  => 'block', // Default to the new block view? Or 'fields' for backward compatibility? Let's choose block.
				'desc_tip' => true,
			),
			// --- NEW SETTING 2 ---
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

			// --- NEW SECTION FOR LIMITS ---
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
			// --- END NEW SECTION ---
		);
		return apply_filters( 'hc_wcma_settings', $settings );
	}

	/**
	 * Displays the user address book in the WordPress admin user profile page.
	 *
	 * Displays billing and shipping addresses and provides buttons to edit and delete them.
	 * Also displays "Add New" buttons for both types of addresses.
	 *
	 * @param WP_User $user The user object to display the address book for.
	 * @return void
	 */
	public static function display_user_address_book( $user ) {
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}

		$user_id              = $user->ID;
		$billing_addresses    = hc_wcma_get_user_addresses( $user_id, 'billing' );
		$shipping_addresses   = hc_wcma_get_user_addresses( $user_id, 'shipping' );
		$default_billing_key  = hc_wcma_get_default_address_key( $user_id, 'billing' );
		$default_shipping_key = hc_wcma_get_default_address_key( $user_id, 'shipping' );

		?>
		<h2><?php esc_html_e( 'Customer Address Book', 'happycoders-multiple-addresses' ); ?></h2>
		<input type="hidden" name="hc_wcma_admin_user_id" value="<?php echo esc_attr( $user_id ); ?>">
		<?php wp_nonce_field( 'hc_wcma_admin_save_user_' . $user_id, 'hc_wcma_admin_nonce' ); ?>

		<table class="form-table hc-wcma-admin-addresses">
			<tbody>
				<tr>
					<th><h3><?php esc_html_e( 'Billing Addresses', 'happycoders-multiple-addresses' ); ?></h3></th>
					<td>
						<?php if ( ! empty( $billing_addresses ) ) : ?>
							<ul>
							<?php foreach ( $billing_addresses as $key => $address ) : ?>
								<li data-address-key="<?php echo esc_attr( $key ); ?>" data-address-type="billing">
									<strong><?php echo esc_html( $address['nickname'] ?? $key ); ?></strong>
									<?php
									if ( $key === $default_billing_key ) {
										echo ' <span class="default">(Default)</span>';}
									?>
									<br>
									<address><?php echo wp_kses_post( hc_wcma_format_address_for_display( $address ) ); ?></address>
									<button type="button" class="button button-secondary hc-wcma-admin-edit-address"><?php esc_html_e( 'Edit', 'happycoders-multiple-addresses' ); ?></button>
									<button type="button" class="button button-link-delete hc-wcma-admin-delete-address"><?php esc_html_e( 'Delete', 'happycoders-multiple-addresses' ); ?></button>
									<div class="hc-wcma-admin-edit-form" style="display:none; border: 1px solid #ccc; padding: 10px; margin-top: 10px;">
										<h4>Edit Billing Address</h4>
										<?php
										$billing_fields = hc_wcma_get_address_fields( 'billing' );
										foreach ( $billing_fields as $field_key => $field ) {
											$field_name  = "hc_wcma_billing[{$key}][{$field_key}]";
											$field_id    = "hc_wcma_billing_{$key}_{$field_key}";
											$field_value = $address[ $field_key ] ?? '';
											echo '<p><label for="' . esc_attr( $field_id ) . '">' . esc_html( $field['label'] ) . '</label><br/>';
											echo '<input type="text" name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_id ) . '" value="' . esc_attr( $field_value ) . '" class="regular-text"></p>';
										}
										?>
										<button type="submit" name="hc_wcma_save_user_profile" class="button button-primary"><?php esc_html_e( 'Save Changes', 'happycoders-multiple-addresses' ); ?> (Needs page reload)</button>
										<button type="button" class="button hc-wcma-admin-cancel-edit"><?php esc_html_e( 'Cancel', 'happycoders-multiple-addresses' ); ?></button>
									</div>
								</li>
							<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p><?php esc_html_e( 'No saved billing addresses found.', 'happycoders-multiple-addresses' ); ?></p>
						<?php endif; ?>
						<button type="button" class="button hc-wcma-admin-add-address" data-address-type="billing"><?php esc_html_e( 'Add New Billing Address', 'happycoders-multiple-addresses' ); ?></button>
						<!-- Add form for new address (hidden) -->
					</td>
				</tr>
				<tr>
					<th><h3><?php esc_html_e( 'Shipping Addresses', 'happycoders-multiple-addresses' ); ?></h3></th>
					<td>
						<?php if ( ! empty( $shipping_addresses ) ) : ?>
							<ul>
							<?php foreach ( $shipping_addresses as $key => $address ) : ?>
								<li data-address-key="<?php echo esc_attr( $key ); ?>" data-address-type="shipping">
									<strong><?php echo esc_html( $address['nickname'] ?? $key ); ?></strong>
									<?php
									if ( $key === $default_shipping_key ) {
										echo ' <span class="default">(Default)</span>';}
									?>
									<br>
									<address><?php echo wp_kses_post( hc_wcma_format_address_for_display( $address ) ); ?></address>
									<button type="button" class="button button-secondary hc-wcma-admin-edit-address"><?php esc_html_e( 'Edit', 'happycoders-multiple-addresses' ); ?></button>
									<button type="button" class="button button-link-delete hc-wcma-admin-delete-address"><?php esc_html_e( 'Delete', 'happycoders-multiple-addresses' ); ?></button>
									<!-- Add edit form fields here (hidden initially) -->
									<div class="hc-wcma-admin-edit-form" style="display:none; border: 1px solid #ccc; padding: 10px; margin-top: 10px;">
										<h4>Edit Shipping Address</h4>
											<?php
											$shipping_fields = hc_wcma_get_address_fields( 'shipping' );
											foreach ( $shipping_fields as $field_key => $field ) {
												$field_name  = "hc_wcma_shipping[{$key}][{$field_key}]";
												$field_id    = "hc_wcma_shipping_{$key}_{$field_key}";
												$field_value = $address[ $field_key ] ?? '';
												echo '<p><label for="' . esc_attr( $field_id ) . '">' . esc_html( $field['label'] ) . '</label><br/>';
												echo '<input type="text" name="' . esc_attr( $field_name ) . '" id="' . esc_attr( $field_id ) . '" value="' . esc_attr( $field_value ) . '" class="regular-text"></p>';
											}
											?>
										<button type="submit" name="hc_wcma_save_user_profile" class="button button-primary"><?php esc_html_e( 'Save Changes', 'happycoders-multiple-addresses' ); ?> (Needs page reload)</button>
										<button type="button" class="button hc-wcma-admin-cancel-edit"><?php esc_html_e( 'Cancel', 'happycoders-multiple-addresses' ); ?></button>
									</div>
								</li>
							<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<p><?php esc_html_e( 'No saved shipping addresses found.', 'happycoders-multiple-addresses' ); ?></p>
						<?php endif; ?>
						<button type="button" class="button hc-wcma-admin-add-address" data-address-type="shipping"><?php esc_html_e( 'Add New Shipping Address', 'happycoders-multiple-addresses' ); ?></button>
						<!-- Add form for new address (hidden) -->
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Saves the user's address book (billing and shipping) via the admin profile page.
	 *
	 * Currently, this function only handles updating existing addresses via the
	 * displayed edit forms. It does not handle adding new addresses or deleting
	 * existing ones. Deletion needs AJAX or separate flags. Add/New address saving
	 * needs implementation.
	 *
	 * @param int $user_id The ID of the user whose address book is being saved.
	 */
	public static function save_user_address_book( $user_id ) {
		if ( ! isset( $_POST['hc_wcma_admin_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hc_wcma_admin_nonce'] ) ), 'hc_wcma_admin_save_user_' . $user_id ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hc_wcma_admin_nonce'] ) ), 'hc_wcma_admin_save_user_' . $user_id ) ) {
				return;
		}
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
				return;
		}

		// --- Save Billing Addresses ---
		if ( isset( $_POST['hc_wcma_billing'] ) && is_array( $_POST['hc_wcma_billing'] ) ) {
			$current_billing = hc_wcma_get_user_addresses( $user_id, 'billing' );
			$posted_billing  = array_map( 'sanitize_text_field', wp_unslash( $_POST['hc_wcma_billing'] ) );
			$updated_billing = array();

			foreach ( $posted_billing as $key => $address_data ) {
				if ( isset( $current_billing[ $key ] ) ) {
					$sanitized_data = array();
					$billing_fields = hc_wcma_get_address_fields( 'billing' );
					foreach ( $billing_fields as $field_key => $config ) {
						$posted_value = $address_data[ $field_key ] ?? '';
						if ( 'email' === $field_key ) {
							$sanitized_data[ $field_key ] = sanitize_email( $posted_value );
						} elseif ( 'phone' === $field_key ) {
							$sanitized_data[ $field_key ] = wc_sanitize_phone_number( $posted_value );
						} else {
							$sanitized_data[ $field_key ] = sanitize_text_field( $posted_value );
						}
					}
					$updated_billing[ $key ] = $sanitized_data;
				}
			}
			// This logic only updates existing ones via POST. Deletion needs AJAX or separate flags.
			// Add/New address saving needs implementation.
			// This example only handles the *update* part from the displayed edit forms.
			$final_billing = array_replace( $current_billing, $updated_billing );
			hc_wcma_save_user_addresses( $user_id, $final_billing, 'billing' );
		}

		// --- Save Shipping Addresses (Similar logic) ---
		if ( isset( $_POST['hc_wcma_shipping'] ) && is_array( $_POST['hc_wcma_shipping'] ) ) {
			$current_shipping = hc_wcma_get_user_addresses( $user_id, 'shipping' );
			$posted_shipping  = array_map( 'sanitize_text_field', wp_unslash( $_POST['hc_wcma_shipping'] ) );
			$updated_shipping = array();

			foreach ( $posted_shipping as $key => $address_data ) {
				if ( isset( $current_shipping[ $key ] ) ) {
					$sanitized_data = array();
					foreach ( hc_wcma_get_address_fields( 'shipping' ) as $field_key => $config ) {
						$sanitized_data[ $field_key ] = isset( $address_data[ $field_key ] ) ? sanitize_text_field( $address_data[ $field_key ] ) : '';
					}
					$updated_shipping[ $key ] = $sanitized_data;
				}
			}
			$final_shipping = array_replace( $current_shipping, $updated_shipping );
			hc_wcma_save_user_addresses( $user_id, $final_shipping, 'shipping' );
		}
	}

	/**
	 * AJAX handler for admin deleting an address.
	 */
	public static function ajax_admin_delete_address() {
		check_ajax_referer( 'hc_wcma_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_users' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'happycoders-multiple-addresses' ) ) );
		}

		$user_id      = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
		$address_key  = isset( $_POST['address_key'] ) ? sanitize_text_field( wp_unslash( $_POST['address_key'] ) ) : '';
		$address_type = isset( $_POST['address_type'] ) ? sanitize_text_field( wp_unslash( $_POST['address_type'] ) ) : '';

		if ( ! $user_id || ! $address_key || ! in_array( $address_type, array( 'billing', 'shipping' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data provided.', 'happycoders-multiple-addresses' ) ) );
		}

		$addresses = hc_wcma_get_user_addresses( $user_id, $address_type );

		if ( isset( $addresses[ $address_key ] ) ) {
			unset( $addresses[ $address_key ] );

			$default_key = hc_wcma_get_default_address_key( $user_id, $address_type );
			if ( $default_key === $address_key ) {
				delete_user_meta( $user_id, '_hc_wcma_default_' . $address_type . '_key' );
			}

			if ( hc_wcma_save_user_addresses( $user_id, $addresses, $address_type ) ) {
				wp_send_json_success( array( 'message' => __( 'Address deleted successfully.', 'happycoders-multiple-addresses' ) ) );
			} else {
				wp_send_json_error( array( 'message' => __( 'Failed to delete address.', 'happycoders-multiple-addresses' ) ) );
			}
		} else {
			wp_send_json_error( array( 'message' => __( 'Address not found.', 'happycoders-multiple-addresses' ) ) );
		}
	}
}

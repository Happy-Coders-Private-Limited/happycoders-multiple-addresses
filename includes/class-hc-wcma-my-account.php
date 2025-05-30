<?php
/**
 * Class HC_WCMA_My_Account
 *
 * @package happycoders-multiple-addresses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class HC_WCMA_My_Account
 */
class HC_WCMA_My_Account {

	/**
	 * Initializes the plugin. Hooks into the following:
	 * - Adds a menu item to the My Account page
	 * - Adds content for the endpoint
	 * - Adds query vars if needed (though endpoint usually handles it)
	 */
	public static function init() {
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'add_menu_item' ) );
		add_action( 'woocommerce_account_' . HC_WCMA_ENDPOINT_SLUG . '_endpoint', array( __CLASS__, 'render_endpoint_content' ) );
		add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ), 0 );
	}

	/**
	 * Register the new endpoint.
	 */
	public static function add_endpoint() {
		add_rewrite_endpoint( HC_WCMA_ENDPOINT_SLUG, EP_ROOT | EP_PAGES );
	}

	/**
	 * Add query vars.
	 *
	 * @param array $vars Query vars.
	 * @return array Query vars.
	 */
	public static function add_query_vars( $vars ) {
		$vars[] = HC_WCMA_ENDPOINT_SLUG;
		return $vars;
	}

	/**
	 * Add menu item.
	 *
	 * @param array $items Menu items.
	 * @return array Menu items.
	 */
	public static function add_menu_item( $items ) {
		$endpoint = HC_WCMA_ENDPOINT_SLUG;
		$new_item = array( $endpoint => __( 'Multi Address Book', 'happycoders-multiple-addresses' ) );

		$items = array_slice( $items, 0, 3, true ) + $new_item + array_slice( $items, 3, null, true );

		unset( $items['edit-address'] );

		return $items;
	}

	/**
	 * Render the content for the Address Book endpoint.
	 */
	public static function render_endpoint_content() {
		$user_id = get_current_user_id();

		// --- Get Addresses and Defaults ---
		$all_billing_addresses  = hc_wcma_get_user_addresses( $user_id, 'billing' );
		$all_shipping_addresses = hc_wcma_get_user_addresses( $user_id, 'shipping' );
		$default_billing_key    = hc_wcma_get_default_address_key( $user_id, 'billing' );
		$default_shipping_key   = hc_wcma_get_default_address_key( $user_id, 'shipping' );

		// --- Function to Reorder Addresses ---
		$reorder_addresses = function ( $addresses, $default_key ) {
			if ( empty( $addresses ) ) {
				return array();
			}
			if ( ! empty( $default_key ) && isset( $addresses[ $default_key ] ) ) {
				$default_address = array( $default_key => $addresses[ $default_key ] );
				unset( $addresses[ $default_key ] );
				return $default_address + $addresses;
			}
			return $addresses;
		};

		$ordered_billing_addresses  = $reorder_addresses( $all_billing_addresses, $default_billing_key );
		$ordered_shipping_addresses = $reorder_addresses( $all_shipping_addresses, $default_shipping_key );

		echo '<div id="hc_wcma_limit_notice" class="woocommerce-info" style="display: none;"></div>';

		echo '<h2>' . esc_html__( 'Add New Address', 'happycoders-multiple-addresses' ) . '</h2>';
		echo '<div id="hc_wcma_add_address_fields_wrapper">';
		echo '<form id="hc_wcma_add_address_form" class="hc-wcma-address-form woocommerce-form">';

		echo '<p class="form-row form-row-wide"><label for="hc_wcma_address_type">' . esc_html__( 'Address Type', 'happycoders-multiple-addresses' ) . ' <abbr class="required" title="required">*</abbr></label>
        <select name="hc_wcma_address_type" id="hc_wcma_address_type" required><option value="">' . esc_html__( '-- Select Type --', 'happycoders-multiple-addresses' ) . '</option><option value="billing">' . esc_html__( 'Billing', 'happycoders-multiple-addresses' ) . '</option><option value="shipping">' . esc_html__( 'Shipping', 'happycoders-multiple-addresses' ) . '</option></select></p>';

		$billing_fields  = WC()->countries->get_address_fields( '', 'billing_' );
		$shipping_fields = WC()->countries->get_address_fields( '', 'shipping_' );

		$company_field_props = array(
			'label'    => __( 'Company', 'happycoders-multiple-addresses' ),
			'class'    => array( 'form-row-wide' ),
			'priority' => 30,
		);
		$email_field_props   = array(
			'label'    => __( 'Email address', 'happycoders-multiple-addresses' ),
			'type'     => 'email',
			'class'    => array( 'form-row-wide' ),
			'validate' => array( 'email' ),
			'priority' => 100,
		);
		$phone_field_props   = array(
			'label'    => __( 'Phone', 'happycoders-multiple-addresses' ),
			'type'     => 'tel',
			'class'    => array( 'form-row-wide' ),
			'validate' => array( 'phone' ),
			'priority' => 110,
		);

		if ( ! isset( $billing_fields['billing_company'] ) ) {
			$billing_fields['billing_company'] = $company_field_props;
		}

		uasort( $billing_fields, 'wc_checkout_fields_uasort_comparison' );

		if ( ! isset( $shipping_fields['shipping_company'] ) ) {
			$shipping_fields['shipping_company'] = $company_field_props;
		}
		if ( ! isset( $shipping_fields['shipping_email'] ) ) {
			$shipping_fields['shipping_email'] = $email_field_props;
		}
		if ( ! isset( $shipping_fields['shipping_phone'] ) ) {
			$shipping_fields['shipping_phone'] = $phone_field_props;
		}

		uasort( $shipping_fields, 'wc_checkout_fields_uasort_comparison' );

		$customer = WC()->customer;

		$nickname_field = array(
			'label'       => __( 'Address Nickname', 'happycoders-multiple-addresses' ),
			'placeholder' => __( 'e.g., Home, Work', 'happycoders-multiple-addresses' ),
			'required'    => false,
			'class'       => array( 'form-row-wide' ),
			'priority'    => 5,
			'type'        => 'text',
		);

		echo '<div class="hc_wcma_fields hc_wcma_billing_fields">';
		echo '<h3>' . esc_attr( __( 'Billing Details', 'happycoders-multiple-addresses' ) ) . '</h3>';
		woocommerce_form_field( 'billing_nickname', $nickname_field, '' );
		foreach ( $billing_fields as $key => $field ) {
			$clean_key = str_replace( 'billing_', '', $key );
			if ( empty( $field['label'] ) ) {
				$field['label'] = ucfirst( str_replace( '_', ' ', $clean_key ) );
			}

			woocommerce_form_field( $key, $field, '' );
		}
		echo '</div>';

		echo '<div class="hc_wcma_fields hc_wcma_shipping_fields" style="display:none;">';
		echo '<h3>' . esc_attr( __( 'Shipping Details', 'happycoders-multiple-addresses' ) ) . '</h3>';
		woocommerce_form_field( 'shipping_nickname', $nickname_field, '' );
		foreach ( $shipping_fields as $key => $field ) {
			$clean_key = str_replace( 'shipping_', '', $key );
			if ( empty( $field['label'] ) ) {
				$field['label'] = ucfirst( str_replace( '_', ' ', $clean_key ) );
			}

			woocommerce_form_field( $key, $field, '' );
		}
		echo '</div>';

		wp_nonce_field( 'hc_wcma_save_address_action', 'hc_wcma_save_address_nonce' );
		echo '<p><button type="submit" class="button wp-element-button" name="save_address" value="' . esc_attr__( 'Save Address', 'happycoders-multiple-addresses' ) . '">' . esc_html__( 'Save Address', 'happycoders-multiple-addresses' ) . '</button></p>';
		echo '</form>';
		echo '<div id="hc_wcma_form_feedback"></div>';
		echo '</div>';

		wc_enqueue_js(
			"
            jQuery(function($) {
                var wrapper = $('#hc_wcma_add_address_fields_wrapper');
                var billing_fields = wrapper.find('.hc_wcma_billing_fields');
                var shipping_fields = wrapper.find('.hc_wcma_shipping_fields');

                $('#hc_wcma_address_type').on('change', function() {
                    var selected_type = $(this).val();
                    billing_fields.hide();
                    shipping_fields.hide();

                    if (selected_type === 'billing' || selected_type === 'both') {
                        billing_fields.show();
                    }
                    if (selected_type === 'shipping' || selected_type === 'both') {
                        shipping_fields.show();
                    }
                    // Trigger country change handler in case fields were hidden
                    $(document.body).trigger('country_to_state_changed', ['billing', wrapper]);
                    $(document.body).trigger('country_to_state_changed', ['shipping', wrapper]);

                }).trigger('change'); // Trigger on load if needed

                // Ensure WC init runs on these fields after potential display changes
                $(document.body).trigger('wc_address_i18n_ready');
                $(document.body).trigger('wc_country_select_ready');
            });
        "
		);

		echo '<hr/>';

		echo '<h2>' . esc_html__( 'Saved Billing Addresses', 'happycoders-multiple-addresses' ) . '</h2>';
		if ( ! empty( $ordered_billing_addresses ) ) {
			echo '<div class="hc-wcma-address-carousel" id="billing-address-carousel">';
			echo '<div class="swiper-wrapper">';
			foreach ( $ordered_billing_addresses as $key => $address ) {
				echo '<div class="swiper-slide">';
				echo '<div class="hc-wcma-address-card woocommerce-Address" data-address-key="' . esc_attr( $key ) . '" data-address-type="billing">';
				echo '<h3>' . esc_html( $address['nickname'] ?? __( 'Billing Address', 'happycoders-multiple-addresses' ) );
				if ( $key === $default_billing_key ) {
					echo '<span class="hc-wcma-default-badge">' . esc_html__( 'Default', 'happycoders-multiple-addresses' ) . '</span>';
				}
				echo '</h3>';
				echo '<address>';
				echo wp_kses_post( hc_wcma_format_address_for_display( $address ) );
				echo '</address>';
				echo '<div class="hc-wcma-actions">';

				echo '<button class="button wp-element-button hc-wcma-edit-button" data-address=\'' . esc_attr( wp_json_encode( $address ) ) . '\'>' . esc_html__( 'Edit', 'happycoders-multiple-addresses' ) . '</button> ';
				echo '<button class="button wp-element-button hc-wcma-delete-button">' . esc_html__( 'Delete', 'happycoders-multiple-addresses' ) . '</button> ';
				if ( $key !== $default_billing_key ) {
					echo '<button class="button wp-element-button hc-wcma-set-default-button">' . esc_html__( 'Set as Default', 'happycoders-multiple-addresses' ) . '</button>';
				}
				echo '</div>';
				echo '</div>';
				echo '</div>';
			}
			echo '</div>';
			echo '<div class="swiper-button-prev billing-swiper-button-prev"></div>';
			echo '<div class="swiper-button-next billing-swiper-button-next"></div>';
			echo '</div>';
		} else {
			wc_print_notice( __( 'You have no saved billing addresses.', 'happycoders-multiple-addresses' ), 'notice' );
		}

		echo '<h2>' . esc_html__( 'Saved Shipping Addresses', 'happycoders-multiple-addresses' ) . '</h2>';
		if ( ! empty( $ordered_shipping_addresses ) ) {
			echo '<div class="hc-wcma-address-carousel" id="shipping-address-carousel">';
			echo '<div class="swiper-wrapper">';
			foreach ( $ordered_shipping_addresses as $key => $address ) {
				echo '<div class="swiper-slide">';
				echo '<div class="hc-wcma-address-card woocommerce-Address" data-address-key="' . esc_attr( $key ) . '" data-address-type="shipping">';
				echo '<h3>' . esc_html( $address['nickname'] ?? __( 'Shipping Address', 'happycoders-multiple-addresses' ) );
				if ( $key === $default_shipping_key ) {
					echo '<span class="hc-wcma-default-badge">' . esc_html__( 'Default', 'happycoders-multiple-addresses' ) . '</span>';
				}
				echo '</h3>';
				echo '<address>';
				echo wp_kses_post( hc_wcma_format_address_for_display( $address ) );
				echo '</address>';
				echo '<div class="hc-wcma-actions">';
				echo '<button class="button wp-element-button hc-wcma-edit-button" data-address=\'' . esc_attr( wp_json_encode( $address ) ) . '\'>' . esc_html__( 'Edit', 'happycoders-multiple-addresses' ) . '</button> ';
				echo '<button class="button wp-element-button hc-wcma-delete-button">' . esc_html__( 'Delete', 'happycoders-multiple-addresses' ) . '</button> ';
				if ( $key !== $default_shipping_key ) {
					echo '<button class="button wp-element-button hc-wcma-set-default-button">' . esc_html__( 'Set as Default', 'happycoders-multiple-addresses' ) . '</button>';
				}
				echo '</div>';
				echo '</div>';
				echo '</div>';
			}
			echo '</div>';
			echo '<div class="swiper-button-prev shipping-swiper-button-prev"></div>';
			echo '<div class="swiper-button-next shipping-swiper-button-next"></div>';
			echo '</div>';
		} else {
			wc_print_notice( __( 'You have no saved shipping addresses.', 'happycoders-multiple-addresses' ), 'notice' );
		}

		self::render_edit_modal();
	}

	/**
	 * Renders the edit modal for addresses in the My Account section.
	 *
	 * Retrieves and processes billing and shipping address fields from WooCommerce,
	 * adding company, email, and phone fields if not already present. Displays a
	 * modal form for editing address details, including nickname fields for both
	 * billing and shipping. The form includes a nonce field for security and
	 * buttons to update the address or cancel the operation.
	 */
	public static function render_edit_modal() {
		$billing_fields  = WC()->countries->get_address_fields( '', 'billing_' );
		$shipping_fields = WC()->countries->get_address_fields( '', 'shipping_' );

		$company_field_props = array(
			'label'    => __( 'Company', 'happycoders-multiple-addresses' ),
			'class'    => array( 'form-row-wide' ),
			'priority' => 30,
		);
		$email_field_props   = array(
			'label'    => __( 'Email address', 'happycoders-multiple-addresses' ),
			'type'     => 'email',
			'class'    => array( 'form-row-wide' ),
			'validate' => array( 'email' ),
			'priority' => 100,
		); // Note: Required? WC default usually is.
		$phone_field_props   = array(
			'label'    => __( 'Phone', 'happycoders-multiple-addresses' ),
			'type'     => 'tel',
			'class'    => array( 'form-row-wide' ),
			'validate' => array( 'phone' ),
			'priority' => 110,
		);

		if ( ! isset( $billing_fields['billing_company'] ) ) {
			$billing_fields['billing_company'] = $company_field_props;
		}

		uasort( $billing_fields, 'wc_checkout_fields_uasort_comparison' );

		if ( ! isset( $shipping_fields['shipping_company'] ) ) {
			$shipping_fields['shipping_company'] = $company_field_props;
		}
		if ( ! isset( $shipping_fields['shipping_email'] ) ) {
			$shipping_fields['shipping_email'] = $email_field_props;
		}
		if ( ! isset( $shipping_fields['shipping_phone'] ) ) {
			$shipping_fields['shipping_phone'] = $phone_field_props;
		}

		uasort( $shipping_fields, 'wc_checkout_fields_uasort_comparison' );

		$nickname_field = array( /* ... same as in add form ... */
			'label'    => __( 'Address Nickname', 'happycoders-multiple-addresses' ),
			'required' => false,
			'class'    => array( 'form-row-wide' ),
			'priority' => 5,
			'type'     => 'text',
		);

		?>
		<div id="hc_wcma_edit_modal_overlay"></div>
		<div id="hc_wcma_edit_modal">
			<h2><?php esc_html_e( 'Edit Address', 'happycoders-multiple-addresses' ); ?></h2>
			<form id="hc_wcma_edit_address_form" class="hc-wcma-address-form woocommerce-form">
				<input type="hidden" name="hc_wcma_address_key" id="hc_wcma_edit_address_key" value="">
				<input type="hidden" name="hc_wcma_address_type" id="hc_wcma_edit_address_type" value="">

				<div class="hc_wcma_edit_fields hc_wcma_edit_billing_fields">
					<h3><?php esc_html_e( 'Billing Details', 'happycoders-multiple-addresses' ); ?></h3>
					<?php woocommerce_form_field( 'billing_nickname', $nickname_field, '' ); ?>
					<?php
					foreach ( $billing_fields as $key => $field ) :
						if ( empty( $field['label'] ) ) {
							$field['label'] = ucfirst( str_replace( '_', ' ', str_replace( 'billing_', '', $key ) ) );
						}
						?>
						<?php woocommerce_form_field( $key, $field, '' ); ?>
					<?php endforeach; ?>
				</div>
				<div class="hc_wcma_edit_fields hc_wcma_edit_shipping_fields">
					<h3><?php esc_html_e( 'Shipping Details', 'happycoders-multiple-addresses' ); ?></h3>
					<?php woocommerce_form_field( 'shipping_nickname', $nickname_field, '' ); ?>
					<?php
					foreach ( $shipping_fields as $key => $field ) :
						if ( empty( $field['label'] ) ) {
							$field['label'] = ucfirst( str_replace( '_', ' ', str_replace( 'shipping_', '', $key ) ) );
						}
						?>
						<?php woocommerce_form_field( $key, $field, '' ); ?>
					<?php endforeach; ?>
				</div>

				<?php wp_nonce_field( 'hc_wcma_save_address_action', 'hc_wcma_edit_address_nonce' ); ?>
				<p>
					<button type="submit" class="button wp-element-button" name="update_address" value="<?php esc_attr_e( 'Update Address', 'happycoders-multiple-addresses' ); ?>"><?php esc_html_e( 'Update Address', 'happycoders-multiple-addresses' ); ?></button>
					<button type="button" class="button wp-element-button" id="hc_wcma_edit_modal_close"><?php esc_html_e( 'Cancel', 'happycoders-multiple-addresses' ); ?></button>
				</p>
			</form>
			<div id="hc_wcma_edit_form_feedback"></div>
		</div>
		<?php
	}
}

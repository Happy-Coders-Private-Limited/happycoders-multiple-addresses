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
 * Main HappyCoders Multiple Addresses Plugin Class.
 *
 * @since 1.0.0
 */
final class HC_WCMA_Main {

	/**
	 * The single instance of the class.
	 *
	 * @since 1.0.0
	 * @var   HC_WCMA_Main|null $instance
	 * @static
	 * @access private
	 */
	private static ?HC_WCMA_Main $instance = null;

	/**
	 * Plugin version.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $version = HC_WCMA_VERSION;

	/**
	 * Slug for the address book endpoint in My Account.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public $address_book_endpoint = HC_WCMA_ENDPOINT_SLUG;

	/**
	 * Get the single instance of HC_WCMA_Main.
	 *
	 * @since 1.0.0
	 * @return HC_WCMA_Main
	 */
	public static function instance(): HC_WCMA_Main {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initializes the plugin by including the required files and hooking actions and filters.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Includes the required PHP files.
	 *
	 * @since 1.0.0
	 */
	private function includes(): void {
		include_once HC_WCMA_PLUGIN_PATH . 'includes/class-hc-wcma-my-account.php';
		include_once HC_WCMA_PLUGIN_PATH . 'includes/class-hc-wcma-checkout.php';
		include_once HC_WCMA_PLUGIN_PATH . 'includes/class-hc-wcma-admin.php';
		include_once HC_WCMA_PLUGIN_PATH . 'includes/class-hc-wcma-ajax.php';
		include_once HC_WCMA_PLUGIN_PATH . 'includes/hc-wcma-functions.php';
		include_once HC_WCMA_PLUGIN_PATH . 'includes/hc-wcma-blocks.php';
	}

	/**
	 * Initializes the hooks.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks(): void {
		add_action( 'plugins_loaded', 'hc_wcma_init' );
		add_action( 'init', array( 'HC_WCMA_My_Account', 'add_endpoint' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		HC_WCMA_My_Account::init();
		HC_WCMA_Checkout::init();
		HC_WCMA_Admin::init();
		HC_WCMA_AJAX::init();
	}

	/**
	 * Enqueues the necessary scripts and styles for the plugin's frontend.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function enqueue_scripts(): void {
		global $wp;
		$is_my_account_page       = is_account_page();
		$is_address_book_endpoint = $is_my_account_page && isset( $wp->query_vars[ $this->address_book_endpoint ] );

		if ( $is_my_account_page && $is_address_book_endpoint ) {
			wp_enqueue_style( 'hc-wcma-my-account', HC_WCMA_PLUGIN_URL . 'assets/css/my-account.css', array(), $this->version . time() );
			wp_enqueue_script( 'wc-country-select' );
			wp_enqueue_script( 'wc-address-i18n' );
			wp_enqueue_style( 'swiper-css', HC_WCMA_PLUGIN_URL . 'assets/vendor/swiper/swiper-bundle.min.css', array(), '11.2.6' );
			wp_enqueue_script( 'swiper-js', HC_WCMA_PLUGIN_URL . 'assets/vendor/swiper/swiper-bundle.min.js', array(), '11.2.6', true );

			wp_enqueue_script( 'hc-wcma-my-account-js', HC_WCMA_PLUGIN_URL . 'assets/js/my-account.js', array( 'jquery', 'wp-util', 'swiper-js' ), $this->version . time(), true );

			$user_id        = get_current_user_id();
			$billing_count  = count( hc_wcma_get_user_addresses( $user_id, 'billing' ) );
			$shipping_count = count( hc_wcma_get_user_addresses( $user_id, 'shipping' ) );
			$billing_limit  = (int) get_option( 'hc_wcma_limit_max_billing_addresses', 0 );
			$shipping_limit = (int) get_option( 'hc_wcma_limit_max_shipping_addresses', 0 );
			wp_localize_script(
				'hc-wcma-my-account-js',
				'hc_wcma_params',
				array(
					'ajax_url'          => admin_url( 'admin-ajax.php' ),
					'nonce'             => wp_create_nonce( 'hc_wcma_ajax_nonce' ),
					'i18n'              => array(
						'delete_confirm' => __( 'Are you sure you want to delete this address?', 'happycoders-multiple-addresses' ),
						'error'          => __( 'An error occurred. Please try again.', 'happycoders-multiple-addresses' ),
					),
					'limits'            => array(
						'billing'  => $billing_limit,
						'shipping' => $shipping_limit,
					),
					'counts'            => array(
						'billing'  => $billing_count,
						'shipping' => $shipping_count,
					),
					/* translators: %limit% is the numeric limit, %type% is 'billing' or 'shipping' */
					'limit_message_tpl' => __( 'You have reached the maximum (%limit%) saved %type% addresses.', 'happycoders-multiple-addresses' ),
				)
			);
		}

		if ( is_checkout() && ! is_order_received_page() && ! hc_wcma_is_checkout_block() ) {

			wp_enqueue_style( 'hc-wcma-checkout', HC_WCMA_PLUGIN_URL . 'assets/css/checkout.css', array(), $this->version );
			wp_enqueue_script( 'hc-wcma-checkout-js', HC_WCMA_PLUGIN_URL . 'assets/js/checkout.js', array( 'jquery', 'wc-checkout', 'selectWoo' ), $this->version, true );

			$user_id = get_current_user_id();

			$checkout_addresses = array(
				'billing'  => array(),
				'shipping' => array(),
			);

			$billing_addresses  = hc_wcma_get_user_addresses( $user_id, 'billing' );
			$shipping_addresses = hc_wcma_get_user_addresses( $user_id, 'shipping' );

			foreach ( $billing_addresses as $key => $addr ) {
				$checkout_addresses['billing'][ $key ] = $addr;
			}
			foreach ( $shipping_addresses as $key => $addr ) {
				$checkout_addresses['shipping'][ $key ] = $addr;
			}

			$selector_style = get_option( 'hc_wcma_checkout_selector_style', 'dropdown' );
			$saved_display  = get_option( 'hc_wcma_checkout_saved_address_display', 'block' );
			$allow_new      = get_option( 'hc_wcma_checkout_allow_new_address', 'yes' );

			$checkout_params = array(
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'hc_wcma_checkout_nonce' ),
				'addresses'      => $checkout_addresses,
				'selector_style' => $selector_style,
				'saved_display'  => $saved_display,
				'allow_new'      => $allow_new,
				'i18n'           => array(
					'select_billing'  => __( 'Select a billing address', 'happycoders-multiple-addresses' ),
					'select_shipping' => __( 'Select a shipping address', 'happycoders-multiple-addresses' ),
					'new_address'     => __( 'Enter a new address', 'happycoders-multiple-addresses' ),
				),
				'existing_nicknames' => array(
					'billing'  => array_column( $billing_addresses, 'nickname' ),
					'shipping' => array_column( $shipping_addresses, 'nickname' ),
				),
			);
			wp_localize_script( 'hc-wcma-checkout-js', 'hc_wcma_checkout_params', $checkout_params );
		}
	}

	/**
	 * Enqueue admin scripts for the user profile page.
	 *
	 * @since 1.0.0
	 * @param string $hook_suffix The current admin page.
	 * @return void
	 */
	public function enqueue_admin_scripts( string $hook_suffix ): void {
		if ( 'user-edit.php' === $hook_suffix || 'profile.php' === $hook_suffix ) {
			wp_enqueue_style( 'hc-wcma-admin', HC_WCMA_PLUGIN_URL . 'assets/css/admin.css', array(), $this->version );
			wp_enqueue_script( 'hc-wcma-admin-js', HC_WCMA_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), $this->version, true );
			wp_localize_script(
				'hc-wcma-admin-js',
				'hc_wcma_admin_params',
				array(
					'nonce' => wp_create_nonce( 'hc_wcma_admin_nonce' ),
					'i18n'  => array(
						'delete_confirm' => __( 'Are you sure you want to delete this address for the user?', 'happycoders-multiple-addresses' ),
					),
				)
			);
		}
	}

	/**
	 * Plugin activation handler.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function hc_wcma_activate(): void {
		if ( defined( 'HC_WCMA_PLUGIN_PATH' ) ) {
			$my_account_class_file = HC_WCMA_PLUGIN_PATH . 'includes/class-hc-wcma-my-account.php';
			if ( file_exists( $my_account_class_file ) ) {
				include_once $my_account_class_file;
			}
		}

		if ( class_exists( 'HC_WCMA_My_Account' ) ) {
			HC_WCMA_My_Account::add_endpoint();

			flush_rewrite_rules();
			// Set a default value for a new option on activation.
			update_option( 'hc_wcma_checkout_selector_style', 'dropdown', true );

		}
	}

	/**
	 * Plugin deactivation handler.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function hc_wcma_deactivate() {
		flush_rewrite_rules();
	}
}

/**
 * Display an admin notice when WooCommerce is not installed or activated.
 *
 * @since 1.0.0
 * @return void
 */
// phpcs:ignore
function hc_wcma_woocommerce_missing_notice(): void {
	?>
	<div class="error">
		<p>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: Link to WooCommerce plugin page */
					__( 'HappyCoders Multiple Addresses plugin requires %s to be installed and active.', 'happycoders-multiple-addresses' ),
					'<a href="https://woocommerce.com/" target="_blank" rel="noopener noreferrer">WooCommerce</a>'
				),
				array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
						'rel'    => array(),
					),
				)
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the HappyCoders Multiple Addresses plugin.
 *
 * @since 1.0.0
 * @return void
 */
function hc_wcma_init(): void {

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'hc_wcma_woocommerce_missing_notice' );
		return;
	}

	add_action(
		'before_woocommerce_init',
		function () {
			if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			}
		}
	);

	HC_WCMA_Main::instance();
}

/**
 * Plugin theme switch handler.
 *
 * @since 1.0.0
 * @return void
 */
function hc_wcma_on_switch_theme(): void {
	if ( defined( 'HC_WCMA_PLUGIN_PATH' ) ) {
		$my_account_class_file = HC_WCMA_PLUGIN_PATH . 'includes/class-hc-wcma-my-account.php';
		if ( file_exists( $my_account_class_file ) ) {
			include_once $my_account_class_file;
		}
	}

	if ( class_exists( 'HC_WCMA_My_Account' ) ) {
		HC_WCMA_My_Account::add_endpoint();
		flush_rewrite_rules();

	}
}
add_action( 'after_switch_theme', 'hc_wcma_on_switch_theme' );

/**
 * Checks if the checkout page is using the block-based checkout.
 *
 * @since 1.0.0
 * @return boolean True if the checkout page uses the block-based checkout, false otherwise.
 */
function hc_wcma_is_checkout_block(): bool {
	global $post;

	// First check, for modern WC Blocks.
	if ( is_singular() && $post ) {
		if ( class_exists( 'WC_Blocks_Utils' ) && method_exists( 'WC_Blocks_Utils', 'has_block_in_page' ) ) {
			return WC_Blocks_Utils::has_block_in_page( $post->ID, 'woocommerce/checkout' );
		}
	}

	return false;
}
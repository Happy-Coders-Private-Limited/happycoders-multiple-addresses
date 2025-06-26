<?php
/**
 * Plugin Name:       HappyCoders Multiple Addresses
 * Plugin URI:        https://happycoders.in/happycoders-multiple-addresses
 * Description:       Allows customers to save and manage multiple billing and shipping addresses.
 * Version:           1.0.0
 * Author:            HappyCoders
 * Author URI:        https://happycoders.in
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       happycoders-multiple-addresses
 * Domain Path:       /languages
 * Requires at least: 5.6
 * Tested up to:      6.4
 * WC requires at least: 6.0
 * WC tested up to:     8.5
 * Requires Plugins: woocommerce
 *
 * @package happycoders-multiple-addresses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HC_WCMA_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'HC_WCMA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HC_WCMA_VERSION', '1.0.0' );
define( 'HC_WCMA_ENDPOINT_SLUG', 'hc-address-book' );

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
	public static function instance() {
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
	 * Includes the following PHP files:
	 * - class-hc-wcma-my-account.php
	 * - class-hc-wcma-checkout.php
	 * - class-hc-wcma-admin.php
	 * - class-hc-wcma-ajax.php
	 * - hc-wcma-functions.php
	 * - hc-wcma-blocks.php
	 *
	 * @since 1.0.0
	 */
	private function includes() {
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
	 * Hooks:
	 * - `init`: `load_plugin_textdomain`, `HC_WCMA_My_Account::add_endpoint`
	 * - `wp_enqueue_scripts`: `enqueue_scripts`
	 * - `admin_enqueue_scripts`: `enqueue_admin_scripts`
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'load_plugin_textdomain' ), 0 );
		add_action( 'init', array( 'HC_WCMA_My_Account', 'add_endpoint' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		HC_WCMA_My_Account::init();
		HC_WCMA_Checkout::init();
		HC_WCMA_Admin::init();
		HC_WCMA_AJAX::init();
	}

	/**
	 * Load the plugin textdomain.
	 *
	 * @since 1.0.0
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'happycoders-multiple-addresses', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Enqueues the necessary scripts and styles for the plugin's frontend.
	 *
	 * Called by the `wp_enqueue_scripts` and `admin_enqueue_scripts` hooks.
	 *
	 * Conditionally enqueues:
	 * - The `my-account` endpoint JavaScript, CSS, and Swiper library.
	 * - The `checkout` JavaScript and CSS, but only if the checkout page is not using the block-based checkout.
	 *
	 * Also localizes the necessary data for the JavaScript files.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts() {
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
					'limit_message_tpl' => __( 'You have reached the maximum (%limit%) saved %type% addresses.', 'happycoders-multiple-addresses' ),
				)
			);
		}

		$is_block_checkout = false;

		global $post;

		if ( is_singular() && $post ) {
			if ( class_exists( 'WC_Blocks_Utils' ) && method_exists( 'WC_Blocks_Utils', 'has_block_in_page' ) ) {
				if ( WC_Blocks_Utils::has_block_in_page( $post->ID, 'woocommerce/checkout' ) ) {
					$is_block_checkout = true;
				}
			}
		}

		if ( ! $is_block_checkout ) {

			wp_enqueue_style( 'hc-wcma-checkout', HC_WCMA_PLUGIN_URL . 'assets/css/checkout.css', array(), $this->version . time() );
			wp_enqueue_script( 'hc-wcma-checkout-js', HC_WCMA_PLUGIN_URL . 'assets/js/checkout.js', array( 'jquery', 'wc-checkout', 'selectWoo' ), $this->version . time(), true );

			$user_id = get_current_user_id();

			$checkout_addresses = array(
				'billing'  => array(),
				'shipping' => array(),
			);

			$billing_addresses  = hc_wcma_get_user_addresses( $user_id, 'billing' );
			$shipping_addresses = hc_wcma_get_user_addresses( $user_id, 'shipping' );

			foreach ( $billing_addresses as $key => $addr ) {
				$checkout_addresses['billing'][ $key ] = $addr; }
			foreach ( $shipping_addresses as $key => $addr ) {
				$checkout_addresses['shipping'][ $key ] = $addr; }

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
			);
			wp_localize_script( 'hc-wcma-checkout-js', 'hc_wcma_checkout_params', $checkout_params );
		}
	}

	/**
	 * Enqueue admin scripts for the user profile page.
	 *
	 * Scripts are enqueued on the user profile page (user-edit.php) and the
	 * current user's profile page (profile.php).
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function enqueue_admin_scripts( $hook_suffix ) {
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
}

/**
 * Display an admin notice when WooCommerce is not installed or activated.
 *
 * @since 1.0.0
 */
// phpcs:ignore
function hc_wcma_woocommerce_missing_notice() {
	?>
	<div class="error">
		<p>
			<?php
			printf(
				/* translators: 1: Link to WooCommerce plugin page */
				esc_html__( 'HappyCoders Multiple Addresses plugin requires %1$s to be installed and active.', 'happycoders-multiple-addresses' ),
				'<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Initialize the HappyCoders Multiple Addresses plugin.
 *
 * Checks if WooCommerce is active and displays an admin notice if not.
 * Declares compatibility with WooCommerce's custom order tables feature
 * and initializes the main plugin instance.
 *
 * Hooks:
 * - Adds an admin notice if WooCommerce is not active.
 * - Declares compatibility with WooCommerce custom order tables before initialization.
 *
 * @return void
 */
function hc_wcma_init() {

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'hc_wcma_woocommerce_missing_notice' );
		return;
	}

	add_action(
		'before_woocommerce_init',
		function () {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	);

	HC_WCMA_Main::instance();
}
add_action( 'plugins_loaded', 'hc_wcma_init' );

register_activation_hook( __FILE__, 'hc_wcma_activate' );

/**
 * Plugin activation handler.
 *
 * Adds default options, includes required classes, and registers the my account
 * endpoint.
 *
 * @since 1.0.0
 *
 * @return void
 */
function hc_wcma_activate() {
	// Add default options, etc.
	if ( defined( 'HC_WCMA_PLUGIN_PATH' ) ) {
		$my_account_class_file = HC_WCMA_PLUGIN_PATH . 'includes/class-hc-wcma-my-account.php';
		if ( file_exists( $my_account_class_file ) ) {
			include_once $my_account_class_file;
		}
	}

	if ( class_exists( 'HC_WCMA_My_Account' ) ) {
		HC_WCMA_My_Account::add_endpoint();

		flush_rewrite_rules();

		update_option( 'hc_wcma_checkout_selector_style', 'dropdown', true );

	} else {
		wp_die( 'Plugin activation failed: Required class HC_WCMA_My_Account could not be loaded. Path constant defined: ' . ( defined( 'HC_WCMA_PLUGIN_PATH' ) ? 'Yes' : 'No' ) );
	}
}

register_deactivation_hook( __FILE__, 'hc_wcma_deactivate' );

/**
 * Plugin deactivation handler.
 *
 * Flushes rewrite rules after deactivating the plugin.
 *
 * @since 1.0.0
 *
 * @return void
 */
function hc_wcma_deactivate() {
	flush_rewrite_rules();
}

/**
 * Plugin theme switch handler.
 *
 * Adds the custom my account endpoint when the theme is switched.
 *
 * This is necessary because the endpoint is registered in the constructor of the
 * HC_WCMA_My_Account class. This class is not loaded when the theme is switched.
 *
 * @since 1.0.0
 *
 * @return void
 */
function hc_wcma_on_switch_theme() {
	if ( defined( 'HC_WCMA_PLUGIN_PATH' ) ) {
		$my_account_class_file = HC_WCMA_PLUGIN_PATH . 'includes/class-hc-wcma-my-account.php';
		if ( file_exists( $my_account_class_file ) ) {
			include_once $my_account_class_file;
		}
	}

	if ( class_exists( 'HC_WCMA_My_Account' ) ) {
		HC_WCMA_My_Account::add_endpoint();

		flush_rewrite_rules();

	} else {
		wp_die( 'Plugin activation failed: Required class HC_WCMA_My_Account could not be loaded. Path constant defined: ' . ( defined( 'HC_WCMA_PLUGIN_PATH' ) ? 'Yes' : 'No' ) );
	}
}
add_action( 'after_switch_theme', 'hc_wcma_on_switch_theme' );

/**
 * Checks if the checkout page is using the block-based checkout.
 *
 * @return boolean True if the checkout page uses the block-based checkout, false otherwise.
 */
function hc_wcma_is_checkout_block() {
	return WC_Blocks_Utils::has_block_in_page( wc_get_page_id( 'checkout' ), 'woocommerce/checkout' );
}

/**
 * Add Settings link to the plugin actions row.
 *
 * @param array  $links Existing action links.
 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
 * @return array Modified action links.
 */
function hc_wcma_add_action_links( $links, $plugin_file ) {
	static $this_plugin;
	if ( ! $this_plugin ) {
		$this_plugin = plugin_basename( __FILE__ );
	}

	if ( $plugin_file === $this_plugin ) {
		$settings_url = admin_url( 'admin.php?page=wc-settings&tab=hc_wcma' );

		$settings_link = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'happycoders-multiple-addresses' ) . '</a>';

		array_unshift( $links, $settings_link );
	}

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'hc_wcma_add_action_links', 10, 2 );

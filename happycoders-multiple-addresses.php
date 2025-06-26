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

require_once HC_WCMA_PLUGIN_PATH . 'includes/class-hc-wcma-main.php';

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action( 'plugins_loaded', array( 'HC_WCMA_Main', 'instance' ) );

register_activation_hook( __FILE__, array( 'HC_WCMA_Main', 'hc_wcma_activate' ) );
register_deactivation_hook( __FILE__, array( 'HC_WCMA_Main', 'hc_wcma_deactivate' ) );

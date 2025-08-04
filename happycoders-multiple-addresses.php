<?php
/**
 * Plugin Name:       Happy Coders Multi Address for WooCommerce
 * Plugin URI:        https://happycoders.in/happycoders-multiple-addresses
 * Description:       Allows customers to save and manage multiple billing and shipping addresses.
 * Version:           1.0.5
 * Author:            HappyCoders
 * Author URI:        https://happycoders.in
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       happycoders-multiple-addresses
 * Requires at least: 5.6
 * Tested up to:      6.8
 * WC requires at least: 6.0
 * WC tested up to:     10.0.0
 * Requires Plugins: woocommerce
 *
 * @package happycoders-multiple-addresses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HC_WCMA_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'HC_WCMA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'HC_WCMA_VERSION', '1.0.5' );
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

/**
 * Add Settings link to the plugin actions row.
 *
 * @since 1.0.0
 * @param array  $links       Existing action links.
 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
 * @return array Modified action links.
 */
function hc_wcma_add_action_links( array $links, string $plugin_file ): array {
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

register_activation_hook( __FILE__, array( 'HC_WCMA_Main', 'hc_wcma_activate' ) );
register_deactivation_hook( __FILE__, array( 'HC_WCMA_Main', 'hc_wcma_deactivate' ) );

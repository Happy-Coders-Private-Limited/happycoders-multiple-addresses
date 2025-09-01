<?php
/**
 * Class HC_WCMA_REST_API
 *
 * @package happycoders-multiple-addresses
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class HC_WCMA_REST_API
 */
class HC_WCMA_REST_API {

	/**
	 * Initialize the REST API hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register the REST API routes.
	 */
	public static function register_routes() {
		register_rest_route(
			'hc-wcma/v1',
			'/save-nickname',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'save_nickname' ),
				'permission_callback' => '__return_true', // TODO: Add a proper permission check.
			)
		);
	}

	/**
	 * Save the nickname to the session.
	 *
	 * @param WP_REST_Request $request The REST request.
	 *
	 * @return WP_REST_Response
	 */
	public static function save_nickname( $request ) {
		$params = $request->get_json_params();

		if ( ! isset( $params['address_type'] ) || ! in_array( $params['address_type'], array( 'billing', 'shipping' ), true ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Invalid address type.',
				),
				400
			);
		}

		$address_type  = $params['address_type'];
		$nickname_type = isset( $params['nickname_type'] ) ? sanitize_text_field( $params['nickname_type'] ) : '';
		$nickname      = isset( $params['nickname_type'] ) ? ( ( 'Other' === $params['nickname_type'] && isset( $params['nickname'] ) ) ? sanitize_text_field( $params['nickname'] ) : sanitize_text_field( $params['nickname_type'] ) ) : '';

		if ( ! WC()->session ) {
			WC()->session = new WC_Session_Handler();
			WC()->session->init();
		}

		WC()->session->set( 'hc_wcma_' . $address_type . '_nickname_type', $nickname_type );
		WC()->session->set( 'hc_wcma_' . $address_type . '_nickname', $nickname );

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}
}

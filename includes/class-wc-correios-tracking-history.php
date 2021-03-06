<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Correios orders.
 */
class WC_Correios_Tracking_History {

	/**
	 * Initialize the order actions.
	 */
	public function __construct() {
		// Show tracking code in order details.
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'view' ), 1 );
	}

	/**
	 * Get the tracing history API URL.
	 *
	 * @return string
	 */
	protected function get_tracking_history_api_url() {
		return apply_filters( 'woocommerce_correios_tracking_api_url', 'http://websro.correios.com.br/sro_bin/sroii_xml.eventos' );
	}

	/**
	 * Get method options
	 *
	 * @return array
	 */
	protected function get_method_options() {
		return get_option( 'woocommerce_correios_settings', array() );
	}

	/**
	 * Get user data.
	 *
	 * @return array
	 */
	protected function get_user_data() {
		$options  = $this->get_method_options();
		$login    = 'ECT';
		$password = 'SRO';

		if ( 'corporate' == $options['corporate_service'] ) {
			$login    = empty( $options['login'] ) ? 'ECT' : $options['login'];
			$password = empty( $options['password'] ) ? 'SRO' : $options['password'];
		}

		return array(
			'login'    => $login,
			'password' => $password
		);
	}

	/**
	 * Logger.
	 *
	 * @param string $data
	 */
	protected function logger( $data ) {
		$options = $this->get_method_options();

		if ( ! empty( $options ) && 'yes' == $options['debug'] ) {
			$logger = WC_Correios::logger();
			$logger->add( 'correios', $data );
		}
	}

	/**
	 * Access API Correios.
	 *
	 * @param  string $tracking_code.
	 *
	 * @return SimpleXmlElement|stdClass History Tracking code.
	 */
	protected function get_tracking_history( $tracking_code ) {
		$user_data   = $this->get_user_data();
		$args        = apply_filters( 'woocommerce_correios_tracking_args', array(
			'Usuario'   => $user_data['login'],
			'Senha'     => $user_data['password'],
			'Tipo'      => 'L', /* L - List of objects | F - Object Range */
			'Resultado' => 'T', /* T - Returns all the object's events | U - Returns only last event object */
			'Objetos'   => $tracking_code,
		) );
		$api_url     = $this->get_tracking_history_api_url();
		$request_url = add_query_arg( $args, $api_url );
		$params      = array(
			'sslverify' => false,
			'timeout'   => 30
		);

		$this->logger( 'Requesting tracking history in: ' . print_r( $request_url, true ) );

		$response = wp_remote_get( $request_url, $params );

		if ( ! is_wp_error( $response ) && $response['response']['code'] >= 200 && $response['response']['code'] < 300 ) {
			$tracking_history = new SimpleXmlElement( $response['body'], LIBXML_NOCDATA );
		} else {
			$tracking_history = new stdClass();
			$tracking_history->error = true;
		}

		$this->logger( 'Tracking history response: ' . print_r( $tracking_history, true ) );

		return $tracking_history;
	}

	/**
	 * Display the order tracking code in order details and the tracking history.
	 *
	 * @param WC_Order $order_id Order data.
	 */
	public function view( $order ) {
		$events        = false;
		$tracking_code = get_post_meta( $order->id, 'correios_tracking', true );

		// Check if exist a tracking code for the order.
		if ( ! $tracking_code ) {
			return;
		}

		// Get the shipping method options.
		$options = $this->get_method_options();

		// Try to connect to Correios Webservices and get the tracking history.
		if ( ! empty( $options['tracking_history'] ) && 'yes' == $options['tracking_history'] ) {
			$tracking = $this->get_tracking_history( $tracking_code );
			$events   = isset( $tracking->objeto->evento ) ? $tracking->objeto->evento : false;
		}

		// Display the right template for show the tracking code or tracking history.
		if ( $events ) {
			woocommerce_get_template(
				'myaccount/tracking-history-table.php',
				array(
					'events' => $events,
					'code'   => $tracking_code
				),
				'',
				WC_Correios::get_templates_path()
			);
		} else {
			woocommerce_get_template(
				'myaccount/tracking-code.php',
				array(
					'code' => $tracking_code
				),
				'',
				WC_Correios::get_templates_path()
			);
		}
	}
}

new WC_Correios_Tracking_History();

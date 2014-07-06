<?php
/*
 * Plugin Name: Stripe for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/stripe-for-woocommerce
 * Description: Use Stripe for collecting credit card payments on WooCommerce.
 * Version: 1.1
 * Author: Stephen Zuniga
 * Author URI: http://stephenzuniga.com
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * Foundation built by: Sean Voss // https://github.com/seanvoss/striper
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WooCommerce_Stripe {

	public function __construct() {

		// Include Stripe Methods
		include_once( 'classes/class-wc_stripe.php' );

		// Grab settings
		$this->settings = get_option( 'woocommerce_wc_stripe' . '_settings', null );

		// API Info
		$this->settings['api_endpoint']				= 'https://api.stripe.com/';
		$this->settings['publishable_key']			= $this->settings['testmode'] == 'yes' ? $this->settings['test_publishable_key'] : $this->settings['live_publishable_key'];
		$this->settings['secret_key']				= $this->settings['testmode'] == 'yes' ? $this->settings['test_secret_key'] : $this->settings['live_secret_key'];

		// Database info location
		$this->settings['stripe_db_location']		= $this->settings['testmode'] == 'yes' ? '_stripe_test_customer_info' : '_stripe_live_customer_info';

		// Hooks
		add_filter( 'woocommerce_payment_gateways', array( &$this, 'woocommerce_stripe_gateway' ) );
		add_action( 'woocommerce_after_my_account', array( &$this, 'account_saved_cards' ) );
	}

	/**
	 * Add Stripe Gateway to WooCommerces list of Gateways
	 *
	 * @access public
	 * @param array $methods
	 * @return array
	 */
	public function woocommerce_stripe_gateway( $methods ) {
		if ( class_exists( 'WC_Payment_Gateway' ) ) {
			include_once( 'classes/class-wc_stripe_gateway.php' );
		}

		if ( class_exists( 'WC_Stripe_Gateway' ) ) {
			$methods[] = 'WC_Stripe_Gateway';
		}

		return $methods;
	}

	/**
	 * Gives front-end view of saved cards in the account page
	 *
	 * @access public
	 * @return void
	 */
	public function account_saved_cards() {
		wc_stripe_get_template( 'saved-cards.php' );
	}
}

$GLOBALS['wc_stripe'] = new WooCommerce_Stripe();

/**
 * Process the captured payment when changing order status to completed
 *
 * @access public
 * @param int $order_id
 * @return bool
 */
function wc_stripe_order_status_completed( $order_id = null ) {
	global $woocommerce, $wc_stripe;

	if ( ! $order_id ) {
		$order_id = $_POST['order_id'];
	}

	$data = get_post_meta( $order_id );
	$total = $data['_order_total'][0] * 100;

	$params = array();
	if( isset( $_POST['amount'] ) && $amount = $_POST['amount'] ) {
		$params['amount'] = round( $amount );
	}

	if( get_post_meta( $order_id, 'capture', true ) ) {

		$transaction_id = get_post_meta( $order_id, 'transaction_id', true );

		$charge = WC_Stripe::capture_charge( $transaction_id, $params );

		return $charge;
	}
}
add_action( 'woocommerce_order_status_processing_to_completed', 'wc_stripe_order_status_completed' );

/**
 * Handles posting notifications to the user when their credit card information is invalid
 *
 * @access public
 * @return void
 */
function wc_stripe_validation_errors() {

	foreach( $_POST['errors'] as $error ) {
		$message = '';

		$message .= '<strong>';
		switch ( $error['field'] ) {
			case 'number':
				$message .= __( 'Credit Card Number', 'wc_stripe' );
				break;
			case 'expiration':
				$message .= __( 'Credit Card Expiration', 'wc_stripe' );
				break;
			case 'cvc':
				$message .= __( 'Credit Card CVC', 'wc_stripe' );
				break;
		}
		$message .= '</strong>';

		switch ( $error['type'] ) {
			case 'undefined':
				$message .= ' ' . __( 'is a required field', 'wc_stripe' );
				break;
			case 'invalid':
				$message = __( 'Please enter a valid', 'wc_stripe' ) . ' ' . $message;
				break;
		}
		$message .= '.';

		wc_add_notice( $message, 'error' );
	}

	if ( is_ajax() ) {

		ob_start();
		wc_print_notices();
		$messages = ob_get_clean();

		echo '<!--WC_STRIPE_START-->' . json_encode(
			array(
				'result'	=> 'failure',
				'messages' 	=> $messages,
				'refresh' 	=> isset( WC()->session->refresh_totals ) ? 'true' : 'false',
				'reload'    => isset( WC()->session->reload_checkout ) ? 'true' : 'false'
			)
		) . '<!--WC_STRIPE_END-->';

		unset( WC()->session->refresh_totals, WC()->session->reload_checkout );
		exit;
	}
	die();
}
add_action( 'wp_ajax_stripe_form_validation', 'wc_stripe_validation_errors' );
add_action( 'wp_ajax_nopriv_stripe_form_validation', 'wc_stripe_validation_errors' );

/**
 * Wrapper of wc_get_template to relate directly to woocommerce-stripe
 *
 * @param string $template_name
 * @return string
 */
function wc_stripe_get_template( $template_name ) {
	return wc_get_template( $template_name, array(), WC()->template_path() . '/woocommerce-stripe', plugin_dir_path( __FILE__ ) . '/templates/' );
}
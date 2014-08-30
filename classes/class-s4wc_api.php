<?php
/**
 * Functions for interfacing with Stripe's API
 *
 * @class 		S4WC_API
 * @version		1.24
 * @author 		Stephen Zuniga
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class S4WC_API {

	/**
	 * Create customer on stripe servers
	 *
	 * @access public
	 * @param array $form_data
	 * @param string $customer_description
	 * @return array
	 */
	public static function create_customer( $form_data, $customer_description ) {

		$post_body = array(
			'description'	=> apply_filters( 's4wc_customer_description', $customer_description, $customer_description, $form_data ),
			'card'			=> $form_data['token']
		);

		$customer = S4WC_API::post_data( $post_body, 'customers' );

		$active_card = $customer->cards->data[ array_search( $customer->default_card, $customer->cards->data ) ];

		// Save users customer information for later use
		$customerArray = array(
			'customer_id'	=> $customer->id,
			'card'			=> array(
				'id'			=> $active_card->id,
				'brand'			=> $active_card->type,
				'last4'			=> $active_card->last4,
				'exp_year'		=> $active_card->exp_year,
				'exp_month'		=> $active_card->exp_month
			),
			'default_card'	=> $active_card->id
		);
		S4WC_DB::update_customer( get_current_user_id(), $customerArray );

		return $customer;
	}

	/**
	 * Get customer from stripe servers
	 *
	 * @access public
	 * @param string $customer_id
	 * @return array
	 */
	public static function get_customer( $customer_id ) {
		return S4WC_API::get_data( 'customers/' . $customer_id );
	}

	/**
	 * Update customer on stripe servers
	 *
	 * @access public
	 * @param string $customer_id
	 * @param array $request
	 * @return array
	 */
	public static function update_customer( $customer_id, $customer_data ) {
		return S4WC_API::post_data( $customer_data, 'customers/' . $customer_id );
	}

	/**
	 * Delete card from stripe servers
	 *
	 * @access public
	 * @param integer $user_id
	 * @param integer $position
	 * @return array
	 */
	public static function delete_card( $user_id, $position ) {
		global $s4wc;

		if ( ! $position )
			$position = 0;

		$user_meta = get_user_meta( $user_id, $s4wc->settings['stripe_db_location'], true );

		S4WC_DB::delete_customer( get_current_user_id(), array( 'card' => $user_meta['cards'][ $position ]['id'] ) );

		return S4WC_API::delete_data( 'customers/' . $user_meta['customer_id'] . '/cards/' . $user_meta['cards'][ $position ]['id'] );
	}

	/**
	 * Create charge on stripe servers
	 *
	 * @access public
	 * @param array $charge_data
	 * @return array
	 */
	public static function create_charge( $charge_data ) {
		return S4WC_API::post_data( $charge_data );
	}

	/**
	 * Capture charge on stripe servers
	 *
	 * @access public
	 * @param string $transaction_id
	 * @param array $charge_data
	 * @return array
	 */
	public static function capture_charge( $transaction_id, $charge_data ) {
		return S4WC_API::post_data( $charge_data, 'charges/' . $transaction_id . '/capture' );
	}

	/**
	 * Get data from Stripe's servers by passing an API endpoint
	 *
	 * @access public
	 * @param string $get_location
	 * @return array
	 */
	public static function get_data( $get_location ) {
		global $s4wc;

		$response = wp_remote_get( 'https://api.stripe.com/' . 'v1/' . $get_location, array(
			'method'		=> 'GET',
			'headers' 		=> array(
				'Authorization' => 'Basic ' . base64_encode( $s4wc->settings['secret_key'] . ':' ),
			),
			'timeout' 		=> 70,
			'sslverify' 	=> false,
			'user-agent' 	=> 'WooCommerce-Stripe',
		) );

		return S4WC_API::parse_response( $response );
	}

	/**
	 * Post data to Stripe's servers by passing data and an API endpoint
	 *
	 * @access public
	 * @param array $post_data
	 * @param string $post_location
	 * @return array
	 */
	public static function post_data( $post_data, $post_location = 'charges' ) {
		global $s4wc;

		$response = wp_remote_post( $s4wc->settings['api_endpoint'] . 'v1/' . $post_location, array(
			'method'		=> 'POST',
			'headers' 		=> array(
				'Authorization' => 'Basic ' . base64_encode( $s4wc->settings['secret_key'] . ':' ),
			),
			'body'			=> $post_data,
			'timeout' 		=> 70,
			'sslverify' 	=> false,
			'user-agent' 	=> 'WooCommerce-Stripe',
		) );

		return S4WC_API::parse_response( $response );
	}

	/**
	 * Delete data from Stripe's servers by passing an API endpoint
	 *
	 * @access public
	 * @param string $delete_location
	 * @return array
	 */
	public static function delete_data( $delete_location ) {
		global $s4wc;

		$response = wp_remote_post( $s4wc->settings['api_endpoint'] . 'v1/' . $delete_location, array(
			'method'		=> 'DELETE',
			'headers' 		=> array(
				'Authorization' => 'Basic ' . base64_encode( $s4wc->settings['secret_key'] . ':' ),
			),
			'timeout' 		=> 70,
			'sslverify' 	=> false,
			'user-agent' 	=> 'WooCommerce-Stripe',
		) );

		return S4WC_API::parse_response( $response );
	}

	/**
	 * Parse Stripe's response after interacting with the API
	 *
	 * @access public
	 * @param array $response
	 * @return array
	 */
	public static function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			throw new Exception( __( 'There was a problem connecting to the payment gateway.', 'stripe-for-woocommerce' ) );
		}

		if( empty( $response['body'] ) ) {
			throw new Exception( __( 'Empty response.', 'stripe-for-woocommerce' ) );
		}

		$parsed_response = json_decode( $response['body'] );

		// Handle response
		if ( ! empty( $parsed_response->error ) ) {
			throw new Exception( __( $parsed_response->error->message, 'stripe-for-woocommerce' ) );
		} elseif ( empty( $parsed_response->id ) ) {
			throw new Exception( __( 'Invalid response.', 'stripe-for-woocommerce' ) );
		}

		return $parsed_response;
	}
}
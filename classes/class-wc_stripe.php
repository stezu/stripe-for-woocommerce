<?php
/**
 * Functions for interfacing with Stripe's API
 *
 * @class 		WC_Stripe
 * @version		1.1
 * @author 		Stephen Zuniga
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class WC_Stripe {

	/**
	 * Create customer on stripe servers
	 *
	 * @access public
	 * @param integer $user_id
	 * @param array $form_data
	 * @param string $customer_description
	 * @return array
	 */
	public static function create_customer( $user_id, $form_data, $customer_description ) {
		global $wc_stripe;

		$post_body = array(
			'description'	=> $customer_description,
			'card'			=> $form_data['token']
		);

		$customer = WC_Stripe::post_data( $post_body, 'customers' );

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
		WC_Stripe::update_customer_db( $user_id, $customerArray );

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
		return WC_Stripe::get_data( 'customers/' . $customer_id );
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
		return WC_Stripe::post_data( $customer_data, 'customers/' . $customer_id );
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
		global $wc_stripe;

		if ( ! $position )
			$position = 0;

		$user_meta = get_user_meta( $user_id, $wc_stripe->settings['stripe_db_location'], true );

		WC_Stripe::delete_customer_db( get_current_user_id(), array( 'card' => $user_meta['cards'][$position]['id'] ) );

		return WC_Stripe::delete_data( 'customers/' . $user_meta['customer_id'] . '/cards/' . $user_meta['cards'][$position]['id'] );
	}

	/**
	 * Create charge on stripe servers
	 *
	 * @access public
	 * @param array $charge_data
	 * @return array
	 */
	public static function create_charge( $charge_data ) {
		return WC_Stripe::post_data( $charge_data );
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
		return WC_Stripe::post_data( $charge_data, 'charges/' . $transaction_id . '/capture' );
	}

	/**
	 * Get data from Stripe's servers by passing an API endpoint
	 *
	 * @access public
	 * @param string $get_location
	 * @return array
	 */
	public static function get_data( $get_location ) {
		global $wc_stripe;

		$response = wp_remote_get( 'https://api.stripe.com/' . 'v1/' . $get_location, array(
			'method'		=> 'GET',
			'headers' 		=> array(
				'Authorization' => 'Basic ' . base64_encode( $wc_stripe->settings['secret_key'] . ':' ),
			),
			'timeout' 		=> 70,
			'sslverify' 	=> false,
			'user-agent' 	=> 'WooCommerce-Stripe',
		) );

		return WC_Stripe::parse_response( $response );
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
		global $wc_stripe;

		$response = wp_remote_post( $wc_stripe->settings['api_endpoint'] . 'v1/' . $post_location, array(
			'method'		=> 'POST',
			'headers' 		=> array(
				'Authorization' => 'Basic ' . base64_encode( $wc_stripe->settings['secret_key'] . ':' ),
			),
			'body'			=> $post_data,
			'timeout' 		=> 70,
			'sslverify' 	=> false,
			'user-agent' 	=> 'WooCommerce-Stripe',
		) );

		return WC_Stripe::parse_response( $response );
	}

	/**
	 * Delete data from Stripe's servers by passing an API endpoint
	 *
	 * @access public
	 * @param string $delete_location
	 * @return array
	 */
	public static function delete_data( $delete_location ) {
		global $wc_stripe;

		$response = wp_remote_post( $wc_stripe->settings['api_endpoint'] . 'v1/' . $delete_location, array(
			'method'		=> 'DELETE',
			'headers' 		=> array(
				'Authorization' => 'Basic ' . base64_encode( $wc_stripe->settings['secret_key'] . ':' ),
			),
			'timeout' 		=> 70,
			'sslverify' 	=> false,
			'user-agent' 	=> 'WooCommerce-Stripe',
		) );

		return WC_Stripe::parse_response( $response );
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
			throw new Exception( __( 'There was a problem connecting to the payment gateway.', 'wc_stripe' ) );
		}

		if( empty( $response['body'] ) ) {
			throw new Exception( __( 'Empty response.', 'wc_stripe' ) );
		}

		$parsed_response = json_decode( $response['body'] );

		// Handle response
		if ( ! empty( $parsed_response->error ) ) {
			throw new Exception( __( $parsed_response->error->message, 'wc_stripe' ) );
		} elseif ( empty( $parsed_response->id ) ) {
			throw new Exception( __( 'Invalid response.', 'wc_stripe' ) );
		}

		return $parsed_response;
	}

	/**
	 * Add/Update the customer database object
	 *
	 * @access public
	 * @param integer $user_id
	 * @param array $customer_data
	 * @return mixed
	 */
	public static function update_customer_db( $user_id, $customer_data ) {
		global $wc_stripe;

		// Sample structure of $customer_data
		// $customer_data = array(
		// 	'customer_id'	=> 'cus_4FP7ML8QPaZNmc',
		// 	'card'			=> array(
		// 		'id'			=> 'card_104FP72XTgyB3Fd3Pw9jM2Xh',
		// 		'last4'			=> '4242',
		// 		'brand'			=> 'Visa',
		// 		'exp_month'		=> 12,
		// 		'exp_year'		=> 2016
		// 	),
		// 	'default_card'	=> 'card_104FP72XTgyB3Fd3Pw9jM2Xh'
		// );

		if ( isset( $customer_data ) ) {

			// Set variables related to the form fields we're updating
			$customer_id = isset( $customer_data['customer_id'] ) ? $customer_data['customer_id'] : null;

			if ( isset( $customer_data['card'] ) ) {
				$card_id		= isset( $customer_data['card']['id'] ) ? $customer_data['card']['id'] : null;
				$card_last4		= isset( $customer_data['card']['last4'] ) ? $customer_data['card']['last4'] : null;
				$card_brand		= isset( $customer_data['card']['brand'] ) ? $customer_data['card']['brand'] : null;
				$card_exp_month	= isset( $customer_data['card']['exp_month'] ) ? $customer_data['card']['exp_month'] : null;
				$card_exp_year	= isset( $customer_data['card']['exp_year'] ) ? $customer_data['card']['exp_year'] : null;
			}

			$default_card = isset( $customer_data['default_card'] ) ? $customer_data['default_card'] : null;

			// Grab the current object out of the database and return a useable array
			$currentObject = maybe_unserialize( get_user_meta( $user_id, $wc_stripe->settings['stripe_db_location'], true ) );

			// If there is an exising object, append values
			if ( $currentObject ) {
				$newObject = $currentObject;

				// Add a new card to the object
				if ( isset( $customer_data['card'] ) ) {
					$newObject['cards'][] = array(
						'id'		=> $card_id,
						'last4'		=> $card_last4,
						'brand'		=> $card_brand,
						'exp_month'	=> $card_exp_month,
						'exp_year'	=> $card_exp_year
					);
				}

				// Reference a new default card
				if ( isset( $customer_data['default_card'] ) ) {
					$newObject['default_card'] = $default_card;
				}
			}

			// Otherwise, create a new object
			else {
				$newObject = array();

				$newObject['customer_id']	= $customer_id;
				$newObject['cards']			= array();
				$newObject['cards'][]		= array(
					'id'		=> $card_id,
					'last4'		=> $card_last4,
					'brand'		=> $card_brand,
					'exp_month'	=> $card_exp_month,
					'exp_year'	=> $card_exp_year
				);
				$newObject['default_card'] = $default_card;
			}

			// Add to the database
			return update_user_meta( $user_id, $wc_stripe->settings['stripe_db_location'], $newObject );
		}
	}

	/**
	 * Delete from the customer database object
	 *
	 * @access public
	 * @param integer $user_id
	 * @param array $customer_data
	 * @return mixed
	 */
	public static function delete_customer_db( $user_id, $customer_data ) {
		global $wc_stripe;

		// Sample structure of $customer_data
		// $customer_data = array( 
		// 	'card' => 'card_104FP72XTgyB3Fd3Pw9jM2Xh'
		// );

		if ( isset( $customer_data ) ) {

			// Grab the current object out of the database and return a useable array
			$currentObject = maybe_unserialize( get_user_meta( $user_id, $wc_stripe->settings['stripe_db_location'], true ) );

			// If the object exists already, do work
			if ( $currentObject ) {
				$newObject = $currentObject;

				// If a card id is passed, delete the card from the database object
				if ( isset( $customer_data['card'] ) ) {
					unset( $newObject['cards'][ recursive_array_search( $customer_data['card'], $newObject['cards'] ) ] );
				}
			}

			// Otherwise fail
			else {
				return false;
			}

			// Add to the database
			return update_user_meta( $user_id, $wc_stripe->settings['stripe_db_location'], $newObject );
		}
	}
}

function recursive_array_search( $needle, $haystack ) {

    foreach ( $haystack as $key => $value ) {
        $current_key = $key;

        if ( $needle === $value OR ( is_array( $value ) && recursive_array_search( $needle, $value ) !== false ) ) {
            return $current_key;
        }
    }
    return false;
}
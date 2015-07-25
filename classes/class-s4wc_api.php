<?php
/**
 * Functions for interfacing with Stripe's API
 *
 * @class       S4WC_API
 * @author      Stephen Zuniga
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class S4WC_API {
    public static $api_endpoint = 'https://api.stripe.com/v1/';

    /**
     * Create customer on stripe servers
     *
     * @access      public
     * @param       int $user_id
     * @param       array $customer_data
     * @return      array
     */
    public static function create_customer( $user_id, $customer_data ) {

        // Create a customer on Stripe servers
        $customer = S4WC_API::post_data( $customer_data, 'customers' );

        $key = S4WC_API::find_card_index( $customer->sources->data, 'id', $customer->default_source );

        if ( $key > -1 ) {
            $active_card = $customer->sources->data[ $key ];

            // Save users customer information for later use
            $customerArray = array(
                'customer_id'   => $customer->id,
                'card'          => array(
                    'id'            => $active_card->id,
                    'brand'         => $active_card->type,
                    'last4'         => $active_card->last4,
                    'exp_year'      => $active_card->exp_year,
                    'exp_month'     => $active_card->exp_month
                ),
                'default_card'  => $active_card->id
            );
            S4WC_DB::update_customer( $user_id, $customerArray );

            return $customer;
        } else {
            return false;
        }
    }

    /**
     * Get customer from stripe servers
     *
     * @access      public
     * @param       string $customer_id
     * @return      array
     */
    public static function get_customer( $customer_id ) {
        return S4WC_API::get_data( 'customers/' . $customer_id );
    }

    /**
     * Update customer on stripe servers
     *
     * @access      public
     * @param       string $customer_id
     * @param       array $request
     * @return      array
     */
    public static function update_customer( $customer_id, $customer_data ) {
        return S4WC_API::post_data( $customer_data, 'customers/' . $customer_id );
    }

    /**
     * Delete card from stripe servers
     *
     * @access      public
     * @param       int $user_id
     * @param       int $position
     * @return      array
     */
    public static function delete_card( $user_id, $position = 0 ) {
        global $s4wc;

        $user_meta = get_user_meta( $user_id, $s4wc->settings['stripe_db_location'], true );

        S4WC_DB::delete_customer( $user_id, array( 'card' => $user_meta['cards'][ $position ]['id'] ) );

        return S4WC_API::delete_data( 'customers/' . $user_meta['customer_id'] . '/cards/' . $user_meta['cards'][ $position ]['id'] );
    }

    /**
     * Create charge on stripe servers
     *
     * @access      public
     * @param       array $charge_data
     * @return      array
     */
    public static function create_charge( $charge_data ) {
        return S4WC_API::post_data( $charge_data );
    }

    /**
     * Capture charge on stripe servers
     *
     * @access      public
     * @param       string $transaction_id
     * @param       array $charge_data
     * @return      array
     */
    public static function capture_charge( $transaction_id, $charge_data ) {
        return S4WC_API::post_data( $charge_data, 'charges/' . $transaction_id . '/capture' );
    }

    /**
     * Create refund on stripe servers
     *
     * @access      public
     * @param       string $transaction_id
     * @param       array $refund_data
     * @return      array
     */
    public static function create_refund( $transaction_id, $refund_data ) {
        return S4WC_API::post_data( $refund_data, 'charges/' . $transaction_id . '/refunds' );
    }

    /**
     * Get data from Stripe's servers by passing an API endpoint
     *
     * @access      public
     * @param       string $get_location
     * @return      array
     */
    public static function get_data( $get_location ) {
        global $s4wc;

        $response = wp_remote_get( self::$api_endpoint . $get_location, array(
            'method'        => 'GET',
            'headers'       => array(
                'Authorization' => 'Basic ' . base64_encode( $s4wc->settings['secret_key'] . ':' ),
            ),
            'timeout'       => 70,
            'sslverify'     => false,
            'user-agent'    => 'WooCommerce-Stripe',
        ) );

        return S4WC_API::parse_response( $response );
    }

    /**
     * Post data to Stripe's servers by passing data and an API endpoint
     *
     * @access      public
     * @param       array $post_data
     * @param       string $post_location
     * @return      array
     */
    public static function post_data( $post_data, $post_location = 'charges' ) {
        global $s4wc;

        $response = wp_remote_post( self::$api_endpoint . $post_location, array(
            'method'        => 'POST',
            'headers'       => array(
                'Authorization' => 'Basic ' . base64_encode( $s4wc->settings['secret_key'] . ':' ),
            ),
            'body'          => $post_data,
            'timeout'       => 70,
            'sslverify'     => false,
            'user-agent'    => 'WooCommerce-Stripe',
        ) );

        return S4WC_API::parse_response( $response );
    }

    /**
     * Delete data from Stripe's servers by passing an API endpoint
     *
     * @access      public
     * @param       string $delete_location
     * @return      array
     */
    public static function delete_data( $delete_location ) {
        global $s4wc;

        $response = wp_remote_post( self::$api_endpoint . $delete_location, array(
            'method'        => 'DELETE',
            'headers'       => array(
                'Authorization' => 'Basic ' . base64_encode( $s4wc->settings['secret_key'] . ':' ),
            ),
            'timeout'       => 70,
            'sslverify'     => false,
            'user-agent'    => 'WooCommerce-Stripe',
        ) );

        return S4WC_API::parse_response( $response );
    }

    /**
     * Parse Stripe's response after interacting with the API
     *
     * @access      public
     * @param       array $response
     * @return      array
     */
    public static function parse_response( $response ) {
        if ( is_wp_error( $response ) ) {
            throw new Exception( 's4wc_problem_connecting' );
        }

        if ( empty( $response['body'] ) ) {
            throw new Exception( 's4wc_empty_response' );
        }

        $parsed_response = json_decode( $response['body'] );

        // Handle response
        if ( ! empty( $parsed_response->error ) && ! empty( $parsed_response->error->code ) ) {
            throw new Exception( $parsed_response->error->code );
        } elseif ( empty( $parsed_response->id ) ) {
            throw new Exception( 's4wc_invalid_response' );
        }

        return $parsed_response;
    }

    /**
     * Finds the index of the default card in the returned data array of objects
     *
     * @access      public
     * @param       array $haystack
     * @param       string $name
     * @param       string $needle
     * @return      int
     */
    public static function find_card_index( $haystack, $name, $needle ) {

        foreach ( $haystack as $index => $element ) {

            if ( isset( $element->$name ) ) {

                if ( $element->$name == $needle ) {
                    return $index;
                }
            } else {
                return -1;
            }
        }

        return -1;
    }
}

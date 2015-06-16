<?php
/**
 * Functions for interfacing with the database
 *
 * @class       S4WC_DB
 * @author      Stephen Zuniga
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class S4WC_DB {

    /**
     * Add/Update the customer database object
     *
     * @access      public
     * @param       int $user_id
     * @param       array $customer_data
     * @return      mixed
     */
    public static function update_customer( $user_id, $customer_data ) {
        global $s4wc;

        // Sample structure of $customer_data
        // $customer_data = array(
        //  'customer_id'   => 'cus_4FP7ML8QPaZNmc',
        //  'card'          => array(
        //      'id'            => 'card_104FP72XTgyB3Fd3Pw9jM2Xh',
        //      'last4'         => '4242',
        //      'brand'         => 'Visa',
        //      'exp_month'     => 12,
        //      'exp_year'      => 2016
        //  ),
        //  'default_card'  => 'card_104FP72XTgyB3Fd3Pw9jM2Xh'
        // );

        if ( ! isset( $customer_data ) ) {
            return;
        }

        // Set variables related to the form fields we're updating
        $customer_id = isset( $customer_data['customer_id'] ) ? $customer_data['customer_id'] : null;

        if ( isset( $customer_data['card'] ) ) {
            $card_id        = isset( $customer_data['card']['id'] ) ? $customer_data['card']['id'] : null;
            $card_last4     = isset( $customer_data['card']['last4'] ) ? $customer_data['card']['last4'] : null;
            $card_brand     = isset( $customer_data['card']['brand'] ) ? $customer_data['card']['brand'] : null;
            $card_exp_month = isset( $customer_data['card']['exp_month'] ) ? $customer_data['card']['exp_month'] : null;
            $card_exp_year  = isset( $customer_data['card']['exp_year'] ) ? $customer_data['card']['exp_year'] : null;
        }

        $default_card = isset( $customer_data['default_card'] ) ? $customer_data['default_card'] : null;

        // Grab the current object out of the database and return a useable array
        $currentObject = maybe_unserialize( get_user_meta( $user_id, $s4wc->settings['stripe_db_location'], true ) );

        // If there is an exising object, append values
        if ( $currentObject ) {
            $newObject = $currentObject;

            // Add a new card to the object
            if ( isset( $customer_data['card'] ) ) {
                $newObject['cards'][] = array(
                    'id'        => $card_id,
                    'last4'     => $card_last4,
                    'brand'     => $card_brand,
                    'exp_month' => $card_exp_month,
                    'exp_year'  => $card_exp_year
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

            $newObject['customer_id']   = $customer_id;
            $newObject['cards']         = array();

            // Add a new card to the object
            if ( isset( $customer_data['card'] ) ) {
                $newObject['cards'][]       = array(
                    'id'        => $card_id,
                    'last4'     => $card_last4,
                    'brand'     => $card_brand,
                    'exp_month' => $card_exp_month,
                    'exp_year'  => $card_exp_year
                );
            }
            $newObject['default_card'] = $default_card;
        }

        // Add to the database
        return update_user_meta( $user_id, $s4wc->settings['stripe_db_location'], $newObject );
    }

    /**
     * Delete from the customer database object
     *
     * @access      public
     * @param       int $user_id
     * @param       array $customer_data
     * @return      mixed
     */
    public static function delete_customer( $user_id, $customer_data ) {
        global $s4wc;

        // Sample structure of $customer_data
        // $customer_data = array(
        //  'card' => 'card_104FP72XTgyB3Fd3Pw9jM2Xh'
        // );

        if ( ! isset( $customer_data ) ) {
            return false;
        }

        // Grab the current object out of the database and return a useable array
        $currentObject = maybe_unserialize( get_user_meta( $user_id, $s4wc->settings['stripe_db_location'], true ) );

        // If the object exists already, do work
        if ( $currentObject ) {
            $newObject = $currentObject;

            // If a card id is passed, delete the card from the database object
            if ( isset( $customer_data['card'] ) ) {
                unset( $newObject['cards'][ recursive_array_search( $customer_data['card'], $newObject['cards'] ) ] );
            }

            // Add to the database
            return update_user_meta( $user_id, $s4wc->settings['stripe_db_location'], $newObject );
        } else {
            return false;
        }
    }
}

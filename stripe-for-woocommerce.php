<?php
/*
 * Plugin Name: Stripe for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/stripe-for-woocommerce
 * Description: Use Stripe for collecting credit card payments on WooCommerce.
 * Version: 1.37
 * Author: Stephen Zuniga
 * Author URI: http://stephenzuniga.com
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * Foundation built by: Sean Voss // https://github.com/seanvoss/striper
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class S4WC {

    public function __construct() {
        global $wpdb;

        // Include Stripe Methods
        include_once( 'classes/class-s4wc_api.php' );

        // Include Database Manipulation Methods
        include_once( 'classes/class-s4wc_db.php' );

        // Include Customer Profile Methods
        include_once( 'classes/class-s4wc_customer.php' );

        // Grab settings
        $this->settings = get_option( 'woocommerce_s4wc_settings', array() );

        // Add default values for fresh installs
        $this->settings['testmode']                 = isset( $this->settings['testmode'] ) ? $this->settings['testmode'] : 'yes';
        $this->settings['test_publishable_key']     = isset( $this->settings['test_publishable_key'] ) ? $this->settings['test_publishable_key'] : '';
        $this->settings['test_secret_key']          = isset( $this->settings['test_secret_key'] ) ? $this->settings['test_secret_key'] : '';
        $this->settings['live_publishable_key']     = isset( $this->settings['live_publishable_key'] ) ? $this->settings['live_publishable_key'] : '';
        $this->settings['live_secret_key']          = isset( $this->settings['live_secret_key'] ) ? $this->settings['live_secret_key'] : '';
        $this->settings['saved_cards']              = isset( $this->settings['saved_cards'] ) ? $this->settings['saved_cards'] : 'yes';

        // API Info
        $this->settings['publishable_key']          = $this->settings['testmode'] == 'yes' ? $this->settings['test_publishable_key'] : $this->settings['live_publishable_key'];
        $this->settings['secret_key']               = $this->settings['testmode'] == 'yes' ? $this->settings['test_secret_key'] : $this->settings['live_secret_key'];

        // Database info location
        $this->settings['stripe_db_location']       = $this->settings['testmode'] == 'yes' ? '_stripe_test_customer_info' : '_stripe_live_customer_info';

        // Hooks
        add_filter( 'woocommerce_payment_gateways', array( $this, 'add_stripe_gateway' ) );
        add_action( 'woocommerce_order_status_processing_to_completed', array( $this, 'order_status_completed' ) );

        // Localization
        load_plugin_textdomain( 'stripe-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    /**
     * Add Stripe Gateway to WooCommerces list of Gateways
     *
     * @access      public
     * @param       array $methods
     * @return      array
     */
    public function add_stripe_gateway( $methods ) {
        if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
            return;
        }

        // Include payment gateway
        include_once( 'classes/class-s4wc_gateway.php' );

        if ( class_exists( 'WC_Subscriptions_Order' ) ) {
            include_once( 'classes/class-s4wc_subscriptions_gateway.php' );

            $methods[] = 'S4WC_Subscriptions_Gateway';
        } else {
            $methods[] = 'S4WC_Gateway';
        }

        return $methods;
    }

    /**
     * Localize Stripe error messages
     *
     * @access      protected
     * @param       Exception $e
     * @return      string
     */
    public function get_error_message( $e ) {

        switch ( $e->getMessage() ) {

            // Messages from Stripe API
            case 'incorrect_number':
                $message = __( 'Your card number is incorrect.', 'stripe-for-woocommerce' );
                break;
            case 'invalid_number':
                $message = __( 'Your card number is not a valid credit card number.', 'stripe-for-woocommerce' );
                break;
            case 'invalid_expiry_month':
                $message = __( 'Your card\'s expiration month is invalid.', 'stripe-for-woocommerce' );
                break;
            case 'invalid_expiry_year':
                $message = __( 'Your card\'s expiration year is invalid.', 'stripe-for-woocommerce' );
                break;
            case 'invalid_cvc':
                $message = __( 'Your card\'s security code is invalid.', 'stripe-for-woocommerce' );
                break;
            case 'expired_card':
                $message = __( 'Your card has expired.', 'stripe-for-woocommerce' );
                break;
            case 'incorrect_cvc':
                $message = __( 'Your card\'s security code is incorrect.', 'stripe-for-woocommerce' );
                break;
            case 'incorrect_zip':
                $message = __( 'Your zip code failed validation.', 'stripe-for-woocommerce' );
                break;
            case 'card_declined':
                $message = __( 'Your card was declined.', 'stripe-for-woocommerce' );
                break;

            // Messages from S4WC
            case 's4wc_problem_connecting':
            case 's4wc_empty_response':
            case 's4wc_invalid_response':
                $message = __( 'There was a problem connecting to the payment gateway.', 'stripe-for-woocommerce' );
                break;

            // Generic failed order
            default:
                $message = __( 'Failed to process the order, please try again later.', 'stripe-for-woocommerce' );
        }

        return $message;
    }

    /**
     * Process the captured payment when changing order status to completed
     *
     * @access      public
     * @param       int $order_id
     * @return      mixed
     */
    public function order_status_completed( $order_id = null ) {

        if ( ! $order_id ) {
            $order_id = $_POST['order_id'];
        }

        // `_s4wc_capture` added in 1.35, let `capture` last for a few more updates before removing
        if ( get_post_meta( $order_id, '_s4wc_capture', true ) || get_post_meta( $order_id, 'capture', true ) ) {

            $order = new WC_Order( $order_id );
            $params = array(
                'amount' => isset( $_POST['amount'] ) ? $_POST['amount'] : $order->order_total * 100,
                'expand[]' => 'balance_transaction',
            );

            try {
                $charge = S4WC_API::capture_charge( $order->transaction_id, $params );

                if ( $charge ) {
                    $order->add_order_note(
                        sprintf(
                            __( '%s payment captured.', 'stripe-for-woocommerce' ),
                            get_class( $this )
                        )
                    );

                    // Save Stripe fee
                    if ( isset( $charge->balance_transaction ) && isset( $charge->balance_transaction->fee ) ) {
                        $stripe_fee = number_format( $charge->balance_transaction->fee / 100, 2, '.', '' );
                        update_post_meta( $order_id, 'Stripe Fee', $stripe_fee );
                    }
                }
            } catch ( Exception $e ) {
                $order->add_order_note(
                    sprintf(
                        __( '%s payment failed to capture. %s', 'stripe-for-woocommerce' ),
                        get_class( $this ),
                        $this->get_error_message( $e )
                    )
                );
            }
        }
    }
}

$GLOBALS['s4wc'] = new S4WC();

/**
 * Wrapper of wc_get_template to relate directly to s4wc
 *
 * @param       string $template_name
 * @param       array $args
 * @return      string
 */
function s4wc_get_template( $template_name, $args = array() ) {
    $template_path = WC()->template_path() . '/s4wc/';
    $default_path = plugin_dir_path( __FILE__ ) . '/templates/';

    return wc_get_template( $template_name, $args, $template_path, $default_path );
}

/**
 * Helper function to find the key of a nested value
 *
 * @param       string $needle
 * @param       array $haystack
 * @return      mixed
 */
if ( ! function_exists( 'recursive_array_search' ) ) {
    function recursive_array_search( $needle, $haystack ) {

        foreach ( $haystack as $key => $value ) {

            if ( $needle === $value || ( is_array( $value ) && recursive_array_search( $needle, $value ) !== false ) ) {
                return $key;
            }
        }
        return false;
    }
}

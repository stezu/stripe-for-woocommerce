<?php
/**
 * Stripe Gateway
 *
 * Provides a Stripe Payment Gateway.
 *
 * @class       S4WC_Gateway
 * @extends     Abstract_S4WC_Gateway
 * @version     1.31
 * @package     WooCommerce/Classes/Payment
 * @author      Stephen Zuniga
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class S4WC_Gateway extends Abstract_S4WC_Gateway {

    public function __construct() {
        
        parent::__construct();
    }

    /**
     * Set up the charge that will be sent to Stripe
     *
     * @access      private
     * @return      void
     */
    private function charge_set_up() {

        // Allow options to be set without modifying sensitive data like amount, currency, etc.
        $stripe_charge_data = apply_filters( 's4wc_charge_data', array(), $this->form_data, $this->order );

        // Set up basics for charging
        $stripe_charge_data['amount']   = $this->form_data['amount']; // amount in cents
        $stripe_charge_data['currency'] = $this->form_data['currency'];
        $stripe_charge_data['capture']  = ( $this->settings['charge_type'] == 'capture' ) ? 'true' : 'false';

        // Make sure we only create customers if a user is logged in
        if ( is_user_logged_in() && $this->settings['saved_cards'] === 'yes' ) {

            // Add a customer or retrieve an existing one
            $customer = $this->get_customer();

            $stripe_charge_data['card'] = $customer['card'];
            $stripe_charge_data['customer'] = $customer['customer_id'];

            // Update default card
            if ( count( $this->stripe_customer_info['cards'] ) && $this->form_data['chosen_card'] !== 'new' ) {
                $default_card = $this->stripe_customer_info['cards'][ intval( $this->form_data['chosen_card'] ) ]['id'];
                S4WC_DB::update_customer( $this->order->user_id, array( 'default_card' => $default_card ) );
            }

        } else {

            // Set up one time charge
            $stripe_charge_data['card'] = $this->form_data['token'];
        }

        // Charge description
        $stripe_charge_data['description'] = $this->get_charge_description();

        // Create the charge on Stripe's servers - this will charge the user's card
        $charge = S4WC_API::create_charge( $stripe_charge_data );

        $this->transaction_id = $charge->id;
    }
}

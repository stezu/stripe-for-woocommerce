<?php
/**
 * Stripe Subscription Gateway
 *
 * Provides a Stripe Payment Gateway for Subscriptions.
 *
 * @class       S4WC_Subscriptions_Gateway
 * @extends     S4WC_Gateway
 * @version     1.25
 * @package     WooCommerce/Classes/Payment
 * @author      Stephen Zuniga
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class S4WC_Subscriptions_Gateway extends S4WC_Gateway {

    public function __construct() {
        parent::__construct();

        // Hooks
        add_action( 'scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 3 );
    }

    /**
     * Process the payment and return the result
     *
     * @access      public
     * @param       int $order_id
     * @return      array
     */
    public function process_payment( $order_id ) {

        if ( WC_Subscriptions_Order::order_contains_subscription( $order_id ) ) {
            $this->order = new WC_Order( $order_id );

            if ( $this->subscription_to_stripe() ) {
                $this->order_complete();

                WC_Subscriptions_Manager::activate_subscriptions_for_order( $this->order );

                $result = array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url( $this->order )
                );

                return $result;
            } else {
                $this->payment_failed();

                // Add a generic error message if we don't currently have any others
                if ( wc_notice_count( 'error' ) == 0 ) {
                    wc_add_notice( __( 'Transaction Error: Could not complete your subscription payment.', 'stripe-for-woocommerce' ), 'error' );
                }
            }
        } else {
            return parent::process_payment( $order_id );
        }
    }

    /**
     * Process the subscription payment and return the result
     *
     * @access      public
     * @param       WC_Order $order
     * @param       int $amount
     * @return      array
     */
    public function process_subscription_payment( $order, $amount = 0 ) {
        global $s4wc;

        // Can't send to stripe without a value, assume it's good to go.
        if ( $amount === 0 ) {
            return true;
        }

        // Get customer id
        $customer = get_user_meta( $order->user_id, $s4wc->settings['stripe_db_location'], true );

        // Set a default name, override with a product name if it exists for Stripe's dashboard
        $product_name = __( 'Subscription', 'stripe-for-woocommerce' );
        $order_items = $order->get_items();

        // Grab first subscription name and use it
        foreach ( $order_items as $key => $item ) {
            if ( isset( $item['subscription_status'] ) ) {
                $product_name = $item['name'];
                break;
            }
        }

        // Charge description
        $charge_description = sprintf(
            __( 'Payment for %s (Order: %s)', 'stripe-for-woocommerce' ),
            $product_name,
            $order->get_order_number()
        );

        // Set up basics for charging
        $charge_data = array(
            'amount'        => $amount * 100, // amount in cents
            'currency'      => strtolower( get_woocommerce_currency() ),
            'description'   => apply_filters( 's4wc_subscription_charge_description', $charge_description, $order ),
            'customer'      => $customer['customer_id'],
            'card'          => $customer['default_card']
        );
        $charge = S4WC_API::create_charge( $charge_data );

        if ( isset( $charge->id ) ) {
            $order->add_order_note( sprintf( __( 'Subscription paid (%s)', 'stripe-for-woocommerce' ), $charge->id ) );

            return $charge;
        }
        return false;
    }

    /**
     * Send subscription form data to Stripe
     * Handles sending the charge to an existing customer, a new customer (that's logged in), or a guest
     *
     * @access      protected
     * @return      bool
     */
    protected function subscription_to_stripe() {

        // Get the credit card details submitted by the form
        $form_data = $this->get_form_data();

        // If there are errors on the form, don't bother sending to Stripe.
        if ( $form_data['errors'] == 1 ) {
            return;
        }

        // Set up the charge for Stripe's servers
        try {

            // Add a customer or retrieve an existing one
            $description = $this->current_user->user_login . ' (#' . $this->current_user_id . ' - ' . $this->current_user->user_email . ') ' . $form_data['customer']['name']; // username (user_id - user_email) Full Name
            $customer = $this->get_customer( $description, $form_data );

            // Update default card
            if ( $form_data['chosen_card'] !== 'new' ) {
                $default_card = $this->stripe_customer_info['cards'][ $form_data['chosen_card'] ]['id'];
                S4WC_DB::update_customer( $this->current_user_id, array( 'default_card' => $default_card ) );
            }

            $initial_payment = WC_Subscriptions_Order::get_total_initial_payment( $this->order );

            $charge = $this->process_subscription_payment( $this->order, $initial_payment );

            $this->transaction_id = $charge->id;

            // Save data for the "Capture"
            update_post_meta( $this->order->id, '_transaction_id', $this->transaction_id );
            update_post_meta( $this->order->id, 'capture', strcmp( $this->settings['charge_type'], 'authorize' ) == 0 );

            // Save data for cross-reference between Stripe Dashboard and WooCommerce
            update_post_meta( $this->order->id, 'customer_id', $customer['customer_id'] );

            return true;

        } catch ( Exception $e ) {

            // Stop page reload if we have errors to show
            unset( WC()->session->reload_checkout );

            $message = $this->get_stripe_error_message( $e );

            wc_add_notice( __( 'Subscription Error:', 'stripe-for-woocommerce' ) . ' ' . $message, 'error' );

            return false;
        }
    }

    /**
     * Process a scheduled payment
     *
     * @access      public
     * @param       float $amount_to_charge
     * @param       WC_Order $order
     * @param       int $product_id
     * @return      void
     */
    public function scheduled_subscription_payment( $amount_to_charge, $order, $product_id ) {
        $charge = $this->process_subscription_payment( $order, $amount_to_charge );

        if ( $charge ) {
            WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
        } else {
            WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order, $product_id );
        }
    }
}

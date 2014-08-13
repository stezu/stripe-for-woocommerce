<?php
/**
 * Stripe Subscription Gateway
 *
 * Provides a Stripe Payment Gateway for Subscriptions.
 *
 * @class		S4WC_Subscriptions_Gateway
 * @extends		S4WC_Gateway
 * @version		1.22
 * @package		WooCommerce/Classes/Payment
 * @author		Stephen Zuniga
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class S4WC_Subscriptions_Gateway extends S4WC_Gateway {

	/**
	 * Constructor for the gateway.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		parent::__construct();

		// Hooks
		add_action( 'scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 3 );
	}

	/**
	 * Send subscription form data to Stripe
	 * Handles sending the charge to an existing customer, a new customer (that's logged in), or a guest
	 *
	 * @access protected
	 * @return boolean
	 */
	protected function subscription_to_stripe() {
		global $woocommerce, $s4wc;

		// Get the credit card details submitted by the form
		$form_data = $this->get_form_data();

		// If there are errors on the form, don't bother sending to Stripe.
		if ( $form_data['errors'] == 1 ) {
			return;
		}

		// Get customer id
		$customer = get_user_meta( $this->current_user_id, $s4wc->settings['stripe_db_location'], true );

		// Update default card
		$default_card = $customer['cards'][ $form_data['chosen_card'] ]['id'];
		S4WC_DB::update_customer( $this->current_user_id, array( 'default_card' => $default_card ) );

		// Set up the charge for Stripe's servers
		try {
			$initial_payment = WC_Subscriptions_Order::get_total_initial_payment( $this->order );

			$charge = $this->process_subscription_payment( $initial_payment, $this->order );

			$this->transaction_id = $charge->id;

			// Save data for the "Capture"
			update_post_meta( $this->order->id, 'transaction_id', $this->transaction_id );
			update_post_meta( $this->order->id, 'capture', strcmp( $this->charge_type, 'authorize' ) == 0 );

			// Save data for cross-reference between Stripe Dashboard and WooCommerce
			update_post_meta( $this->order->id, 'customer_id', $customer['customer_id'] );

			return true;

		} catch ( Exception $e ) {
			$this->transaction_error_message = $e->getMessage();
			wc_add_notice( __( 'Error:', 'stripe-for-woocommerce' ) . ' ' . $e->getMessage(), 'error' );

			return false;
		}
	}

	/**
	 * Process a scheduled payment
	 *
	 * @access public
	 * @param float $amount_to_charge
	 * @param WC_Order $order
	 * @param int $product_id
	 * @return void
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $order, $product_id ) {
		$charge = $this->process_subscription_payment( $amount_to_charge, $order );

		if ( $charge ) {
			WC_Subscriptions_Manager::process_subscription_payments_on_order( $order );
		} else {
			WC_Subscriptions_Manager::process_subscription_payment_failure_on_order( $order, $product_id );
		}
	}

	/**
	 * Process the payment and return the result
	 *
	 * @access public
	 * @param int $order_id
	 * @return array
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
				wc_add_notice( __( 'Transaction Error: Could not complete your payment.', 'stripe-for-woocommerce' ), 'error' );
			}
		} else {
			return parent::process_payment( $order_id );
		}
	}

	/**
	 * Process the subscription payment and return the result
	 *
	 * @access public
	 * @param int $amount
	 * @param WC_Order $order
	 * @return array
	 */
	public function process_subscription_payment( $amount = 0, $order ) {
		global $s4wc;

		// Can't send to stripe without a value, assume it's good to go.
		if ( $amount === 0 ) {
			return true;
		}

		// Set a default name, override with a subscription if it exists for Stripe's dashboard
		$product_name = 'Subscription';
		$order_items = $order->get_items();
		foreach ( $order_items as $key => $item ) {
			if ( isset( $item['subscription_status'] ) ) {
				$product_name = $item['name'];
				break;
			}
		}

		// Get customer id
		$customer = get_user_meta( $order->user_id, $s4wc->settings['stripe_db_location'], true );

		// Charge description
		$charge_description = 'Payment for ' . $product_name . ' (Order: ' . $order->get_order_number() . ')';
		// Set up basics for charging
		$charge_data = array(
			'amount'		=> $amount * 100, // amount in cents
			'currency'		=> strtolower( get_woocommerce_currency() ),
			'description'	=> apply_filters( 's4wc_subscription_charge_description', $charge_description, $charge_description, $order ),
			'customer'		=> $customer['customer_id'],
			'card'			=> $customer['default_card']
		);
		$charge = S4WC_API::create_charge( $charge_data );

		if ( isset( $charge->id ) ) {
			$order->add_order_note( sprintf( __( 'Subscription paid (%s)', 'stripe-for-woocommerce' ), $charge->id ) );

			return $charge;
		}
		return false;
	}
}

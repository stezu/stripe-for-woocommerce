<?php
/**
 * Stripe Subscription Gateway
 *
 * Provides a Stripe Payment Gateway for Subscriptions.
 *
 * @class		S4WC_Subscriptions_Gateway
 * @extends		S4WC_Gateway
 * @version		1.11
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
	 * Send form data to Stripe
	 * Handles sending the charge to an existing customer, a new customer (that's logged in), or a guest
	 *
	 * @access protected
	 * @return boolean
	 */
	protected function send_to_stripe() {
		global $woocommerce;

		// Get the credit card details submitted by the form
		$data = $this->get_form_data();

		// If there are errors on the form, don't bother sending to Stripe.
		if ( $data['errors'] == 1 ) {
			return;
		}

		// Set up the charge for Stripe's servers
		try {
			$initial_payment = WC_Subscriptions_Order::get_total_initial_payment( $this->order );

			$charge = $this->process_subscription_payment( $this->order, $initial_payment );

			$this->transactionId = $charge->id;

			// Save data for the "Capture"
			update_post_meta( $this->order->id, 'transaction_id', $this->transactionId );
			update_post_meta( $this->order->id, 'capture', strcmp( $this->charge_type, 'authorize' ) == 0 );

			// Save data for cross-reference between Stripe Dashboard and WooCommerce
			update_post_meta( $this->order->id, 'customer_id', $customer['id'] );

			return true;

		} catch ( Exception $e ) {
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
		$charge = $this->process_subscription_payment( $order, $amount_to_charge );

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

			if ( $this->send_to_stripe() ) {
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
	 * @param WC_Order $order
	 * @param int $amount
	 * @return array
	 */
	public function process_subscription_payment( $order, $amount = 0 ) {
		$product_name = 'Subscription';
		$order_items = $order->get_items();
		foreach ( $order_items as $key => $item ) {
			if ( isset( $item['subscription_status'] ) ) {
				$product_name = $item['name'];
				break;
			}
		}

		// Get customer id from order meta
		$customer = get_post_meta( $order->id, 'customer_id', true );

		// Set up basics for charging
		$charge_data = array(
			'amount'		=> $amount * 100, // amount in cents
			'currency'		=> strtolower( get_woocommerce_currency() ),
			'description'	=> __( 'Payment for ' . $product_name . '(Order: ' . $order->get_order_number() . ')', 'stripe-for-woocommerce' ),
			'customer'		=> $customer
		);
		$charge = S4WC_API::create_charge( $charge_data );

		if ( isset( $charge->id ) ) {
			$order->add_order_note( sprintf( __( 'Subscription paid (%s)', 'stripe-for-woocommerce' ), $charge->id ) );

			return $charge;
		}
		return false;
	}
}

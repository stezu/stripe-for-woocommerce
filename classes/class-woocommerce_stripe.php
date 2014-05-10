<?php
class Woocommerce_Stripe extends WC_Payment_Gateway {
	protected $GATEWAY_NAME				= 'wc_stripe';
	protected $order					= null;
	protected $transactionId			= null;
	protected $transactionErrorMessage	= null;

	public function __construct() {
		$this->id						= 'wc_stripe';
		$this->method_title				= 'WooCommerce Stripe';
		$this->has_fields				= true;

		// Init settings
		$this->init_form_fields();
		$this->init_settings();

		// Get settings
		$this->enabled					= $this->settings['enabled'];
		$this->title					= $this->settings['title'];
		$this->description				= $this->settings['description'];
		$this->testmode					= $this->settings['testmode'];
		$this->capture					= $this->settings['capture'];
		$this->additional_fields		= $this->settings['additional_fields'];

		// Get API Keys
		$this->publishable_key			= $this->testmode == 'yes' ? $this->settings['test_publishable_key'] : $this->settings['live_publishable_key'];
		$this->secret_key				= $this->testmode == 'yes' ? $this->settings['test_secret_key'] : $this->settings['live_secret_key'];

		// Get current user information
		$this->current_user				= wp_get_current_user();
		$this->current_user_id			= get_current_user_id();
		$this->stripe_customer_info		= get_user_meta( $this->current_user_id, '_stripe_customer_info' );

		// Hooks
		add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'admin_notices', array( $this, 'perform_checks' ) );
		add_action( 'wp_enqueue_scripts', array( &$this, 'load_scripts' ) );

		// Set Stripe secret key
		Stripe::setApiKey( $this->secret_key );
	}

	public function perform_checks() {
		global $woocommerce;

		if ( $this->enabled == 'no') {
			return;
		}

		// We're using the credit card field bundles with WC 2.1.0, and this entire plugin won't work without it
		if ( $woocommerce->version < '2.1.0' ) {
			echo '<div class="error"><p>Stripe for WooCommerce uses some advanced features introduced in WooCommerce 2.1.0. Please update WooCommerce to continue using Stripe for WooCommerce.</p></div>';
			return;
		}

		// Check for API Keys
		if ( ! $this->publishable_key && ! $this->secret_key ) {
			echo '<div class="error"><p>Stripe needs API Keys to work, please find your secret and publishable keys in the <a href="https://manage.stripe.com/account/apikeys" target="_blank">Stripe accounts section</a>.</p></div>';
			return;
		}

		// Force SSL on production
		if ( $this->testmode == 'no' && get_option( 'woocommerce_force_ssl_checkout' ) == 'no' ) {
			echo '<div class="error"><p>Stripe needs SSL in order to be secure. Read mode about forcing SSL on checkout in <a href="http://docs.woothemes.com/document/ssl-and-https/" target="_blank">the WooCommerce docs</a>.</p></div>';
			return;
		}
	}

	// Disable plugin if checks fail
	public function is_available() {
		global $woocommerce;

		if ( $this->enabled == 'no' ) {
			return false;
		}

		// We're using the credit card field bundles with WC 2.1.0, and this entire plugin won't work without it
		if ( $woocommerce->version < '2.1.0' ) {
			return false;
		}

		// Stripe won't work without keys
		if ( ! $this->publishable_key && ! $this->secret_key ) {
			return false;
		}

		// Disable plugin if we don't use ssl
		if ( is_ssl() && $this->testmode == 'no' ) {
			return false;
		}

		return true;
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'type'			=> 'checkbox',
				'title'			=> __( 'Enable/Disable', 'woothemes' ),
				'label'			=> __( 'Enable Credit Card Payment', 'woothemes' ),
				'default'		=> 'yes'
			),
			'capture' => array(
				'type'			=> 'checkbox',
				'title'			=> __( 'Auth & Capture', 'woothemes' ),
				'description'	=> __( 'This authorizes payment on checkout, but doesn\'t charge until you manually set the order as processing', 'woothemes' ),
				'label'			=> __( 'Enable Authorization & Capture', 'woothemes' ),
				'default'		=> 'no'
			),
			'testmode' => array(
				'type'			=> 'checkbox',
				'title'			=> __( 'Testing', 'woothemes' ),
				'label'			=> __( 'Turn on testing', 'woothemes' ),
				'default'		=> 'no'
			),
			'title' => array(
				'type'			=> 'text',
				'title'			=> __( 'Title', 'woothemes' ),
				'description'	=> __( 'This controls the title which the user sees during checkout.', 'woothemes' ),
				'default'		=> __( 'Credit Card Payment', 'woothemes' )
			),
			'description' => array(
				'type'			=> 'text',
				'title'			=> __( 'Description', 'woothemes' ),
				'description'	=> __( 'This controls the description which the user sees during checkout.', 'woothemes' ),
				'default'		=> __( '', 'woothemes' )
			),
			'additional_fields' => array(
				'type'			=> 'checkbox',
				'title'			=> __( 'Additional Fields', 'woothemes' ),
				'description'	=> __( 'This enables the use of a Billing ZIP and a Name on Card for Stripe authentication purposes. This is only neccessary if you check the "Only ship to the users billing address" box on WooCommerce Shipping settings.', 'woothemes' ),
				'label'			=> __( 'Use Additional Fields', 'woothemes' ),
				'default'		=> 'no'
			),
			'test_secret_key'	=> array(
				'type'			=> 'text',
				'title'			=> __( 'Stripe API Test Secret key', 'woothemes' ),
				'default'		=> __( '', 'woothemes' )
			),
			'test_publishable_key' => array(
				'type'			=> 'text',
				'title'			=> __( 'Stripe API Test Publishable key', 'woothemes' ),
				'default'		=> __( '', 'woothemes' )
			),
			'live_secret_key'	=> array(
				'type'			=> 'text',
				'title'			=> __( 'Stripe API Live Secret key', 'woothemes' ),
				'default'		=> __( '', 'woothemes' )
			),
			'live_publishable_key' => array(
				'type'			=> 'text',
				'title'			=> __( 'Stripe API Live Publishable key', 'woothemes' ),
				'default'		=> __( '', 'woothemes' )
			),
		);
	}

	public function admin_options() {
		?>
		<h3>Credit Card Payment</h3>
		<p>Allows Credit Card payments.</p>
		<table class="form-table">
			<?php $this->generate_settings_html(); ?>
		</table>
		<?php
	}

	public function load_scripts() {
		// Main stripe js
		wp_enqueue_script( 'stripe', 'https://js.stripe.com/v2/', '', '1.0', true );

		// Plugin js
		wp_enqueue_script( 'wc_stripe_js', plugins_url( 'assets/js/wc_stripe.js', dirname( __FILE__ ) ), array( 'stripe' ), '1.0', true );

		$wc_stripe_info = array(
			'publishableKey'	=> $this->publishable_key,
			'hasCard'			=> $this->stripe_customer_info ? true : false
		);

		wp_localize_script( 'wc_stripe_js', 'wc_stripe_info', $wc_stripe_info );
	}

	public function payment_fields() {
		if( is_user_logged_in() && $this->stripe_customer_info ) :

			// Add option to use a saved card
			foreach ( $this->stripe_customer_info as $i => $credit_card ) : ?>

				<input type="radio" id="stripe_card_<?php echo $i; ?>" name="wc_stripe_card" value="<?php echo $i; ?>" checked>
				<label for="stripe_card_<?php echo $i; ?>">Card ending with <?php echo $credit_card['last4']; ?> (<?php echo $credit_card['exp_month']; ?>/<?php echo $credit_card['exp_year']; ?>)</label><br>

			<?php endforeach; ?>

			<input type="radio" id="new_card" name="wc_stripe_card" value="new">
			<label for="new_card">Use a new credit card</label>

		<?php endif; ?>

		<div id="wc_stripe-creditcard-form">

			<?php if ( $this->additional_fields == 'yes' ) : ?>
				<p class="form-row form-row-first address-field validate-required" id="wc_stripe_name_field">
					<label for="wc_stripe-billing-name">Name on Card <abbr class="required">*</abbr></label>
					<input type="text" class="input-text" name="wc_stripe-billing-name" id="wc_stripe-billing-name">
				</p>

				<p class="form-row form-row-last address-field validate-required validate-postcode" id="wc_stripe_postcode_field">
					<label for="wc_stripe-billing-zip">Billing Zip <abbr class="required">*</abbr></label>
					<input type="text" class="input-text" name="wc_stripe-billing-zip" id="wc_stripe-billing-zip" placeholder="Postcode / Zip">
				</p>
			<?php endif; ?>

			<?php

			$cc_number = woocommerce_form_field( 'card-number', array(
				'label'			=> 'Card Number',
				'placeholder'	=> '•••• •••• •••• ••••',
				'maxlength'		=> 20,
				'required'		=> true,
				'input_class'	=> array( 'wc_stripe-card-number' ),
				'return'		=> true
			) );
			$cc_number = preg_replace( '/name=".*?\"/i', '', $cc_number );
			echo $cc_number;

			$cc_expiry = woocommerce_form_field( 'card-expiry', array(
				'label'			=> 'Expiry (MM/YY)',
				'placeholder'	=> 'MM / YY',
				'required'		=> true,
				'class'			=> array( 'form-row-first' ),
				'input_class'	=> array( 'wc_stripe-card-expiry' ),
				'return'		=> true
			) );
			$cc_expiry = preg_replace( '/name=".*?\"/i', '', $cc_expiry );
			echo $cc_expiry;

			$cc_cvc = woocommerce_form_field( 'card-cvc', array(
				'label'			=> 'Card Code',
				'placeholder'	=> 'CVC',
				'required'		=> true,
				'class'			=> array( 'form-row-last' ),
				'input_class'	=> array( 'wc_stripe-card-cvc' ),
				'return'		=> true,
				'clear'			=> true
			) );
			$cc_cvc = preg_replace( '/name=".*?\"/i', '', $cc_cvc );
			echo $cc_cvc;

			?>
		</div>
		<?php
	}

	protected function send_to_stripe() {
		global $woocommerce;

		// Get the credit card details submitted by the form
		$data = $this->get_form_data();

		// Set up basics for charging
		$customer_description = $this->current_user->user_login . ' (#' . $this->current_user_id . ' - ' . $this->current_user->user_email . ') ' . $data['card']['name']; // username (user_id - user_email) Full Name
		$stripe_charge_data = array(
			'amount'		=> $data['amount'], // amount in cents
			'currency'		=> $data['currency'],
			'description'	=> $customer_description,
			'capture'		=> strcmp( $this->capture, 'yes' ) != 0,
		);

		// Set up the charge for Stripe's servers
		try {

			// Make sure we only create customers if a user is logged in
			if( is_user_logged_in() ) {

				if ( ! $this->stripe_customer_info ) {
					$customer = $this->create_customer( $data, $customer_description );
				} else {
					// If the user is already registered on the stripe servers, retreive their information
					$customer = Stripe_Customer::retrieve( $this->stripe_customer_info[0]['customer_id'] );

					if ( $data['chosen_card'] == 'new' ) {
						$card = $customer->cards->create( array( 'card' => $data['token'] ) );
						$customer->default_card = $card->id;
						$customer->save();

						add_user_meta( $this->current_user_id, '_stripe_customer_info', array(
							'customer_id'	=> $customer->id,
							'card_id'		=> $card->id,
							'type'			=> $card->type,
							'last4'			=> $card->last4,
							'exp_year'		=> $card->exp_year,
							'exp_month'		=> $card->exp_month,
						) );

						$stripe_charge_data['card'] = $card->id;
					} else {
						$stripe_charge_data['card'] = $this->stripe_customer_info[$data['chosen_card']]['card_id'];
					}
				}

				// Set up charging data to include customer information
				$stripe_charge_data['customer'] = $customer->id;
			} else {
				// Set up one time charge
				$stripe_charge_data['card'] = $data['token'];
			}

			// Create the charge on Stripe's servers - this will charge the user's card
			$charge = Stripe_Charge::create( $stripe_charge_data );

			$this->transactionId = $charge['id'];

			// Save data for the "Capture"
			update_post_meta( $this->order->id, 'transaction_id', $this->transactionId );
			update_post_meta( $this->order->id, 'key', $this->secret_key );
			update_post_meta( $this->order->id, 'auth_capture', strcmp( $this->capture, 'yes' ) == 0 );

			// Save data for cross-reference between Stripe Dashboard and WooCommerce
			update_post_meta( $this->order->id, 'customer_id', $customer->id );

			return true;

		} catch ( Stripe_Error $e ) {
			// The card has been declined, or other error
			$body = $e->getJsonBody();
			$err  = $body['error'];
			error_log( 'Stripe Error:' . $err['message'] . '\n' );

			wc_add_notice( __( 'Payment error:', 'woothemes' ) . $err['message'], 'error' );
			return false;
		}
	}

	protected function create_customer( $data, $customer_description ) {

		$customer = Stripe_Customer::create( array(
			'description'	=> $customer_description,
			'card'			=> $data['token'],
		));
		$card = $customer->cards->retrieve( $customer->default_card );

		// Save users customer information for later use
		add_user_meta( $this->current_user_id, '_stripe_customer_info', array(
			'customer_id'	=> $customer->id,
			'card_id'		=> $card->id,
			'type'			=> $card->type,
			'last4'			=> $card->last4,
			'exp_year'		=> $card->exp_year,
			'exp_month'		=> $card->exp_month,
		) );

		return $customer;
	}

	public static function delete_card( $position ) {
		$user_meta = get_user_meta( get_current_user_id(), '_stripe_customer_info', $position );
		$customer = Stripe_Customer::retrieve( $user_meta['customer_id'] );
		$current_card = $user_meta['card_id'];

		$customer->cards->retrieve( $current_card )->delete();
		delete_user_meta( get_current_user_id(), '_stripe_customer_info', $position );
	}

	public function process_payment( $order_id ) {
		global $woocommerce;

		$this->order = new WC_Order( $order_id );
		if ( $this->send_to_stripe() ) {
			$this->order_complete();

			$result = array(
				'result' => 'success',
				'redirect' => $this->get_return_url( $this->order )
			);
			return $result;
		} else {
			$this->payment_failed();
			wc_add_notice( __( 'Transaction Error: Could not complete your payment', 'woothemes' ), 'error' );
		}
	}

	protected function payment_failed() {
		$this->order->add_order_note(
			sprintf(
				'%s Credit Card Payment Failed with message: "%s"',
				$this->GATEWAY_NAME,
				$this->transactionErrorMessage
			)
		);
	}

	protected function order_complete() {
		global $woocommerce;

		if ($this->order->status == 'completed') {
			return;
		}

		$this->order->payment_complete();
		$woocommerce->cart->empty_cart();

		$this->order->add_order_note(
			sprintf(
				'%s payment completed with Transaction Id of "%s"',
				$this->GATEWAY_NAME,
				$this->transactionId
			)
		);

		unset($_SESSION['order_awaiting_payment']);
	}


	protected function get_form_data()
	{
		if ($this->order && $this->order != null) {
			return array(
				'amount'		=> (float) $this->order->get_total() * 100,
				'currency'		=> strtolower(get_woocommerce_currency()),
				'token'			=> isset( $_POST['stripe_token'] ) ? $_POST['stripe_token'] : '',
				'description'	=> 'Charge for %s' . $this->order->billing_email,
				'chosen_card'	=> isset( $_POST['wc_stripe_card'] ) ? $_POST['wc_stripe_card'] : '',
				'card'			=> array(
					'name'				=> $this->order->billing_first_name . ' ' . $this->order->billing_last_name,
					'billing_email'		=> $this->order->billing_email,
					'address_line1'		=> $this->order->billing_address_1,
					'address_line2'		=> $this->order->billing_address_2,
					'address_zip'		=> $this->order->billing_postcode,
					'address_state'		=> $this->order->billing_state,
					'address_country'	=> $this->order->billing_country,
				)
			);
		}
		return false;
	}
}

//add_action('wp_ajax_capture_striper', 'striper_order_status_completed');

function striper_order_status_completed($order_id = null) {
	global $woocommerce;
	if (!$order_id) {
		$order_id = $_POST['order_id'];
	}

	$data = get_post_meta( $order_id );
	$total = $data['_order_total'][0] * 100;

	$params = array();
	if( isset( $_POST['amount'] ) && $amount = $_POST['amount'] ) {
		$params['amount'] = round( $amount );
	}

	if( get_post_meta( $order_id, 'auth_capture', true ) ) {
		Stripe::setApiKey( get_post_meta( $order_id, 'key', true ) );

		try {
			$tid = get_post_meta( $order_id, 'transaction_id', true );
			$ch = Stripe_Charge::retrieve( $tid );

			if( $total < $ch->amount ) {
				$params['amount'] = $total;
			}
			$ch->capture( $params );
		} catch( Stripe_Error $e ) {
			// There was an error
			$body = $e->getJsonBody();
			$err  = $body['error'];
			error_log('Stripe Error:' . $err['message'] . '\n');
			wc_add_notice(__('Payment error:', 'woothemes') . $err['message'], 'error');
			return null;
		}

		return true;
	}
}

add_action('woocommerce_order_status_processing_to_completed', 'striper_order_status_completed' );
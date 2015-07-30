<?php
/**
 * Stripe Gateway
 *
 * Provides a Stripe Payment Gateway.
 *
 * @class       S4WC_Gateway
 * @extends     WC_Payment_Gateway
 * @package     WooCommerce/Classes/Payment
 * @author      Stephen Zuniga
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class S4WC_Gateway extends WC_Payment_Gateway {
    protected $order                     = null;
    protected $form_data                 = null;
    protected $transaction_id            = null;
    protected $transaction_error_message = null;

    public function __construct() {
        global $s4wc;

        $this->id           = 's4wc';
        $this->method_title = 'Stripe for WooCommerce';
        $this->has_fields   = true;
        $this->supports     = array(
            'default_credit_card_form',
            'products',
            'subscriptions',
            'subscription_cancellation',
            'subscription_suspension',
            'subscription_reactivation',
            'subscription_amount_changes',
            'subscription_date_changes',
            'subscription_payment_method_change',
            'refunds'
        );

        // Init settings
        $this->init_form_fields();
        $this->init_settings();

        // Use settings
        $this->enabled     = $this->settings['enabled'];
        $this->title       = $this->settings['title'];
        $this->description = $this->settings['description'];

        // Get current user information
        $this->stripe_customer_info = get_user_meta( get_current_user_id(), $s4wc->settings['stripe_db_location'], true );

        // Add an icon with a filter for customization
        $icon_url = apply_filters( 's4wc_icon_url', plugins_url( 'assets/images/credits.png', dirname(__FILE__) ) );
        if ( $icon_url ) {
            $this->icon = $icon_url;
        }

        // Hooks
        add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
        add_action( 'woocommerce_credit_card_form_start', array( $this, 'before_cc_form' ) );
        add_action( 'woocommerce_credit_card_form_end', array( $this, 'after_cc_form' ) );
    }

    /**
     * Check if this gateway is enabled and all dependencies are fine.
     * Disable the plugin if dependencies fail.
     *
     * @access      public
     * @return      bool
     */
    public function is_available() {
        global $s4wc;

        if ( $this->enabled === 'no' ) {
            return false;
        }

        // Stripe won't work without keys
        if ( ! $s4wc->settings['publishable_key'] && ! $s4wc->settings['secret_key'] ) {
            return false;
        }

        // Disable plugin if we don't use ssl
        if ( ! is_ssl() && $this->settings['testmode'] === 'no' ) {
            return false;
        }

        // Allow smaller orders to process for WooCommerce Bookings
        if ( is_checkout_pay_page() ) {
            $order_key = urldecode( $_GET['key'] );
            $order_id  = absint( get_query_var( 'order-pay' ) );
            $order     = new WC_Order( $order_id );

            if ( $order->id == $order_id && $order->order_key == $order_key && $this->get_order_total() * 100 < 50) {
                return false;
            }
        }

        // Stripe will only process orders of at least 50 cents otherwise
        elseif ( $this->get_order_total() * 100 < 50 ) {
            return false;
        }

        return true;
    }

    /**
     * Send notices to users if requirements fail, or for any other reason
     *
     * @access      public
     * @return      bool
     */
    public function admin_notices() {
        global $s4wc, $pagenow, $wpdb;

        if ( $this->enabled == 'no') {
            return false;
        }

        // Check for API Keys
        if ( ! $s4wc->settings['publishable_key'] && ! $s4wc->settings['secret_key'] ) {
            echo '<div class="error"><p>' . __( 'Stripe needs API Keys to work, please find your secret and publishable keys in the <a href="https://manage.stripe.com/account/apikeys" target="_blank">Stripe accounts section</a>.', 'stripe-for-woocommerce' ) . '</p></div>';
            return false;
        }

        // Force SSL on production
        if ( $this->settings['testmode'] == 'no' && get_option( 'woocommerce_force_ssl_checkout' ) == 'no' ) {
            echo '<div class="error"><p>' . __( 'Stripe needs SSL in order to be secure. Read mode about forcing SSL on checkout in <a href="http://docs.woothemes.com/document/ssl-and-https/" target="_blank">the WooCommerce docs</a>.', 'stripe-for-woocommerce' ) . '</p></div>';
            return false;
        }

        // Add notices for admin page
        if ( $pagenow === 'admin.php' ) {
            $options_base = 'admin.php?page=wc-settings&tab=checkout&section=' . strtolower( get_class( $this ) );

            if ( ! empty( $_GET['action'] ) && ! empty( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 's4wc_action' ) ) {

                // Delete all test data
                if ( $_GET['action'] === 'delete_test_data' ) {

                    // Delete test data if the action has been confirmed
                    if ( ! empty( $_GET['confirm'] ) && $_GET['confirm'] === 'yes' ) {

                        $result = $wpdb->delete( $wpdb->usermeta, array( 'meta_key' => '_stripe_test_customer_info' ) );

                        if ( $result !== false ) :
                            ?>
                            <div class="updated">
                                <p><?php _e( 'Stripe Test Data successfully deleted.', 'stripe-for-woocommerce' ); ?></p>
                            </div>
                            <?php
                        else :
                            ?>
                            <div class="error">
                                <p><?php _e( 'Unable to delete Stripe Test Data', 'stripe-for-woocommerce' ); ?></p>
                            </div>
                            <?php
                        endif;
                    }

                    // Ask for confimation before we actually delete data
                    else {
                        ?>
                        <div class="error">
                            <p><?php _e( 'Are you sure you want to delete all test data? This action cannot be undone.', 'stripe-for-woocommerce' ); ?></p>
                            <p>
                                <a href="<?php echo wp_nonce_url( admin_url( $options_base . '&action=delete_test_data&confirm=yes' ), 's4wc_action' ); ?>" class="button"><?php _e( 'Delete', 'stripe-for-woocommerce' ); ?></a>
                                <a href="<?php echo admin_url( $options_base ); ?>" class="button"><?php _e( 'Cancel', 'stripe-for-woocommerce' ); ?></a>
                            </p>
                        </div>
                        <?php
                    }
                }
            }
        }
    }

    /**
     * Initialise Gateway Settings Form Fields
     *
     * @access      public
     * @return      void
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'type'          => 'checkbox',
                'title'         => __( 'Enable/Disable', 'stripe-for-woocommerce' ),
                'label'         => __( 'Enable Stripe for WooCommerce', 'stripe-for-woocommerce' ),
                'default'       => 'yes'
            ),
            'title' => array(
                'type'          => 'text',
                'title'         => __( 'Title', 'stripe-for-woocommerce' ),
                'description'   => __( 'This controls the title which the user sees during checkout.', 'stripe-for-woocommerce' ),
                'default'       => __( 'Credit Card Payment', 'stripe-for-woocommerce' )
            ),
            'description' => array(
                'type'          => 'textarea',
                'title'         => __( 'Description', 'stripe-for-woocommerce' ),
                'description'   => __( 'This controls the description which the user sees during checkout.', 'stripe-for-woocommerce' ),
                'default'       => '',
            ),
            'charge_type' => array(
                'type'          => 'select',
                'title'         => __( 'Charge Type', 'stripe-for-woocommerce' ),
                'description'   => __( 'Choose to capture payment at checkout, or authorize only to capture later.', 'stripe-for-woocommerce' ),
                'options'       => array(
                    'capture'   => __( 'Authorize & Capture', 'stripe-for-woocommerce' ),
                    'authorize' => __( 'Authorize Only', 'stripe-for-woocommerce' )
                ),
                'default'       => 'capture'
            ),
            'additional_fields' => array(
                'type'          => 'checkbox',
                'title'         => __( 'Additional Fields', 'stripe-for-woocommerce' ),
                'description'   => __( 'Add a Billing ZIP and a Name on Card for Stripe authentication purposes. This is only neccessary if you check the "Only ship to the users billing address" box on WooCommerce Shipping settings.', 'stripe-for-woocommerce' ),
                'label'         => __( 'Use Additional Fields', 'stripe-for-woocommerce' ),
                'default'       => 'no'
            ),
            'saved_cards' => array(
                'type'          => 'checkbox',
                'title'         => __( 'Saved Cards', 'stripe-for-woocommerce' ),
                'description'   => __( 'Allow customers to use saved cards for future purchases.', 'stripe-for-woocommerce' ),
                'default'       => 'yes',
            ),
            'testmode' => array(
                'type'          => 'checkbox',
                'title'         => __( 'Test Mode', 'stripe-for-woocommerce' ),
                'description'   => __( 'Use the test mode on Stripe\'s dashboard to verify everything works before going live.', 'stripe-for-woocommerce' ),
                'label'         => __( 'Turn on testing', 'stripe-for-woocommerce' ),
                'default'       => 'no'
            ),
            'test_secret_key'   => array(
                'type'          => 'text',
                'title'         => __( 'Stripe API Test Secret key', 'stripe-for-woocommerce' ),
                'default'       => '',
            ),
            'test_publishable_key' => array(
                'type'          => 'text',
                'title'         => __( 'Stripe API Test Publishable key', 'stripe-for-woocommerce' ),
                'default'       => '',
            ),
            'live_secret_key'   => array(
                'type'          => 'text',
                'title'         => __( 'Stripe API Live Secret key', 'stripe-for-woocommerce' ),
                'default'       => '',
            ),
            'live_publishable_key' => array(
                'type'          => 'text',
                'title'         => __( 'Stripe API Live Publishable key', 'stripe-for-woocommerce' ),
                'default'       => '',
            ),
        );
    }

    /**
     * Admin Panel Options
     * - Options for bits like 'title' and availability on a country-by-country basis
     *
     * @access      public
     * @return      void
     */
    public function admin_options() {

        $options_base = 'admin.php?page=wc-settings&tab=checkout&section=' . strtolower( get_class( $this ) );
        ?>
        <h3><?php echo ( ! empty( $this->method_title ) ) ? $this->method_title : __( 'Settings', 'woocommerce' ) ; ?></h3>
        <p><?php _e( 'Allows Credit Card payments through <a href="https://stripe.com/">Stripe</a>. You can find your API Keys in your <a href="https://dashboard.stripe.com/account/apikeys">Stripe Account Settings</a>.', 'stripe-for-woocommerce' ); ?></p>
        <table class="form-table">
            <?php $this->generate_settings_html(); ?>
            <tr>
                <th><?php _e( 'Delete Stripe Test Data', 'stripe-for-woocommerce' ); ?></th>
                <td>
                    <p>
                        <a href="<?php echo wp_nonce_url( admin_url( $options_base . '&action=delete_test_data' ), 's4wc_action' ); ?>" class="button"><?php _e( 'Delete all Test Data', 'stripe-for-woocommerce' ); ?></a>
                        <span class="description"><?php _e( '<strong class="red">Warning:</strong> This will delete all Stripe test customer data, make sure to back up your database.', 'stripe-for-woocommerce' ); ?></span>
                    </p>
                </td>
            </tr>
        </table>

        <?php
    }

    /**
     * Load dependent scripts
     * - stripe.js from the stripe servers
     * - s4wc.js for handling the data to submit to stripe
     *
     * @access      public
     * @return      void
     */
    public function load_scripts() {
        global $s4wc;

        // Main stripe js
        wp_enqueue_script( 'stripe', 'https://js.stripe.com/v2/', false, '2.0', true );

        // Plugin js
        wp_enqueue_script( 's4wc_js', plugins_url( 'assets/js/s4wc.min.js', dirname( __FILE__ ) ), array( 'stripe', 'wc-credit-card-form' ), '1.36', true );

        // Add data that s4wc.js needs
        $s4wc_info = array(
            'publishableKey'    => $s4wc->settings['publishable_key'],
            'savedCardsEnabled' => $s4wc->settings['saved_cards'] === 'yes' ? true : false,
            'hasCard'           => ( $this->stripe_customer_info && count( $this->stripe_customer_info['cards'] ) ) ? true : false
        );

        // If we're on the pay page, Stripe needs the address
        if ( is_checkout_pay_page() ) {
            $order_key = urldecode( $_GET['key'] );
            $order_id  = absint( get_query_var( 'order-pay' ) );
            $order     = new WC_Order( $order_id );

            if ( $order->id == $order_id && $order->order_key == $order_key ) {
                $s4wc_info['billing_name']      = $order->billing_first_name . ' ' . $order->billing_last_name;
                $s4wc_info['billing_address_1'] = $order->billing_address_1;
                $s4wc_info['billing_address_2'] = $order->billing_address_2;
                $s4wc_info['billing_city']      = $order->billing_city;
                $s4wc_info['billing_state']     = $order->billing_state;
                $s4wc_info['billing_postcode']  = $order->billing_postcode;
                $s4wc_info['billing_country']   = $order->billing_country;
            }
        }

        wp_localize_script( 's4wc_js', 's4wc_info', $s4wc_info );
    }

    /**
     * Add additional fields just above the credit card form
     *
     * @access      public
     * @param       string $gateway_id
     * @return      void
     */
    public function before_cc_form( $gateway_id ) {
        global $s4wc;

        // Ensure that we're only outputting this for the s4wc gateway
        if ( $gateway_id !== $this->id ) {
            return;
        }

        // These form fields are optional, so we should respect that
        if ( $s4wc->settings['additional_fields'] !== 'yes' ) {
            return;
        }

        woocommerce_form_field( 'billing-name', array(
            'label'             => __( 'Name on Card', 'stripe-for-woocommerce' ),
            'required'          => true,
            'class'             => array( 'form-row-first' ),
            'input_class'       => array( 's4wc-billing-name' ),
            'custom_attributes' => array(
                'autocomplete'  => 'off',
            ),
        ) );

        woocommerce_form_field( 'billing-zip', array(
            'label'             => __( 'Billing Zip', 'stripe-for-woocommerce' ),
            'required'          => true,
            'class'             => array( 'form-row-last' ),
            'input_class'       => array( 's4wc-billing-zip' ),
            'clear'             => true,
            'custom_attributes' => array(
                'autocomplete'  => 'off',
            ),
        ) );
    }

    /**
     * Add an option to save card details after the form
     *
     * @access      public
     * @param       string $gateway_id
     * @return      void
     */
    public function after_cc_form( $gateway_id ) {
        global $s4wc;

        // Ensure that we're only outputting this for the s4wc gateway
        if ( $gateway_id !== $this->id ) {
            return;
        }

        // This form field is optional, so we should respect that
        if ( $s4wc->settings['saved_cards'] !== 'yes' ) {
            return;
        }

        woocommerce_form_field( 's4wc_save_card', array(
            'type'              => 'checkbox',
            'label'             => __( 'Save Card Details For Later', 'stripe-for-woocommerce' ),
            'class'             => array( 'form-row-wide' ),
            'input_class'       => array( 's4wc-save-card' ),
            'custom_attributes' => array(
                'autocomplete'  => 'off'
            ),
        ) );
    }

    /**
     * Output payment fields, optional additional fields and woocommerce cc form
     *
     * @access      public
     * @return      void
     */
    public function payment_fields() {

        // Output the saved card data
        s4wc_get_template( 'payment-fields.php' );

        // Output WooCommerce 2.1+ cc form
        $this->credit_card_form( array(
            'fields_have_names' => false,
        ) );
    }

    /**
     * Validate credit card form fields
     *
     * @access      public
     * @return      void
     */
    public function validate_fields() {

        $form_fields = array(
            'card-number' => array(
                'name'       => __( 'Credit Card Number', 'stripe-for-woocommerce' ),
                'error_type' => isset( $_POST['s4wc-card-number'] ) ? $_POST['s4wc-card-number'] : null,
            ),
            'card-expiry' => array(
                'name'       => __( 'Credit Card Expiration', 'stripe-for-woocommerce' ),
                'error_type' => isset( $_POST['s4wc-card-expiry'] ) ? $_POST['s4wc-card-expiry'] : null,
            ),
            'card-cvc'    => array(
                'name'       => __( 'Credit Card CVC', 'stripe-for-woocommerce' ),
                'error_type' => isset( $_POST['s4wc-card-cvc'] ) ? $_POST['s4wc-card-cvc'] : null,
            ),
        );

        foreach ( $form_fields as $form_field ) {

            if ( ! empty( $form_field['error_type'] ) ) {
                wc_add_notice( $this->get_form_error_message( $form_field['name'], $form_field['error_type'] ), 'error' );
            }
        }
    }

    /**
     * Get error message for form validator given field name and type of error
     *
     * @access      protected
     * @param       string $field_name
     * @param       string $error_type
     * @return      string
     */
    protected function get_form_error_message( $field_name, $error_type = 'undefined' ) {

        if ( $error_type === 'invalid' ) {
            return sprintf( __( 'Please enter a valid %s.', 'stripe-for-woocommerce' ), "<strong>$field_name</strong>" );
        } else {
            return sprintf( __( '%s is a required field.', 'stripe-for-woocommerce' ), "<strong>$field_name</strong>" );
        }
    }

    /**
     * Process the payment and return the result
     *
     * @access      public
     * @param       int $order_id
     * @return      array
     */
    public function process_payment( $order_id ) {

        if ( $this->send_to_stripe( $order_id ) ) {
            $this->order_complete();

            $result = array(
                'result' => 'success',
                'redirect' => $this->get_return_url( $this->order )
            );

            return $result;
        } else {
            $this->payment_failed();

            // Add a generic error message if we don't currently have any others
            if ( wc_notice_count( 'error' ) == 0 ) {
                wc_add_notice( __( 'Transaction Error: Could not complete your payment.', 'stripe-for-woocommerce' ), 'error' );
            }
        }
    }

    /**
     * Process refund
     *
     * Overriding refund method
     *
     * @access      public
     * @param       int $order_id
     * @param       float $amount
     * @param       string $reason
     * @return      mixed True or False based on success, or WP_Error
     */
    public function process_refund( $order_id, $amount = null, $reason = '' ) {

        $this->order = new WC_Order( $order_id );
        $this->transaction_id = $this->order->get_transaction_id();

        if ( ! $this->transaction_id ) {
            return new WP_Error( 's4wc_refund_error',
                sprintf(
                    __( '%s Credit Card Refund failed because the Transaction ID is missing.', 'stripe-for-woocommerce' ),
                    get_class( $this )
                )
            );
        }

        try {

            $refund_data = array();

            // If the amount is set, refund that amount, otherwise the entire amount is refunded
            if ( $amount ) {
                $refund_data['amount'] = $amount * 100;
            }

            // If a reason is provided, add it to the Stripe metadata for the refund
            if ( $reason ) {
                $refund_data['metadata']['reason'] = $reason;
            }

            // Send the refund to the Stripe API
            return S4WC_API::create_refund( $this->transaction_id, $refund_data );

        } catch ( Exception $e ) {
            $this->transaction_error_message = $s4wc->get_error_message( $e );

            $this->order->add_order_note(
                sprintf(
                    __( '%s Credit Card Refund Failed with message: "%s"', 'stripe-for-woocommerce' ),
                    get_class( $this ),
                    $this->transaction_error_message
                )
            );

            // Something failed somewhere, send a message.
            return new WP_Error( 's4wc_refund_error', $this->transaction_error_message );
        }

        return false;
    }

    /**
     * Send form data to Stripe
     * Handles sending the charge to an existing customer, a new customer (that's logged in), or a guest
     *
     * @access      protected
     * @param       int $order_id
     * @return      bool
     */
    protected function send_to_stripe( $order_id ) {
        global $s4wc;

        // Get the order based on order_id
        $this->order = new WC_Order( $order_id );

        // Get the credit card details submitted by the form
        $this->form_data = $this->get_form_data();

        // If there are errors on the form, don't bother sending to Stripe.
        if ( $this->form_data['errors'] == 1 ) {
            return;
        }

        // Set up the charge for Stripe's servers
        try {

            // Allow for any type of charge to use the same try/catch config
            $this->charge_set_up();

            // Save data for the "Capture"
            update_post_meta( $this->order->id, '_s4wc_capture', strcmp( $this->settings['charge_type'], 'authorize' ) == 0 );

            // Save Stripe fee
            if ( isset( $this->charge->balance_transaction ) && isset( $this->charge->balance_transaction->fee ) ) {
                $stripe_fee = number_format( $this->charge->balance_transaction->fee / 100, 2, '.', '' );
                update_post_meta( $this->order->id, 'Stripe Fee', $stripe_fee );
            }

            return true;

        } catch ( Exception $e ) {

            // Stop page reload if we have errors to show
            unset( WC()->session->reload_checkout );

            $this->transaction_error_message = $s4wc->get_error_message( $e );

            wc_add_notice( __( 'Error:', 'stripe-for-woocommerce' ) . ' ' . $this->transaction_error_message, 'error' );

            return false;
        }
    }

    /**
     * Create a customer if the current user isn't already one
     * Retrieve a customer if one already exists
     * Add a card to a customer if necessary
     *
     * @access      protected
     * @return      array
     */
    protected function get_customer() {
        global $s4wc;

        $output = array();
        $customer_info = get_user_meta( $this->order->user_id, $s4wc->settings['stripe_db_location'], true );

        if ( $customer_info ) {

            // If the user is already registered on the stripe servers, retreive their information
            $customer = S4WC_API::get_customer( $customer_info['customer_id'] );

            // If the user doesn't have cards or is adding a new one
            if ( $this->form_data['chosen_card'] === 'new' ) {

                // Add new card on stripe servers and make default
                $card = S4WC_API::update_customer( $customer_info['customer_id'] . '/cards', array(
                    'card' => $this->form_data['token']
                ) );

                // Add new customer details to database
                S4WC_DB::update_customer( $this->order->user_id, array(
                    'customer_id'  => $customer->id,
                    'card'         => array(
                        'id'        => $card->id,
                        'brand'     => $card->brand,
                        'last4'     => $card->last4,
                        'exp_month' => $card->exp_month,
                        'exp_year'  => $card->exp_year,
                    ),
                    'default_card' => $card->id
                ) );

                $output['card'] = $card->id;
            } else {
                $output['card'] = $customer_info['cards'][ intval( $this->form_data['chosen_card'] ) ]['id'];
            }

        } else {

            $user = get_userdata( $this->order->user_id );

            // Allow options to be set without modifying sensitive data like token, email, etc
            $customer_data = apply_filters( 's4wc_customer_data', array(), $this->form_data, $this->order );

            // Set default customer description
            $customer_description = $user->user_login . ' (#' . $this->order->user_id . ' - ' . $user->user_email . ') ' . $this->form_data['customer']['name']; // username (user_id - user_email) Full Name

            // Set up basics for customer
            $customer_data['description'] = apply_filters( 's4wc_customer_description', $customer_description, $this->form_data, $this->order );
            $customer_data['email']       = $this->form_data['customer']['billing_email'];
            $customer_data['card']        = $this->form_data['token'];

            // Create the customer in the api with the above data
            $customer = S4WC_API::create_customer( $this->order->user_id, $customer_data );

            $output['card'] = $customer->default_source;
        }

        // Set up charging data to include customer information
        $output['customer_id'] = $customer->id;

        // Save data for cross-reference between Stripe Dashboard and WooCommerce
        update_post_meta( $this->order->id, 'Stripe Customer Id', $customer->id );

        return $output;
    }

    /**
     * Get the charge's description
     *
     * @access      protected
     * @param       string $type Type of product being bought
     * @return      string
     */
    protected function get_charge_description( $type = 'simple' ) {
        $order_items = $this->order->get_items();

        // Set a default name, override with a product name if it exists for Stripe's dashboard
        if ( $type === 'subscription' ) {
            $product_name = __( 'Subscription', 'stripe-for-woocommerce' );
        } else {
            $product_name = __( 'Purchases', 'stripe-for-woocommerce' );
        }

        // Grab first viable product name and use it
        foreach ( $order_items as $key => $item ) {

            if ( $type === 'subscription' && isset( $item['subscription_status'] ) ) {
                $product_name = $item['name'];
                break;
            } elseif ( $type === 'simple' ) {
                $product_name = $item['name'];
                break;
            }
        }

        // Charge description
        $charge_description = sprintf(
            __( 'Payment for %s (Order: %s)', 'stripe-for-woocommerce' ),
            $product_name,
            $this->order->get_order_number()
        );

        if ( $type === 'subscription' ) {
            return apply_filters( 's4wc_subscription_charge_description', $charge_description, $this->order );
        } else {
            return apply_filters( 's4wc_charge_description', $charge_description, $this->form_data, $this->order );
        }
    }

    /**
     * Mark the payment as failed in the order notes
     *
     * @access      protected
     * @return      void
     */
    protected function payment_failed() {
        $this->order->add_order_note(
            sprintf(
                __( '%s payment failed with message: "%s"', 'stripe-for-woocommerce' ),
                get_class( $this ),
                $this->transaction_error_message
            )
        );
    }

    /**
     * Mark the payment as completed in the order notes
     *
     * @access      protected
     * @return      void
     */
    protected function order_complete() {

        if ( $this->order->status == 'completed' ) {
            return;
        }

        $this->order->payment_complete( $this->transaction_id );

        $this->order->add_order_note(
            sprintf(
                __( '%s payment completed with Transaction Id of "%s"', 'stripe-for-woocommerce' ),
                get_class( $this ),
                $this->transaction_id
            )
        );
    }

    /**
     * Retrieve the form fields
     *
     * @access      protected
     * @return      mixed
     */
    protected function get_form_data() {

        if ( $this->order && $this->order != null ) {
            return array(
                'amount'      => $this->get_order_total() * 100,
                'currency'    => strtolower( $this->order->order_currency ),
                'token'       => isset( $_POST['stripe_token'] ) ? $_POST['stripe_token'] : '',
                'chosen_card' => isset( $_POST['s4wc_card'] ) ? $_POST['s4wc_card'] : 'new',
                'save_card'   => isset( $_POST['s4wc_save_card'] ) && $_POST['s4wc_save_card'] === 'true',
                'customer'    => array(
                    'name'          => $this->order->billing_first_name . ' ' . $this->order->billing_last_name,
                    'billing_email' => $this->order->billing_email,
                ),
                'errors'      => isset( $_POST['form_errors'] ) ? $_POST['form_errors'] : '',
            );
        }

        return false;
    }

    /**
     * Set up the charge that will be sent to Stripe
     *
     * @access      private
     * @return      void
     */
    private function charge_set_up() {
        global $s4wc;

        $customer_info = get_user_meta( $this->order->user_id, $s4wc->settings['stripe_db_location'], true );

        // Allow options to be set without modifying sensitive data like amount, currency, etc.
        $stripe_charge_data = apply_filters( 's4wc_charge_data', array(), $this->form_data, $this->order );

        // Set up basics for charging
        $stripe_charge_data['amount']   = $this->form_data['amount']; // amount in cents
        $stripe_charge_data['currency'] = $this->form_data['currency'];
        $stripe_charge_data['capture']  = ( $this->settings['charge_type'] == 'capture' ) ? 'true' : 'false';
        $stripe_charge_data['expand[]'] = 'balance_transaction';

        // Make sure we only create customers if a user is logged in and wants to save their card
        if (
            is_user_logged_in() &&
            $this->settings['saved_cards'] === 'yes' &&
            ( $this->form_data['save_card'] || $this->form_data['chosen_card'] !== 'new' )
        ) {
            // Add a customer or retrieve an existing one
            $customer = $this->get_customer();

            $stripe_charge_data['card'] = $customer['card'];
            $stripe_charge_data['customer'] = $customer['customer_id'];

            // Update default card
            if ( count( $customer_info['cards'] ) && $this->form_data['chosen_card'] !== 'new' ) {
                $default_card = $customer_info['cards'][ intval( $this->form_data['chosen_card'] ) ]['id'];
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

        $this->charge = $charge;
        $this->transaction_id = $charge->id;
    }
}

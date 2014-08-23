<?php
/**
 * The Template for displaying the credit card form on the checkout page
 *
 * Override this template by copying it to yourtheme/woocommerce/woocommerce-stripe/payment-fields.php
 *
 * @author		Stephen Zuniga
 * @version		1.22
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

global $s4wc;

// Add notification to the user that this will fail miserably if they attempt it.
echo '<noscript>';
	printf( __( '%s payment does not work without Javascript. Please enable Javascript or use a different payment method.', 'stripe-for-woocommerce' ), $s4wc->settings['title'] );
echo '</noscript>';

if ( $s4wc->settings['description'] ) : ?>
	<p class="s4wc-description"><?php echo $s4wc->settings['description']; ?></p>
<?php endif;

$stripe_customer_info = get_user_meta( get_current_user_id(), $s4wc->settings['stripe_db_location'], true );

if( is_user_logged_in() && $stripe_customer_info && isset( $stripe_customer_info['cards'] ) && count( $stripe_customer_info['cards'] ) ) :

	// Add option to use a saved card
	foreach ( $stripe_customer_info['cards'] as $i => $credit_card ) : ?>

		<input type="radio" id="stripe_card_<?php echo $i; ?>" name="s4wc_card" value="<?php echo $i; ?>"<?php echo ( $stripe_customer_info['default_card'] == $credit_card['id'] ) ? ' checked' : ''; ?>>
		<label for="stripe_card_<?php echo $i; ?>"><?php printf( __( 'Card ending with %s (%s/%s)', 'stripe-for-woocommerce' ), $credit_card['last4'], $credit_card['exp_month'], $credit_card['exp_year'] ); ?></label><br>

	<?php endforeach; ?>

	<input type="radio" id="new_card" name="s4wc_card" value="new">
	<label for="new_card"><?php _e( 'Use a new credit card', 'stripe-for-woocommerce' ); ?></label>

<?php endif; ?>

<div id="s4wc-creditcard-form">

<?php
	if ( $s4wc->settings['additional_fields'] == 'yes' ) : 

		$billing_name = woocommerce_form_field( 'billing-name', array(
			'label'				=> __( 'Name on Card', 'stripe-for-woocommerce' ),
			'required'			=> true,
			'class'				=> array( 'form-row-first' ),
			'input_class'		=> array( 's4wc-billing-name' ),
			'return'			=> true,
			'custom_attributes'	=> array(
				'autocomplete'	=> 'off'
			)
		) );
		echo $billing_name;

		$billing_zip = woocommerce_form_field( 'billing-zip', array(
			'label'				=> __( 'Billing Zip', 'stripe-for-woocommerce' ),
			'required'			=> true,
			'class'				=> array( 'form-row-last' ),
			'input_class'		=> array( 's4wc-billing-zip' ),
			'return'			=> true,
			'clear'				=> true,
			'custom_attributes'	=> array(
				'autocomplete'	=> 'off'
			)
		) );
		echo $billing_zip;

	endif;

	$cc_number = woocommerce_form_field( 'card-number', array(
		'label'				=> __( 'Card Number', 'stripe-for-woocommerce' ),
		'placeholder'		=> '•••• •••• •••• ••••',
		'maxlength'			=> 20,
		'required'			=> true,
		'input_class'		=> array( 's4wc-card-number' ),
		'return'			=> true,
		'custom_attributes'	=> array(
			'autocomplete'	=> 'off',
			'pattern'		=> '\d*'
		)
	) );
	$cc_number = preg_replace( '/name=".*?\"/i', '', $cc_number );
	echo $cc_number;

	$cc_expiry = woocommerce_form_field( 'card-expiry', array(
		'label'				=> __( 'Expiry (MM/YY)', 'stripe-for-woocommerce' ),
		'placeholder'		=> __( 'MM / YY', 'stripe-for-woocommerce' ),
		'required'			=> true,
		'class'				=> array( 'form-row-first' ),
		'input_class'		=> array( 's4wc-card-expiry' ),
		'return'			=> true,
		'custom_attributes'	=> array(
			'autocomplete'	=> 'off',
			'pattern'		=> '\d*'
		)
	) );
	$cc_expiry = preg_replace( '/name=".*?\"/i', '', $cc_expiry );
	echo $cc_expiry;

	$cc_cvc = woocommerce_form_field( 'card-cvc', array(
		'label'				=> __( 'Card Code', 'stripe-for-woocommerce' ),
		'placeholder'		=> __( 'CVC', 'stripe-for-woocommerce' ),
		'required'			=> true,
		'class'				=> array( 'form-row-last' ),
		'input_class'		=> array( 's4wc-card-cvc' ),
		'return'			=> true,
		'clear'				=> true,
		'custom_attributes'	=> array(
			'autocomplete'	=> 'off',
			'pattern'		=> '\d*'
		)
	) );
	$cc_cvc = preg_replace( '/name=".*?\"/i', '', $cc_cvc );
	echo $cc_cvc;

?>
</div>

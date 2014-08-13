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

if ( $s4wc->settings['description'] ) : ?>
	<p class="s4wc-description"><?php echo $s4wc->settings['description']; ?></p>
<?php endif;

$stripe_customer_info = get_user_meta( get_current_user_id(), $s4wc->settings['stripe_db_location'], true );

if( is_user_logged_in() && $stripe_customer_info && isset( $stripe_customer_info['cards'] ) && count( $stripe_customer_info['cards'] ) ) :

	// Add option to use a saved card
	foreach ( $stripe_customer_info['cards'] as $i => $credit_card ) : ?>

		<input type="radio" id="stripe_card_<?php echo $i; ?>" name="s4wc_card" value="<?php echo $i; ?>"<?php echo ( $stripe_customer_info['default_card'] == $credit_card['id'] ) ? ' checked' : ''; ?>>
		<label for="stripe_card_<?php echo $i; ?>">Card ending with <?php echo $credit_card['last4']; ?> (<?php echo $credit_card['exp_month']; ?>/<?php echo $credit_card['exp_year']; ?>)</label><br>

	<?php endforeach; ?>

	<input type="radio" id="new_card" name="s4wc_card" value="new">
	<label for="new_card">Use a new credit card</label>

<?php endif; ?>

<div id="s4wc-creditcard-form">

<?php
	if ( $s4wc->settings['additional_fields'] == 'yes' ) : 

		$billing_name = woocommerce_form_field( 'billing-name', array(
			'label'				=> 'Name on Card',
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
			'label'				=> 'Billing Zip',
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
		'label'				=> 'Card Number',
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
		'label'				=> 'Expiry (MM/YY)',
		'placeholder'		=> 'MM / YY',
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
		'label'			=> 'Card Code',
		'placeholder'		=> 'CVC',
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

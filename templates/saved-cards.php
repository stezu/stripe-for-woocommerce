<?php
/**
 * The Template for displaying the saved credit cards on the account page
 *
 * Override this template by copying it to yourtheme/woocommerce/woocommerce-stripe/saved-cards.php
 *
 * @author		Stephen Zuniga
 * @version		1.25
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

global $s4wc;

// If the current user is not a stripe customer, return
if ( ! get_user_meta( get_current_user_id(), $s4wc->settings['stripe_db_location'], true ) )
	return;

// If user requested to delete a card, delete it
if ( isset( $_POST['delete_card'] ) && wp_verify_nonce( $_POST['_wpnonce'], "stripe_del_card" ) ) {
	S4WC_API::delete_card( get_current_user_id(), $_POST['delete_card'] );
}

// Get user database object
$user_meta = get_user_meta( get_current_user_id(), $s4wc->settings['stripe_db_location'], true );

// Get user credit cards
$credit_cards = isset( $user_meta['cards'] ) ? $user_meta['cards'] : false;

if ( $credit_cards ) :
?>
	<h2 id="saved-cards"><?php _e( 'Saved cards', 'stripe-for-woocommerce' ); ?></h2>
	<table class="shop_table">
		<thead>
			<tr>
				<th><?php _e( 'Card ending in...', 'stripe-for-woocommerce' ); ?></th>
				<th><?php _e( 'Expires', 'stripe-for-woocommerce' ); ?></th>
				<th></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $credit_cards as $i => $credit_card ) : ?>
			<tr>
				<td><?php echo esc_html( $credit_card['last4'] ); ?></td>
				<td><?php echo esc_html( $credit_card['exp_month'] ) . '/' . esc_html( $credit_card['exp_year'] ); ?></td>
				<td>
					<form action="#saved-cards" method="POST">
						<?php wp_nonce_field ( 'stripe_del_card' ); ?>
						<input type="hidden" name="delete_card" value="<?php echo esc_attr( $i ); ?>">
						<input type="submit" value="<?php _e( 'Delete card', 'stripe-for-woocommerce' ); ?>">
					</form>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php
endif;

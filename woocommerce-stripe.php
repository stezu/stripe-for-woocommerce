<?php
/*
Plugin Name: Stripe for WooCommerce
Plugin URI: https://github.com/stezu/woocommerce-stripe/
Description: Use Stripe for collecting credit card payments on WooCommerce.
Version: 0.1.0
Author: Stephen Zuniga
Author URI: https://github.com/stezu

License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Foundation built by: Sean Voss // https://github.com/seanvoss/striper
*/

add_action( 'plugins_loaded', 'wc_stripe_init', 0 );

function wc_stripe_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	// Require Stripe Library
	// if ( ! class_exists( 'Stripe' ) ) {
	// 	require_once( 'lib/stripe-php/lib/Stripe.php' );
	// }

	include_once('classes/class-woocommerce_stripe.php');

	function wc_stripe_account_saved_cards() {
		$credit_cards = get_user_meta( get_current_user_id(), '_stripe_customer_info' );

		if ( ! $credit_cards )
			return;

        if ( isset( $_POST['delete_card'] ) && wp_verify_nonce( $_POST['_wpnonce'], "stripe_del_card" ) ) {
			$credit_card = $credit_cards[ (int) $_POST['delete_card'] ];

			delete_user_meta( get_current_user_id(), '_stripe_customer_info', $credit_card );
		}

		$credit_cards = get_user_meta( get_current_user_id(), '_stripe_customer_info' );

		if ( ! $credit_cards )
			return;
		?>
			<h2 id="saved-cards">Saved cards</h2>
			<table class="shop_table">
				<thead>
					<tr>
						<th>Card ending in...</th>
						<th>Expires</th>
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
                                <input type="hidden" name="delete_card" value="<?php esc_attr($i); ?>">
                                <input type="submit" value="Delete card">
                            </form>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php
	}
	add_action( 'woocommerce_after_my_account', 'wc_stripe_account_saved_cards' );

	// Add the gateway
	function woocommerce_stripe_gateway($methods) {
		if ( class_exists( 'Woocommerce_Stripe' ) ) {
			$methods[] = 'Woocommerce_Stripe';
		}

		return $methods;
	}
	add_filter( 'woocommerce_payment_gateways', 'woocommerce_stripe_gateway' );
}

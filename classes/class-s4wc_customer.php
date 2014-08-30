<?php
/**
 * Customer related modifications and templates
 *
 * @class 		S4WC_Customer
 * @version		1.24
 * @author 		Stephen Zuniga
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class S4WC_Customer {

	public function __construct() {
		
		// Hooks
		add_action( 'woocommerce_after_my_account', array( $this, 'account_saved_cards' ) );
		add_action( 'show_user_profile', array( $this, 'add_customer_profile' ), 20 );
		add_action( 'edit_user_profile', array( $this, 'add_customer_profile' ), 20 );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
	}

	/**
	 * Complete necessary actions and display
	 * notifications at the top of the page
	 *
	 * @access public
	 * @return void
	 */
	public function admin_notices() {
		global $pagenow;

		// If we're on the profile page
		if ( $pagenow === 'profile.php' ) {
			var_dump( 'helloworld' );
		}
	}


	/**
	 * Add to the customer profile
	 *
	 * @access public
	 * @param WP_User $user
	 * @return void
	 */
	public function add_customer_profile( $user ) {

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		?>
		<table class="form-table">
			<tr>
				<th>Delete Stripe Test Data</th>
				<td>
					<p>
						<a href="<?php echo wp_nonce_url( admin_url( 'profile.php?action=delete_test_data' ), 's4wc_action' ); ?>" class="button"><?php _e( 'Delete Test Data', 'stripe-for-woocommerce' ); ?></a>
						<span class="description"><?php _e( '<strong class="red">Warning:</strong> This will delete Stripe test data for this customer, make sure to back up your database.', 'stripe-for-woocommerce' ); ?></span>
					</p>
				</td>
			</tr>
			<tr>
				<th>Delete Stripe Live Data</th>
				<td>
					<p>
						<a href="<?php echo wp_nonce_url( admin_url( 'profile.php?action=delete_live_data' ), 's4wc_action' ); ?>" class="button"><?php _e( 'Delete Live Data', 'stripe-for-woocommerce' ); ?></a>
						<span class="description"><?php _e( '<strong class="red">Warning:</strong> This will delete Stripe live data for this customer, make sure to back up your database.', 'stripe-for-woocommerce' ); ?></span>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Gives front-end view of saved cards in the account page
	 *
	 * @access public
	 * @return void
	 */
	public function account_saved_cards() {
		global $s4wc;

		if ( $s4wc->settings['saved_cards'] === 'yes' ) {
			s4wc_get_template( 'saved-cards.php' );
		}
	}
}

new S4WC_Customer();

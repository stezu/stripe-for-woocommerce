<?php
/**
 * The Template for displaying the saved credit cards on the account page
 *
 * Override this template by copying it to yourtheme/woocommerce/s4wc/saved-cards.php
 *
 * @author      Stephen Zuniga
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// If the customer has credit cards, output them
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
                        <?php wp_nonce_field( 's4wc_delete_card' ); ?>
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

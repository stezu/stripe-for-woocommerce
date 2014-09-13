<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Move to new plugin file
 */

$active_plugins = get_option( 'active_plugins', array() );
foreach ( $active_plugins as $key => $active_plugin ) {
    if ( strstr( $active_plugin, '/woocommerce-stripe.php' ) ) {
        $active_plugins[ $key ] = str_replace( '/woocommerce-stripe.php', '/stripe-for-woocommerce.php', $active_plugin );
    }
}
update_option( 'active_plugins', $active_plugins );
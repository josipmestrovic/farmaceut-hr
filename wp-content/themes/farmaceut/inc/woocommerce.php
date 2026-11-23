<?php
/**
 * WooCommerce prezentacijski hookovi teme.
 *
 * @package Farmaceut
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Podrška teme za WooCommerce.
 */
function farmaceut_woocommerce_podrska() {
	add_theme_support( 'woocommerce' );
}
add_action( 'after_setup_theme', 'farmaceut_woocommerce_podrska' );

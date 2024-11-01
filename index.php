<?php
/**
 * Plugin Name: SprayPay - Gespreid betalen
 * Plugin URI: https://www.spraypay.nl/
 * Description: Verhoog de omzet en de gemiddelde orderwaarde met de SprayPay maandbedrag marketingtool op de productpagina.
 * Version: 1.0.8
 * Author: Tallest
 * Author URI: https://www.tallest.nl/
 * Domain Path: /languages/
 * Text Domain: spraypay
 * Requires PHP: 7.2
 * WC requires at least: 5.9
 * WC tested up to: 8.5
 * License: GPL v2
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

if ( ! function_exists( 'SprayPay' ) ) {

	// Include the main class.
	if ( ! class_exists( 'Spray_Pay' ) ) {
		include_once dirname( __FILE__ ) . '/class-spray-pay.php';
	}
	/**
	 * Main instance of Spray_Pay.
	 *
	 * Returns the main instance of Spray_Pay to prevent the need to use globals.
	 *
	 * @return Spray_Pay
	 * @since  1.0.0
	 */
	function SprayPay() {
		$inst = Spray_Pay::instance();
		register_activation_hook( __FILE__, array( $inst, 'activation' ) );
		register_deactivation_hook( __FILE__, array( $inst, 'deactivation' ) );

		return $inst;
	}

	SprayPay();
}

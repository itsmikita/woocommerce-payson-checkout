<?php

/**
 * Plugin Name: WooCommerce Payson Checkout
 * Plugin URI: https://itsmikita.xyz
 * Description: A different implementation of Payson Checkout for WooCommerce.
 * Text Domain: woocommerce-payson-checkout
 * Domain Path: /languages/
 * Author: Mikita Stankiewicz
 * Author URI: https://itsmikita.xyz
 * Version: 2.0.0
 */

class WooCommerce_Payson_Checkout
{
  /**
   * Constructor
   */
  public function __construct()
  {
    add_action( "plugins_loaded", [ $this, 'load_plugin' ] );
    add_filter( "woocommerce_payment_gateways", [ $this, 'add_payment_gateway' ] );
  }

  /**
   * Load Payment Gateway Class
   *
   * @return void
   */
  public function load_plugin()
  {
    load_plugin_textdomain( 'woocommerce-payson-checkout', false, dirname( plugin_basename( __FILE__ ) ) . "/languages/" );

    require_once "includes/class-wc-payment-gateway-payson-checkout.php";
  }

  /**
   * Add Payment Gateway Methods
   *
   * @param array $methods Array of payment methods
   * @return array Methods
   */
  public function add_payment_gateway( $methods )
  {
    $methods[] = "WC_Payment_Gateway_Payson_Checkout";

    return $methods;
  }  
}

new WooCommerce_Payson_Checkout();

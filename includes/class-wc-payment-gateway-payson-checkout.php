<?php

/**
 * Payson Checkout Payment Gateway
 */
class WC_Payment_Gateway_Payson_Checkout extends WC_Payment_Gateway
{
  private $merchant_id = "";
  private $api_key = "";
  private $test_mode = false;

  /**
   * Constructor
   */
  public function __construct()
  {
    $this->id = "payson_checkout";
    $this->icon = "/wp-content/plugins/woocommerce-payson-checkout/assets/images/payson-checkout.png";
    $this->has_fields = true;
    $this->method_title = "Payson Checkout";
    $this->method_description = __( "Pay by Card, Bank Transfer, Invoice or Pay in Installments.", 'woocommerce-payson-checkout' );
    
    $this->init_form_fields();
    $this->init_settings();

    $this->enabled = $this->get_option( 'enabled' );
    $this->title = $this->get_option( 'title' );
    $this->description = $this->get_option( 'description' );
    $this->merchant_id = $this->get_option( 'merchant_id' );
    $this->api_key = $this->get_option( 'api_key' );
    $this->test_mode = $this->get_option( 'test_mode' );
    $this->checkout_locale = $this->get_option( 'checkout_locale' );
    $this->checkout_color_scheme = $this->get_option( 'checkout_color_scheme' );
    $this->checkout_customer_phone = $this->get_option( 'checkout_customer_phone' );
    $this->checkout_verification = $this->get_option( 'checkout_verification' );

    add_action( "woocommerce_update_options_payment_gateways_{$this->id}", [ $this, "process_admin_options" ] );
    add_action( "wp_ajax_nopriv_payson_checkout", [ $this, "payment_fields" ] );
    add_action( "wp_ajax_payson_checkout", [ $this, "payment_fields" ] );
    add_action( "wp_footer", [ $this, "add_assets" ] );
  }

  /**
   * Setup Gateway's Admin Options
   *
   * @return void
   */
  public function init_form_fields()
  {
    $this->form_fields = [
      'enabled' => [
        'title' => __( "Enable/Disable", 'woocommerce' ),
        'type' => "checkbox",
        'label' => __( "Enable Payson Checkout", 'woocommerce-payson-checkout' ),
        'default' => "yes"
      ],
      'title' => [
        'title' => __( "Title", 'woocommerce' ),
        'type' => "text",
        'description' => __( "This controls the title which the user sees during checkout.", 'woocommerce' ),
        'default' => $this->method_title,
        'desc_tip' => true
      ],
      'description' => [
        'title' => __( "Customer Message", 'woocommerce-payson-checkout' ),
        'type' => "textarea",
        'default' => $this->method_description
      ],
      'merchant_id' => [
        'title' => __( "Payson Agent ID", 'woocommerce-payson-checkout' ),
        'type' => "text",
        'default' => ""
      ],
      'api_key' => [
        'title' => __( "Payson API Key", 'woocommerce-payson-checkout' ),
        'type' => "text",
        'default' => ""
      ],
      'test_mode' => [
        'title' => __( "Test Mode", 'woocommerce-payson-checkout' ),
        'type' => "checkbox",
        'label' => __( "Enable Payson Checkout in Test Mode.", 'woocommerce-payson-checkout' ),
        'default' => "no"
      ],
      'checkout_locale' => [
        'title' => __( "Checkout Language", 'woocommerce-payson-checkout' ),
        'type' => "select",
        'options' => [
          'sv' => "Swedish",
          'en' => "English",
          'fi' => "Finnish",
          'no' => "Norwegian",
          'da' => "Danish",
          'es' => "Spanish",
          'de' => "German"
        ],
        'default' => "en"
      ],
      'checkout_color_scheme' => [
        'title' => __( "Checkout Color Scheme", 'woocommerce-payson-checkout' ),
        'type' => "select",
        'options' => [
          'White' => __( "White", 'woocommerce-payson-checkout' ),
          'Gray' => __( "Gray", 'woocommerce-payson-checkout' )
        ]
        ],
      'checkout_customer_phone' => [
        'title' => __( "Customer's Phone", 'woocommerce-payson-checkout' ),
        'type' => "select",
        'options' => [
          '' => __( "Don't ask customer's phone number", 'woocommerce-payson-checkout' ),
          'phone_optional' => __( "Customer's phone number is optional", 'woocommerce-payson-checkout' ),
          'request_phone' => __( "Require customer's phone number", 'woocommerce-payson-checkout' ),
        ],
        'default' => ""
      ],
      'checkout_verification' => [
        'title' => __( "Verification", 'woocommerce-payson-checkout' ),
        'type' => "checkbox",
        'label' => __( "Enable BankID verification", 'woocommerce-payson-checkout' ),
        'default' => "no"
      ]
    ];
  }

  /**
   * Get Checkout Object
   *
   * @param int $order_id 
   * @return void
   */
  public function checkout( $order_id = null )
  {
    if( empty( $this->merchant_id ) || empty( $this->api_key ) ) {
      throw new Exception( __( "Payson Checkout is not set up!", 'woocommerce-payson-checkout' ) );
    }

    $headers = [
      'Content-Type' => "application/json",
      'Authorization' => "Basic " . base64_encode( $this->merchant_id . ":" . $this->api_key )
    ];
    $items = [];
    $total = 0;

    if( ! $order_id ) {
      $cart = WC()->cart;
      $checkout = WC()->checkout();
      $order_id = $checkout->create_order( [] );
      $order = wc_get_order( $order_id );
      update_post_meta( $order_id, '_customer_user', get_current_user_id() );
      $order->calculate_totals();
      foreach( $cart->get_cart() as $item ) {
        $product = new WC_Product( $item['data']->get_id() );
        $items[] = [
          'name' => strip_tags( $item['data']->get_name() ),
          'unitPrice' => round( ( $item['line_total'] + $item['line_tax'] ) / $item['quantity'], 2 ),
          'quantity' => $item['quantity'],
          'taxRate' => 0.25,
          'reference' => $product->get_id()
        ];
        $total += round( $item['line_total'] + $item['line_tax'], 2 );
      }
    }
    else {
      $order = new WC_Order( $order_id );
      foreach( $order->get_items() as $item ) {
        $items[] = [
          'name' => strip_tags( $item->get_name() ),
          'unitPrice' => round( ( $item->get_total() + $item->get_total_tax() ) / $item->get_quantity(), 2 ),
          'quantity' => $item->get_quantity(),
          'taxRate' => 0.25,
          'reference' => $item->get_id()
        ];
        $total += round( $item->get_total() + $item->get_total_tax() );
      }
    }

    $endpoint = "no" == $this->test_mode ? "https://api.payson.se/2.0" : "https://test-api.payson.se/2.0";
    $response = wp_remote_request(
      $endpoint . "/Checkouts",
      [
        'headers' => $headers,
        'method' => "POST",
        'body' => wp_json_encode( [
          'merchant' => [
            'checkoutUri' => wc_get_checkout_url(),
            'termsUri' => wc_get_page_permalink( 'terms' ),
            'confirmationUri' => $order_id ? $order->get_checkout_order_received_url() : add_query_arg( 'payson_checkout_confirm', 1, wc_get_checkout_url() ),
            'notificationUri' => admin_url( "admin-ajax.php?action=payson_checkout_notification&order_id={$order_id}" )
          ],
          'customer' => [
            'type' => ! empty( WC()->customer->get_billing_company() ) ? "business" : "person",
            'identityNumber' => WC()->session->get( 'billing_ssn' ),
            'firstName' => WC()->customer->get_billing_first_name(),
            'lastName' => WC()->customer->get_billing_last_name(),
            'street' => WC()->customer->get_billing_address_1(),
            'postalCode' => WC()->customer->get_billing_postcode(),
            'city' => WC()->customer->get_billing_city(),
						'countryCode' => "SE",
						'email' => WC()->customer->get_billing_email(),
						'phone' => WC()->customer->get_billing_phone()
          ],
          'order' => [
            'currency' => get_woocommerce_currency(),
            'items' => $items,
            'totalPriceIncludingTax' => $total
          ],
          'gui' => [
            'type' => "select",
            'colorScheme' => $this->checkout_color_scheme,
            'locale' => $this->checkout_locale,
            'requestPhone' => "requestPhone" === $this->checkout_customer_phone,
            'phoneOptional' => "phoneOptional" === $this->checkout_customer_phone,
            'verification' => "no" === $this->checkout_verification ? "none" : "bankId"
          ]
        ] )
      ]
    );
    
    $code = wp_remote_retrieve_response_code( $response );
    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    WC()->session->set( 'payson_checkout_id', $body['id'] ?? "" );
    
    return $body;
  }

  /**
   * Fetch Payment Fields in Checkout
   *
   * @return void
   */
  public function payment_fields()
  {
    if( wp_doing_ajax() && isset( $_REQUEST['action'] ) && "payson_checkout" == $_REQUEST['action'] ) {
      $checkout = $this->checkout();
      if( is_wp_error( $checkout ) ) {
        wp_send_json_error( $checkout );
      }
      else {
        wp_send_json_success( $checkout );
      }
      die();
    }
    else {
      echo "<p>{$this->description}</p><div class=\"payson-checkout-snippet\"></div>";
    }
  }

  /**
   * Add Script on Checkout Page
   *
   * @return void
   */
  public function add_assets()
  {
    if( is_checkout() ) {
      echo '<script id="payson-checkout" src="' . plugins_url( "assets/js/payson-checkout.js", dirname( __FILE__ ) ) . '"></script>';
    }
  }

  /**
   * Redirect to Order Received URL
   *
   * @return void
   */
  public function redirect()
  {
    $payson_confirm = filter_input( INPUT_GET, "payson_confirm", FILTER_SANITIZE_STRING );
    $order_id = filter_input( INPUT_GET, "wc_order_id", FILTER_SANITIZE_STRING );
    if( $payson_confirm && $order_id ) {
      $order = new WC_Order( $order_id );
      $redirect = $order->get_checkout_order_received_url();

      header( "Location: {$redirect}" );
      exit;
    }
  }
}
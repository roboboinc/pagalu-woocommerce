<?php
/**
 * Plugin Name: PagaLu Payment Gateway WooCommerce Extension
 * Plugin URI: https://pagalu.co.mz/dev-products/woocommerce-extension/
 * Description: A Payment Gateway Plugin for PagaLu
 * Version: 1.0.0
 * Author: Robobo Inc
 * Author URI: http://Robobo.org/
 * Developers: Fei Manheche and Arnaldo Govene
 * Developer URI: http://robobo.org/
 * Text Domain: pagalu-woocommerce-extension
 * Domain Path: /languages
 *
 * Copyright: © 2009-2015 Robobo Inc.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
defined( 'ABSPATH' ) or exit;
// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
      return;
}
/**
 * Add the gateway to WC Available Gateways
 *
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + pagalu gateway
 */
function wc_pagalu_add_to_gateways( $gateways ) {
      $gateways[] = 'WC_Gateway_Pagalu';
      return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_pagalu_add_to_gateways' );
/**
 * Adds plugin page links
 *
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_pagalu_gateway_plugin_links( $links ) {
      $plugin_links = array(
            '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=pagalu_gateway' ) . '">' . __( 'Configure', 'wc-gateway-pagalu' ) . '</a>'
      );
      return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_pagalu_gateway_plugin_links' );
/**
 * pagalu Payment Gateway
 *
 * Provides an pagalu Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class         WC_Gateway_Pagalu
 * @extends       WC_Payment_Gateway
 * @version       1.0.0
 * @package       WooCommerce/Classes/Payment
 * @author        SkyVerge
 */
add_action( 'plugins_loaded', 'wc_pagalu_gateway_init', 11 );
function wc_pagalu_gateway_init() {
      class WC_Gateway_Pagalu extends WC_Payment_Gateway {
            /**
             * Constructor for the gateway.
             */
            public function __construct() {
                  $this->id                 = 'pagalu_gateway';
                  $this->icon               = apply_filters('woocommerce_pagalu_icon', '');
                  $this->has_fields         = false;
                  $this->method_title       = __( 'PagaLu', 'wc-gateway-pagalu' );
                  $this->method_description = __( 'A Payment Gateway from https://www.pagalu.co.mz/, allowing you to recieve payments from different local providers including bank transfer, mobile wallets and more added everyday.', 'wc-gateway-pagalu' );
            // Parameters and settings
            $this->mode  = $this->get_option( 'mode', 'sandbox' );
            $this->pagalu_url  = 'https://www.pagalu.co.mz/pagamento-ext/api/pay-ext/';
            $this->currency = 'MZN';
                  // Load the settings.
                  $this->init_form_fields();
                  $this->init_settings();
                  // Define user set variables
                  $this->title        = $this->get_option( 'title' );
                  $this->apitoken    = $this->get_option( 'api-token' );
                  $this->description  = $this->get_option( 'description' );
                  $this->instructions = $this->get_option( 'instructions', $this->description );
                  // Actions
                  add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
                  add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
                  // Customer Emails
                  add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
                  // The IPN
                  add_action( 'woocommerce_api_'. strtolower( get_class($this) ), array( $this, 'ipn' ) );

            }
            /**
             * Initialize Gateway Settings Form Fields
             */
            public function init_form_fields() {
                  $this->form_fields = apply_filters( 'wc_pagalu_form_fields', array(
                        'enabled' => array(
                              'title'   => __( 'Enable/Disable', 'wc-gateway-pagalu' ),
                              'type'    => 'checkbox',
                              'label'   => __( 'Enable PagaLu Payment', 'wc-gateway-pagalu' ),
                              'default' => 'yes'
                        ),
                        'api-token' => array(
                              'title'       => __( 'API-Token', 'wc-gateway-pagalu' ),
                              'type'        => 'text',
                              'description' => __( 'This is the API token you get from pagalu.co.mz.', 'wc-gateway-pagalu' ),
                              'default'     => __( '', 'wc-gateway-pagalu' ),
                              'desc_tip'    => true,
                        ),
                        'title' => array(
                              'title'       => __( 'Title', 'wc-gateway-pagalu' ),
                              'type'        => 'text',
                              'description' => __( 'This controls the title for the payment method the customer sees during checkout.', 'wc-gateway-pagalu' ),
                              'default'     => __( 'PagaLu Payment', 'wc-gateway-pagalu' ),
                              'desc_tip'    => true,
                        ),
                        'description' => array(
                              'title'       => __( 'Description', 'wc-gateway-pagalu' ),
                              'type'        => 'textarea',
                              'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-gateway-pagalu' ),
                              'default'     => __( 'You will be redirected to PagaLu Payment gateway to finalize payment.', 'wc-gateway-pagalu' ),
                              'desc_tip'    => true,
                        ),
                        'instructions' => array(
                              'title'       => __( 'Instructions', 'wc-gateway-pagalu' ),
                              'type'        => 'textarea',
                              'description' => __( 'Instructions that will be added to the thank you page and emails.', 'wc-gateway-pagalu' ),
                              'default'     => '',
                              'desc_tip'    => true,
                        ),
                  ) );
            }
            /**
             * Output for the order received page.
             */
            public function thankyou_page() {
                  if ( $this->instructions ) {
                        echo wpautop( wptexturize( $this->instructions ) );
                  }
            }
            /**
             * Add content to the WC emails.
             *
             * @access public
             * @param WC_Order $order
             * @param bool $sent_to_admin
             * @param bool $plain_text
             */
            public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
                  if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
                        echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
                  }
            }
            /**
             * Process the payment and return the result
             *
             * @param int $order_id
             * @return array
             */
            public function process_payment( $order_id ) {
                  $order = wc_get_order( $order_id );
            $order_data = $order->get_data(); // The Order data
                  // Mark as on-hold (we're awaiting the payment)
                  $order->update_status( 'on-hold', __( 'Awaiting PagaLu payment', 'wc-gateway-pagalu' ) );
                  $success_url = 'http://'.$_SERVER['SERVER_NAME'].'?wp-api=WC_Gateway_Pagalu';

            // Here we send the user to PagaLu.co.mz for processing
            $params                            = array();
            $params[ 'value' ]                 = $order->get_total();
            $params[ 'reference' ]             = $order_id;
            $params[ 'success_url' ]           = $success_url;//$this->ipn_url; //url where IPN messages will be sent after purchase, then validate in the ipn() method
            $params[ 'reject_url' ]           = $success_url;//$this->ipn_url; //url where IPN messages will be sent after purchase, then validate in the ipn() method
            $params[ 'extras' ]  = $order_data['billing']['first_name']. ' '. $order_data['billing']['last_name'];
            $params[ 'phone_number' ]  = $order_data['billing']['phone'];
            $params[ 'email' ]  = $order_data['billing']['email'];
//            if ( $this->mode == 'sandbox' ) {
//                $params[ 'demo' ] = 'Y';
//            }
            $ch = curl_init();
            $params = json_encode($params); // Json encodes $params array
            $authorization = "Authorization: Bearer ";
            $authorization .=  $this->apitoken;
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization ));
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, $this->pagalu_url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            // receive server response ...
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $server_output = curl_exec ($ch);
            //close connection
            curl_close ($ch);
            flush();
            // Reduce stock levels
                  $order->reduce_order_stock();
                  // Remove cart
                  WC()->cart->empty_cart();
            $json = json_decode($server_output, true);
            // further processing ....
            if (json_last_error() == JSON_ERROR_NONE) {
                // SUccess Redirect to PagaLu
//                echo wpautop( wptexturize( $json['response_url'] ) );
                $json_url = $json['response_url'];
//                wp_redirect("$json_url"); //Redirect to PagaLU
                return array(
                'result'   => 'success',
                'redirect' => $json_url
                );
            } else {
                //FAIL at PAGALU
                return array(
                'result'   => 'fail',
                );
                exit;
            }
            exit;
            }
        function ipn() {

          if (isset($_GET['id'])){

            $id = $_GET['id'];

            $ch = curl_init();
            $authorization = "Authorization: Bearer ";
            $authorization .=  $this->apitoken;
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization ));
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, $this->pagalu_url.$id.'/');
            // receive server response ...
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $server_output = curl_exec ($ch);
            //close connection
            curl_close ($ch);
            flush();

            $json = json_decode($server_output, true);

            if (json_last_error() == JSON_ERROR_NONE) {
              if(isset($json['status'])){
                 $order_id = $json['reference'];
                 $order = new WC_Order( $order_id );
                 $order->update_status($json['status']);
              }

            } 

          }
         exit;
        }
  } // end \WC_Gateway_Pagalu class
}

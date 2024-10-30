<?php

/** 
 * Plugin Name: Bizfirst Pay
 * Plugin URI: https://www.bizfirst.xyz/
 * Description: Bizfirst Pay plugin for WooCommerce
 * Version: 1.0.0
 * Author: Bizfirst
 * Author URI: https://profiles.wordpress.org/bizfirst/
 * License: GPLv2 or later
 * Text Domain: bizfirst-pay
 * Domain Path: /languages/
 * WC requires at least: 2.6.0
 * WC tested up to: 3.3.0
 */


if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if (! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}


add_action( 'plugins_loaded', 'bizfirst_pay_init', 0 );

function bizfirst_pay_init() {
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
        return;
    }

    class WC_Bizfirst_Pay extends WC_Payment_Gateway {

        public function __construct() {
            $this->id = 'bizfirst_pay';
            $this->icon = apply_filters( 'woocommerce_bizfirst_pay_icon', plugins_url('/assets/logo.png', __FILE__ ) );

            $this->method_title = __( 'Bizfirst', 'bizfirst-pay' );
            $this->method_description = __( 'Crypto Payments made simple – designed exclusively for WooCommerce stores. Accept USDC payments on your WooCommerce Store', 'bizfirst-pay' );
            $this->has_fields = false;
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->instructions = $this->get_option( 'instructions', $this->description );
            $this->api_key = $this->get_option('api_key');

       
            $this->init_form_fields();
            $this->init_settings();
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_thank_you_' . $this->id, array( $this, 'thank_you_page' ) );
            add_action( 'bizfirst_pay_webhook', array($this, 'webhook') );

        }
        public function init_form_fields() {
            $this->form_fields = apply_filters( 'bizfirst_pay_fields', array(
                'enabled' => array(
                    'title' => __( 'Enable/Disable', 'bizfirst-pay'),
                    'type' => 'checkbox',
                    'label' => __( 'Enable or Disable BizFirst Pay', 'bizfirst-pay'),
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => __( 'Title', 'bizfirst-pay'),
                    'type' => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'bizfirst-pay'),
                    'default' => __( 'USDC Payment (Solana)', 'bizfirst-pay')
                ),
                'description' => array(
                    'title' => __( 'Description', 'bizfirst-pay'),
                    'type' => 'textarea',
                    'description' => __( 'This controls the description which the user sees during checkout.', 'bizfirst-pay'),
                    'default' => __( 'Pay using USDC on the Solana Blockchain', 'bizfirst-pay')
                ),
                'instructions' => array(
                    'title' => __( 'Instructions', 'bizfirst-pay'),
                    'type' => 'textarea',
                    'description' => __( 'Instructions that will be added to the thank you page.', 'bizfirst-pay'),
                    'default' => __( 'Pay via any Solana wallet', 'bizfirst-pay')
                ),
                'api_key' => array(
                    'title' => __( 'API Key', 'bizfirst-pay'),
                    'type' => 'text',
                    'description' => __( 'Enter your BizFirst Pay API Key.', 'bizfirst-pay'),
                    'default' => ''
                ),
            ));
        }
        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );
            $order->update_status( 'on-hold', __( 'Awaiting BizFirst Pay payment', 'bizfirst-pay' ) );

            $body = array(
                'customerFirstName'    => $order->get_billing_first_name(),
                'customerLastName'     => $order->get_billing_last_name(),
                'customerEmail'        => $order->get_billing_email(),
                'ecommerceOrderId'     => $order_id,
                'amount' => $order->get_total(),
                'successUrl' => $this->get_return_url( $order ),
                'failureUrl' => wc_get_cart_url(),
                'serverNotificationUrl' => get_bloginfo('url')."/wc-api/bizfirst_pay_webhook/?nonce=".$nonce."&order_id=".$order_id,
                'baseCurrency' => $order->get_currency()
            );
            $headers = array(
                'Content-Type' => 'application/json',
                'x-bizfirst-api-key' => $this->api_key
            );
            $args = array(
                'body' => json_encode($body),
                'headers' => $headers,
                'method' => 'POST',
                'timeout' => '10'
            );

            $response = wp_remote_request( 'https://app.bizfirst.xyz/api/checkout', $args );

            
            $url = json_decode($response['body'])->url;
            return array(
              'result' => 'success',
              'redirect' => $url
            );

            
        }

        public function webhook() {
            header( 'HTTP/1.1 200 OK' );
            $order_id = isset($_GET['order_id']) ? sanitize_text_field($_GET['order_id']) : null;
            $order_id = absint( $order_id );
           
            if ( ! $order_id ) {
                wp_die( 'Invalid order ID', 'bizfirst-pay' );
            }
            $order_id = (string) $order_id;
            $order_id = esc_sql( $order_id );
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                wp_die( 'Invalid order ID', 'bizfirst-pay' );
            }
            $order->payment_complete();
            wc_reduce_stock_levels($order_id);


            $order->update_status( 'complete', __( 'Cleared Payment', 'bizfirst-pay' ) );
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url( $order )
            );
        }

        public function thank_you_page() {
            echo(wpautop("Thanks for your payment", true));
        }
    }

    
}
add_filter( 'woocommerce_payment_gateways', 'add_bizfirst_pay_gateway' );

function add_bizfirst_pay_gateway( $gateways ) {
    $gateways[] = 'WC_Bizfirst_Pay';
    return $gateways;
}

?>
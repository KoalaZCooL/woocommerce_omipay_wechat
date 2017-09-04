<?php
/*
Plugin Name: WeChat Pay - OmiPay WooCommerce Payment Gateway
Plugin URI: http://www.digitaljungle.agency/
Description: WooCommerce custom payment gateway integration for WeChat Pay via OmiPay.
Version: 0.0
*/

add_action( 'plugins_loaded', 'wechat_omipay_init', 0 );
function wechat_omipay_init() {
    //if condition use to do nothin while WooCommerce is not installed
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
    include_once( 'wechat-omipay-woocommerce.php' );
    // class add it too WooCommerce
    add_filter( 'woocommerce_payment_gateways', 'wechat_add_omipay_gateway' );
    function wechat_add_omipay_gateway( $methods ) {
        $methods[] = 'wechat_OmiPay';
        return $methods;
    }
}
// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wechat_omipay_action_links' );
function wechat_omipay_action_links( $links ) {
    $plugin_links = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'wechat-omipay' ) . '</a>',
    );
    return array_merge( $plugin_links, $links );
}
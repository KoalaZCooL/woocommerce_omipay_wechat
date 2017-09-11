<?php

class wechat_OmiPay extends WC_Payment_Gateway {

    function __construct() {

        // global ID
        $this->id = "wechat_omipay";

        // Show Title
        $this->method_title = __( "WeChat Pay", 'wechat-omipay' );

        // Show Description
        $this->method_description = __( "WeChat OmiPay Gateway Plug-in for WooCommerce", 'wechat-omipay' );

        // vertical tab title
        $this->title = __( "WeChat Pay", 'wechat-omipay' );


        $this->icon = null;

        $this->has_fields = true;

        // support default form with credit card
        $this->supports = array( 'qr_code' );

        // setting defines
        $this->init_form_fields();

        // load time variable setting
        $this->init_settings();

        // Turn these settings into variables we can use
        foreach ( $this->settings as $setting_key => $value ) {
            $this->$setting_key = $value;
        }

        // further check of SSL if you want
        add_action( 'admin_notices', array( $this,	'do_ssl_check' ) );

        // Save settings
        if ( is_admin() ) {
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }
    } // Here is the  End __construct()

    // administration fields for specific Gateway
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'		=> __( 'Enable / Disable', 'wechat-omipay' ),
                'label'		=> __( 'Enable this payment gateway', 'wechat-omipay' ),
                'type'		=> 'checkbox',
                'default'	=> 'no',
            ),
            'title' => array(
                'title'		=> __( 'Title', 'wechat-omipay' ),
                'type'		=> 'text',
                'desc_tip'	=> __( 'Payment title of checkout process.', 'wechat-omipay' ),
                'default'	=> __( 'WeChat Pay', 'wechat-omipay' ),
            ),
            'description' => array(
                'title'		=> __( 'Description', 'wechat-omipay' ),
                'type'		=> 'textarea',
                'desc_tip'	=> __( 'Payment title of checkout process.', 'wechat-omipay' ),
                'default'	=> __( 'Successfull payment through WeChat.', 'wechat-omipay' ),
                'css'		=> 'max-width:450px;'
            ),
            'merchant_number' => array(
                'title'		=> __( 'OmiPay Merchant ID (Numbers only!)', 'wechat-omipay' ),
                'type'		=> 'numeric',
                'desc_tip'	=> __( 'This is the Merchant Number provided by OmiPay when you signed up for an account.', 'wechat-omipay' ),
            ),
            'secret_key' => array(
                'title'		=> __( 'OmiPay API Secret Key', 'wechat-omipay' ),
                'type'		=> 'password',
                'desc_tip'	=> __( 'This is the API Secret Key provided by OmiPay when you signed up for an account.', 'wechat-omipay' ),
            ),
            'showing_debug' => array(
                'title'		=> __( 'Plugin Debug Mode', 'wechat-omipay' ),
                'label'		=> __( 'Enable Debug Mode', 'wechat-omipay' ),
                'type'		=> 'checkbox',
                'description' => __( 'Show Plugin Debugger. [OmiPay has NO test gateway] ', 'wechat-omipay' ),
                'default'	=> 'no',
            )
        );
    }

    // Response handled for payment gateway
    public function process_payment( $order_id ) {
        global $woocommerce;

        $customer_order = new WC_Order( $order_id );

        $this->nonce_str = str_replace('?=','', wp_nonce_url( '', 'WHA_WeChat_Checkout_'.$order_id, '' ) );

        $TZ_orig = date_default_timezone_get();

        #MAKE EST
        date_default_timezone_set('EST');
        $TZ_tgt = date('T_').$order_id;

        #MAKEMILLISECOND
        $timestamp = time()*1000;

        #REVERT TZ ORIG
        date_default_timezone_set($TZ_orig);

        $sign = $this->gen_signature($timestamp);
        $verifying_sig = [
            'm_number'  => $this->merchant_number,
            'timestamp' => $timestamp,
            'nonce_str' => $this->nonce_str,
            'sign'      => $sign
        ];

        // Decide which URL to post to
        $gateway_rate = 'https://www.omipay.com.au/omipay/api/v1/GetExchangeRate';
        $gateway_rate;
        $gateway_QR = 'https://www.omipay.com.au/omipay/api/v1/MakeQROrder';
        $gateway_QR;
        $QR_params = [
            'order_name'    => 'X_'.'WooCommerce_Checkout_Order',
            'amount'        => 1, #in CENTS AUD

            #Notification URL for transaction success.
            #When this order is pay succeed, will send a notification to such URL.
            'notify_url'    => 'http://www.digitaljunglegroup.com/test/omipay_notif_stream.php',

            #The notification data of transaction would include this field.
            #So, it best be unique, in order to identify the order.
            'out_order_no'  => 'out order to omipay okay got it'
        ];
        $gateway_params = http_build_query(array_merge($verifying_sig,$QR_params));
        $gateway_query = 'https://www.omipay.com.au/omipay/api/v1/QueryOrder';
        $gateway_query;
        $gateway_endpoint = $gateway_QR;

        // Send this payload to OmiPay for processing
        $gateway_request_url = $gateway_endpoint.'?'.$gateway_params;
        $response = wp_remote_post( $gateway_request_url, array(
            'method'    => 'POST',
            'headers'   => array("Content-type" => "application/json;charset=UTF-8"),
//            'body'      => http_build_query( $payload ),
            'timeout'   => 90,
            'sslverify' => false,
        ) );

        if ( is_wp_error( $response ) )
            throw new Exception( __( 'There is issue for connecting payment gateway. Sorry for the inconvenience.', 'wechat-omipay' ) );

        if ( empty( $response['body'] ) )
            throw new Exception( __( 'OmiPay\'s Response was not get any data.', 'wechat-omipay' ) );

        // get body response while get not error
        $response_body = json_decode(wp_remote_retrieve_body($response) );

        wc_add_notice( $this->gen_qrcode_string($response_body->qrcode), 'error' );
    }

    // Validate fields
    public function validate_fields() {
        return true;
    }

    public function do_ssl_check() {
        if( $this->enabled == "yes" ) {
            if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
                echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";
            }
        }
    }

    private function gen_signature($timestamp)
    {
        $gen_sig = strtoupper(md5("{$this->merchant_number}&{$timestamp}&{$this->nonce_str}&{$this->secret_key}") );
        return $gen_sig;
    }

    #https://stackoverflow.com/questions/5943368/dynamically-generating-a-qr-code-with-php
    public function gen_qrcode_string($string)
    {
        $str = urlencode($string);
        return <<<QRC
<img src="https://chart.googleapis.com/chart?chs=256x256&cht=qr&chl=$str&choe=UTF-8"/>
<p>$string</p>
QRC;
    }
}
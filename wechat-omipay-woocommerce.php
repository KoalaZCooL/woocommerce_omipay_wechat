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

        // This is where the fun stuff begins
        $payload = array(
            // OmiPay Credentials and API Info

            // Order total
            "x_amount"             	=> $customer_order->order_total,

/* OmiPay do not need these
            // Credit Card Information
            "x_card_num"           	=> str_replace( array(' ', '-' ), '', $_POST['wechat_omipay-card-number'] ),
            "x_card_code"          	=> ( isset( $_POST['wechat_omipay-card-cvc'] ) ) ? $_POST['wechat_omipay-card-cvc'] : '',
            "x_exp_date"           	=> str_replace( array( '/', ' '), '', $_POST['wechat_omipay-card-expiry'] ),

            "x_type"               	=> 'AUTH_CAPTURE',
            "x_invoice_num"        	=> str_replace( "#", "", $customer_order->get_order_number() ),
            "x_test_request"       	=> $showing_debug,
            "x_delim_char"         	=> '|',
            "x_encap_char"         	=> '',
            "x_delim_data"         	=> "TRUE",
            "x_relay_response"     	=> "FALSE",
            "x_method"             	=> "CC",

            // Billing Information
            "x_first_name"         	=> $customer_order->billing_first_name,
            "x_last_name"          	=> $customer_order->billing_last_name,
            "x_address"            	=> $customer_order->billing_address_1,
            "x_city"              	=> $customer_order->billing_city,
            "x_state"              	=> $customer_order->billing_state,
            "x_zip"                	=> $customer_order->billing_postcode,
            "x_country"            	=> $customer_order->billing_country,
            "x_phone"              	=> $customer_order->billing_phone,
            "x_email"              	=> $customer_order->billing_email,

            // Shipping Information
            "x_ship_to_first_name" 	=> $customer_order->shipping_first_name,
            "x_ship_to_last_name"  	=> $customer_order->shipping_last_name,
            "x_ship_to_company"    	=> $customer_order->shipping_company,
            "x_ship_to_address"    	=> $customer_order->shipping_address_1,
            "x_ship_to_city"       	=> $customer_order->shipping_city,
            "x_ship_to_country"    	=> $customer_order->shipping_country,
            "x_ship_to_state"      	=> $customer_order->shipping_state,
            "x_ship_to_zip"        	=> $customer_order->shipping_postcode,

            // information customer
            "x_cust_id"            	=> $customer_order->user_id,
            "x_customer_ip"        	=> $_SERVER['REMOTE_ADDR'],
//*///
        );

        // Send this payload to OmiPay for processing
        $gateway_request_url = $gateway_endpoint.'?'.$gateway_params;
        $response = wp_remote_post( $gateway_request_url, array(
            'method'    => 'POST',
            'headers'   => array("Content-type" => "application/json;charset=UTF-8"),
//            'body'      => http_build_query( $payload ),
            'timeout'   => 90,
            'sslverify' => false,
        ) );

        throw new Exception( __( '<pre style="color: blue">'.print_r([$TZ_orig, $TZ_tgt, $verifying_sig, $response],1).'</pre>', 'wechat-omipay' ) );

        if ( is_wp_error( $response ) )
            throw new Exception( __( 'There is issue for connecting payment gateway. Sorry for the inconvenience.', 'wechat-omipay' ) );

        if ( empty( $response['body'] ) )
            throw new Exception( __( 'OmiPay\'s Response was not get any data.', 'wechat-omipay' ) );

        // get body response while get not error
        $response_body = wp_remote_retrieve_body( $response );

        foreach ( preg_split( "/\r?\n/", $response_body ) as $line ) {
            $resp = explode( "|", $line );
        }

        // values get
        $r['response_code']             = $resp[0];
        $r['response_sub_code']         = $resp[1];
        $r['response_reason_code']      = $resp[2];
        $r['response_reason_text']      = $resp[3];

        // 1 or 4 means the transaction was a success
        if ( ( $r['response_code'] == 1 ) || ( $r['response_code'] == 4 ) ) {
            // Payment successful
            $customer_order->add_order_note( __( 'OmiPay complete payment.', 'wechat-omipay' ) );

            // paid order marked
            $customer_order->payment_complete();

            // this is important part for empty cart
            $woocommerce->cart->empty_cart();

            // Redirect to thank you page
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url( $customer_order ),
            );
        } else
        {
            //transaction fail
            wc_add_notice( 'test notice<br>with html', 'error' );
            $customer_order->add_order_note( 'Error:<br>add order note html' );
        }

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
}
<?php
if (! defined ( 'ABSPATH' ))
	exit (); // Exit if accessed directly

class XHWechatWCPaymentGateway extends WC_Payment_Gateway {
	private $config;

	public function __construct() {
		//支持退款
		array_push($this->supports,'refunds');

		$this->id = XH_WC_WeChat_ID;
		$this->icon =XH_WC_WeChat_URL. '/images/logo.png';
		$this->has_fields = false;

		$this->method_title = '微信支付'; // checkout option title
		$this->method_description='企业版本支持微信原生支付（H5公众号）、微信登录、微信红包推广/促销、微信收货地址同步、微信退款等功能。若需要企业版本，请访问 ';

		$this->init_form_fields ();
		$this->init_settings ();

		$this->title = $this->get_option ( 'title' );
		$this->description = $this->get_option ( 'description' );

		$lib = XH_WC_WeChat_DIR.'/lib';

		include_once ($lib . '/WxPay.Data.php');
		include_once ($lib . '/WxPay.Api.php');
		include_once ($lib . '/WxPay.Exception.php');
		include_once ($lib . '/WxPay.Notify.php');
		include_once ($lib . '/WxPay.Config.php');
		include_once ($lib . '/log.php');
		$this->config =new WechatPaymentConfig ($this->get_option('wechatpay_appID'),  $this->get_option('wechatpay_mchId'), $this->get_option('wechatpay_key'));
	}

	function init_form_fields() {
		$this->form_fields = array (
			'enabled' => array (
				'title' => __ ( 'Enable/Disable', 'wechatpay' ),
				'type' => 'checkbox',
				'label' => __ ( 'Enable WeChatPay Payment', 'wechatpay' ),
				'default' => 'no'
			),
			'title' => array (
				'title' => __ ( 'Title', 'wechatpay' ),
				'type' => 'text',
				'description' => __ ( 'This controls the title which the user sees during checkout.', 'wechatpay' ),
				'default' => __ ( 'WeChatPay', 'wechatpay' ),
				'css' => 'width:400px'
			),
			'description' => array (
				'title' => __ ( 'Description', 'wechatpay' ),
				'type' => 'textarea',
				'description' => __ ( 'This controls the description which the user sees during checkout. Can be HTML.', 'wechatpay' ),
				'default' => __ ( "Pay via WeChatPay, if you don't have an WeChatPay account, you can also pay with your debit card or credit card", 'wechatpay' ),
				//'desc_tip' => true ,
				// 'css' => 'width:400px'
			),
			'wechatpay_appID' => array (
				'title' => __ ( '', 'wechatpay' ),
				'type' => 'hidden',
				'description' => __ ( '', 'wechatpay' ),
				//'css' => 'width:400px'
			),
			'wechatpay_mchId' => array (
				'title' => __ ( 'OmiPay Merchant ID', 'wechatpay' ),
				'type' => 'text',
				'description' => __ ( '[Numbers Only] This is the Merchant Number provided by OmiPay when you signed up for an account.', 'wechatpay' ),
				'css' => 'width:400px'
			),
			'wechatpay_key' => array (
				'title' => __ ( 'OmiPay API Secret Key', 'wechatpay' ),
				'type' => 'password',
				'description' => __ ( 'This is the API Secret Key provided by OmiPay when you signed up for an account; this is needed in order to take payment.', 'wechatpay' ),
				// 'css' => 'width:400px',
				//'desc_tip' => true
			),
			'exchange_rate'=> array (
				'title' => __ ( '', 'wechatpay' ),
				'type' => 'hidden',
				'default'=>1,
				'description' =>  __ ( "", 'wechatpay' ),
				//'css' => 'width:80px;',
				//'desc_tip' => true
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

	public function process_payment($order_id) {
		$order = new WC_Order ( $order_id );
		return array (
			'result' => 'success',
			'redirect' => $order->get_checkout_payment_url ( true )
		);
	}

	public  function woocommerce_wechatpay_add_gateway( $methods ) {
		$methods[] = $this;
		return $methods;
	}

	/**
	 * 
	 * @param WC_Order $order
	 * @param number $limit
	 * @param string $trimmarker
	 */
	public  function get_order_title($order,$limit=32,$trimmarker='...'){
		$id = method_exists($order, 'get_id')?$order->get_id():$order->id;
		$title="#{$id}|".get_option('blogname');

		$order_items =$order->get_items();
		if($order_items&&count($order_items)>0){
			$title="#{$id}|";
			$index=0;
			foreach ($order_items as $item_id =>$item){
				$title.= $item['name'];
				if($index++>0){
					$title.='...';
					break;
				}
			}    
		}

		return apply_filters('xh_wechat_wc_get_order_title', mb_strimwidth ( $title, 0,32, '...','utf-8'));
	}

	public function get_order_status() {
		$order_id = isset($_POST ['orderId'])?$_POST ['orderId']:'';
		$order = new WC_Order ( $order_id );
		$isPaid = ! $order->needs_payment ();
//
//		echo json_encode ( array (
//		    'status' =>$isPaid? 'paid':'unpaid',
//		    'url' => $this->get_return_url ( $order ),
//            'body' => print_r($order,1)
//		));
//
//		exit;

		$OmiPayOrderNo = $this->get_omipay_order_notes($order_id);
		$OmiPayOrderNo = unserialize(str_replace('OmiPayWeiXin_order_no_','', $OmiPayOrderNo));
		//"order_no":"WE1709157514475751"
		$input = array('order_no'=> $OmiPayOrderNo['order_no']);

		try {
			$query_result = WechatPaymentApi::orderQuery ( $input, $this->config );

			if('PAYED'===$query_result['result_code'] ||'CLOSED'===$query_result['result_code'] ){
					$order->payment_complete();
					$isPaid = 1;
			}
			echo json_encode ( array (
					'status' =>$isPaid? 'paid':'unpaid',
					'url' => $this->get_return_url ( $order ),
					'$OmiPayOrderNo' => $OmiPayOrderNo,
					'$query_result' => serialize($query_result)
			));

			exit;
		} catch ( WechatPaymentException $e ) {
			return;
		}
	}

	function wp_enqueue_scripts() {
		$orderId = get_query_var ( 'order-pay' );
		$order = new WC_Order ( $orderId );
		$payment_method = method_exists($order, 'get_payment_method')?$order->get_payment_method():$order->payment_method;
		if ($this->id == $payment_method) {
			if (is_checkout_pay_page () && ! isset ( $_GET ['pay_for_order'] )) {

				wp_enqueue_script ( 'XH_WECHAT_JS_QRCODE', XH_WC_WeChat_URL. '/js/qrcode.js', array (), XH_WC_WeChat_VERSION );
				wp_enqueue_script ( 'XH_WECHAT_JS_CHECKOUT', XH_WC_WeChat_URL. '/js/checkout.js', array ('jquery','XH_WECHAT_JS_QRCODE' ), XH_WC_WeChat_VERSION );
			}
		}
	}

	public function check_wechatpay_response() {
		if(defined('WP_USE_THEMES')&&!WP_USE_THEMES){
			return;
		}
		$xml = isset($GLOBALS ['HTTP_RAW_POST_DATA'])?$GLOBALS ['HTTP_RAW_POST_DATA']:'';	
		if(empty($xml)){
			return ;
		}

		// 如果返回成功则验证签名
		try {
			$result = WechatPaymentResults::Init ( $xml );
			if (!$result||! isset($result['transaction_id'])) {
					return;
			}

			$transaction_id=$result ["transaction_id"];
			$order_id = $result['attach'];

			$input = new WechatPaymentOrderQuery ();
			$input->SetTransaction_id ( $transaction_id );
			$query_result = WechatPaymentApi::orderQuery ( $input, $this->config );
			if ($query_result['result_code'] == 'FAIL' || $query_result['return_code'] == 'FAIL') {
				throw new Exception(sprintf("return_msg:%s ;err_code_des:%s "), $query_result['return_msg'], $query_result['err_code_des']);
			}

			if(!(isset($query_result['trade_state'])&& $query_result['trade_state']=='SUCCESS')){
				throw new Exception("order not paid!");
			}

			$order = new WC_Order ( $order_id );
			if($order->needs_payment()){
				$order->payment_complete ($transaction_id);
			}

			$reply = new WechatPaymentNotifyReply ();
			$reply->SetReturn_code ( "SUCCESS" );
			$reply->SetReturn_msg ( "OK" );

			WxpayApi::replyNotify ( $reply->ToXml () );
			exit;
		} catch ( WechatPaymentException $e ) {
			return;
		}
	}

	public function process_refund( $order_id, $amount = null, $reason = ''){		
		$order = new WC_Order ($order_id );
		if(!$order){
			return new WP_Error( 'invalid_order','错误的订单' );
		}

		$trade_no =$order->get_transaction_id();
		if (empty ( $trade_no )) {
			return new WP_Error( 'invalid_order', '未找到微信支付交易号或订单未支付' );
		}

		$total = $order->get_total ();
		//$amount = $amount;
		$preTotal = $total;
		$preAmount = $amount;

		$exchange_rate = floatval($this->get_option('exchange_rate'));
		if($exchange_rate<=0){
			$exchange_rate=1;
		}

		$total = round ( $total * $exchange_rate, 2 );
		$amount = round ( $amount * $exchange_rate, 2 );

		$total = ( int ) ( $total  * 100);
		$amount = ( int ) ($amount * 100);

		if($amount<=0||$amount>$total){
			return new WP_Error( 'invalid_order',__('Invalid refused amount!' ,XH_WECHAT) );
		}

		$transaction_id = $trade_no;
		$total_fee = $total;
		$refund_fee = $amount;

		$input = new WechatPaymentRefund ();
		$input->SetTransaction_id ( $transaction_id );
		$input->SetTotal_fee ( $total_fee );
		$input->SetRefund_fee ( $refund_fee );

		$input->SetOut_refund_no ( $order_id.time());
		$input->SetOp_user_id ( $this->config->getMCHID());

		try {
			$result = WechatPaymentApi::refund ( $input,60 ,$this->config);
			if ($result ['result_code'] == 'FAIL' || $result ['return_code'] == 'FAIL') {
				Log::DEBUG ( " XHWechatPaymentApi::orderQuery:" . json_encode ( $result ) );
				throw new Exception ("return_msg:". $result ['return_msg'].';err_code_des:'. $result ['err_code_des'] );
			}

		} catch ( Exception $e ) {
			return new WP_Error( 'invalid_order',$e->getMessage ());
		}

		return true;
	}

	/**
	 * 
	 * @param WC_Order $order
	 */
	function receipt_page($order_id) {
		$order = new WC_Order($order_id);
		if(!$order||!$order->needs_payment()){
			wp_redirect($this->get_return_url($order));
			exit;
		}

		echo '<p>' . __ ( 'Please scan the QR code with WeChat to finish the payment.', 'wechatpay' ) . '</p>';

#CHECK FOR OMIPAY PRIVATE ORDER NOTES
		$OmiPayOrderNo = $this->get_omipay_order_notes($order_id);
		$result = unserialize(str_replace('OmiPayWeiXin_order_no_','', $OmiPayOrderNo));

#IF NO OMIPAY PRIVATE ORDER NOTES, EXECUTE QUERY NEW QRorder
#ELSE  SKIP GATEWAY QUERY
		if(empty($result)){

			$SetOut_trade_no = md5(date ( "YmdHis" ).$order_id );

			$input = array(
					'order_name'    => 'WHA_Checkout_Order',
					'amount'        => ( int ) ($order->get_total () * 100), #in CENTS AUD

					#Notification URL for transaction success.
					#When this order is pay succeed, will send a notification to such URL.
					'notify_url'    => ('http://www.digitaljunglegroup.com/test/omipay_notif_stream.php'),

					#The notification data of transaction would include this field.
					#So, it best be unique, in order to identify the order.
					'out_order_no'  => $SetOut_trade_no
			);

			try {
					$result = $stor_resp = WechatPaymentApi::unifiedOrder ( $input, 60, $this->config );
					unset($stor_resp['debug_everythingelse']);
					$order->add_order_note("OmiPayWeiXin_order_no_".(serialize($stor_resp)));
			} catch (Exception $e) {
					echo $e->getMessage();
					return;
			}
//		if((isset($result['result_code'])&& $result['result_code']=='FAIL')
//		    ||
//		    (isset($result['return_code'])&&$result['return_code']=='FAIL')){
//
//		    echo "return_msg:".$result['return_msg']." ;err_code_des: ".$result['err_code_des'];
//		    return;
//		}
		}
		$url = isset($result['qrcode'])? $result ["qrcode"]:'';

		echo  '<input type="hidden" id="xh-wechat-payment-pay-url" value="'.$url.'"/>';
		echo  '<div style="width:200px;height:200px" id="xh-wechat-payment-pay-img" data-oid="'.$order_id.'"></div>';

// SHOW ON DEBUG MODE
		if('yes'===$this->get_option('showing_debug')){?>
			<p><span style="color: blue">debug mode on</span></p>
			<pre id="omipaywechat" style="display: none;">
				<?=print_r(array('$input'=>$input,'$result'=>$result),1)?>
			</pre>
		<?php }
	}

	private function get_omipay_order_notes($order_id){
		global $wpdb;

		$table_perfixed = $wpdb->prefix . 'comments';
		$results = $wpdb->get_results("
			SELECT `comment_ID`, `comment_post_ID`, `comment_content`
			FROM $table_perfixed
			WHERE  `comment_post_ID` = $order_id
			AND  `comment_type` =  'order_note'
			AND  `comment_content` LIKE  'OmiPayWeiXin_order_no_%'
			ORDER BY `comment_ID` DESC
			LIMIT 1
		");

		foreach($results as $note){
			$order_note  = $note->comment_content;
		}
		return $order_note;
	}
}
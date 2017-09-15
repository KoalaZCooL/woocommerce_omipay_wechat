<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

require_once "WxPay.Exception.php";
require_once "WxPay.Config.php";
require_once "WxPay.Data.php";

/**
 * 
 * 接口访问类，包含所有微信支付API列表的封装，类中方法为static方法，
 * 每个接口有默认超时时间（除提交被扫支付为10s，上报超时时间为1s外，其他均为6s）
 *
 */
class WechatPaymentApi
{
	/**
	 * 
	 * 统一下单，WechatPaymentUnifiedOrder中out_trade_no、body、total_fee、trade_type必填
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param WechatPaymentUnifiedOrder $inputObj
	 * @param int $timeOut
	 * @throws WechatPaymentException
	 * @return 成功时返回，其他抛异常
	 */
	public static function unifiedOrder($inputObj, $timeOut = 60,$WxCfg)
	{
        $exchange = self::postXmlCurl(array(), 'https://www.omipay.com.au/omipay/api/v1/GetExchangeRate', false, $timeOut,$WxCfg, array());
		$url = "https://www.omipay.com.au/omipay/api/v1/MakeQROrder";

		$startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($inputObj, $url, false, $timeOut,$WxCfg, array());
		$result = WechatPaymentResults::Init($response,$WxCfg);
        $result['everythingelse'] = array(
            '$inputObj' => $inputObj,
            '$url' => $url,
            '$response' => $response,
            '$exchange' => $exchange
        );
		self::reportCostTime($url, $startTimeStamp, $result,$WxCfg);//上报请求花费时间

		return $result;
	}

	
	/**
	 * 
	 * 测速上报，该方法内部封装在report中，使用时请注意异常流程
	 * WechatPaymentReport中interface_url、return_code、result_code、user_ip、execute_time_必填
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param WechatPaymentReport $inputObj
	 * @param int $timeOut
	 * @throws WechatPaymentException
	 * @return 成功时返回，其他抛异常
	 */
	public static function report($inputObj, $timeOut = 60,$WxCfg)
	{
		$url = "https://api.mch.weixin.qq.com/payitil/report";
		//检测必填参数
		if(!$inputObj->IsInterface_urlSet()) {
			throw new WechatPaymentException("接口URL，缺少必填参数interface_url！");
		} if(!$inputObj->IsReturn_codeSet()) {
			throw new WechatPaymentException("返回状态码，缺少必填参数return_code！");
		} if(!$inputObj->IsResult_codeSet()) {
			throw new WechatPaymentException("业务结果，缺少必填参数result_code！");
		} if(!$inputObj->IsUser_ipSet()) {
			throw new WechatPaymentException("访问接口IP，缺少必填参数user_ip！");
		} if(!$inputObj->IsExecute_time_Set()) {
			throw new WechatPaymentException("接口耗时，缺少必填参数execute_time_！");
		}
		$inputObj->SetAppid($WxCfg->getAPPID());//公众账号ID
		$inputObj->SetMch_id($WxCfg->getMCHID());//商户号
		$inputObj->SetUser_ip($_SERVER['REMOTE_ADDR']);//终端ip
		$inputObj->SetTime(date("YmdHis"));//商户上报时间	 
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		$inputObj->SetSign($WxCfg);//签名
		$xml = $inputObj->ToXml();
		
		$startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($xml, $url, false, $timeOut,$WxCfg);
		return $response;
	}
	
	/**
	 * 
	 * 生成二维码规则,模式一生成支付二维码
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param WechatPaymentBizPayUrl $inputObj
	 * @param int $timeOut
	 * @throws WechatPaymentException
	 * @return 成功时返回，其他抛异常
	 */
	public static function bizpayurl($inputObj, $timeOut = 60,$WxCfg)
	{
		if(!$inputObj->IsProduct_idSet()){
			throw new WechatPaymentException("生成二维码，缺少必填参数product_id！");
		}
		
		$inputObj->SetAppid($WxCfg->getAPPID());//公众账号ID
		$inputObj->SetMch_id($WxCfg->getMCHID());//商户号
		$inputObj->SetTime_stamp(round(microtime(true) * 1000));//时间戳
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
		
		$inputObj->SetSign($WxCfg);//签名
		
		return $inputObj->GetValues();
	}

    /**
     *
     * 查询订单，WechatPaymentOrderQuery中out_trade_no、transaction_id至少填一个
     * appid、mchid、spbill_create_ip、nonce_str不需要填入
     * @param WechatPaymentOrderQuery $inputObj
     * @param int $timeOut
     * @throws WechatPaymentException
     * @return 成功时返回，其他抛异常
     */
    public static function orderQuery($inputObj, $WxCfg,$timeOut = 60)
    {
        $url = "https://www.omipay.com.au/omipay/api/v1/QueryOrder";
        //检测必填参数
        if(!$inputObj->IsOut_trade_noSet() && !$inputObj->IsTransaction_idSet()) {
            throw new WechatPaymentException("订单查询接口中，out_trade_no、transaction_id至少填一个！");
        }
        $inputObj->SetAppid($WxCfg->getAPPID());//公众账号ID
        $inputObj->SetMch_id($WxCfg->getMCHID());//商户号
        $inputObj->SetNonce_str(self::getNonceStr());//随机字符串

        $inputObj->SetSign($WxCfg);//签名
        $xml = $inputObj->ToXml();

        $startTimeStamp = self::getMillisecond();//请求开始时间
        $response = self::postXmlCurl($xml, $url, false, $timeOut,$WxCfg);
        $result = WechatPaymentResults::Init($response,$WxCfg);
        self::reportCostTime($url, $startTimeStamp, $result,$WxCfg);//上报请求花费时间

        return $result;
    }

	
	/**
	 * 
	 * 产生随机字符串，不长于32位
	 * @param int $length
	 * @return 产生的随机字符串
	 */
	public static function getNonceStr($length = 32) 
	{
		$chars = "abcdefghijklmnopqrstuvwxyz0123456789";  
		$str ="";
		for ( $i = 0; $i < $length; $i++ )  {  
			$str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);  
		} 
		return $str;
	}
	
	/**
	 * 直接输出xml
	 * @param string $xml
	 */
	public static function replyNotify($xml)
	{
		echo $xml;
	}
	
	/**
	 * 
	 * 上报数据， 上报的时候将屏蔽所有异常流程
	 * @param string $usrl
	 * @param int $startTimeStamp
	 * @param array $data
	 */
	private static function reportCostTime($url, $startTimeStamp, $data,$WxCfg)
	{
		//如果不需要上报数据
		if($WxCfg->getREPORTLEVENL() == 0){
			return;
		} 
		//如果仅失败上报
		if($WxCfg->getREPORTLEVENL() == 1 &&
			 array_key_exists("return_code", $data) &&
			 $data["return_code"] == "SUCCESS" &&
			 array_key_exists("result_code", $data) &&
			 $data["result_code"] == "SUCCESS")
		 {
		 	return;
		 }
		 
		//上报逻辑
		$endTimeStamp = self::getMillisecond();
		$objInput = new WechatPaymentReport();
		$objInput->SetInterface_url($url);
		$objInput->SetExecute_time_($endTimeStamp - $startTimeStamp);
		//返回状态码
		if(array_key_exists("return_code", $data)){
			$objInput->SetReturn_code($data["return_code"]);
		}
		//返回信息
		if(array_key_exists("return_msg", $data)){
			$objInput->SetReturn_msg($data["return_msg"]);
		}
		//业务结果
		if(array_key_exists("result_code", $data)){
			$objInput->SetResult_code($data["result_code"]);
		}
		//错误代码
		if(array_key_exists("err_code", $data)){
			$objInput->SetErr_code($data["err_code"]);
		}
		//错误代码描述
		if(array_key_exists("err_code_des", $data)){
			$objInput->SetErr_code_des($data["err_code_des"]);
		}
		//商户订单号
		if(array_key_exists("out_trade_no", $data)){
			$objInput->SetOut_trade_no($data["out_trade_no"]);
		}
		//设备号
		if(array_key_exists("device_info", $data)){
			$objInput->SetDevice_info($data["device_info"]);
		}
		
		try{
			self::report($objInput,1,$WxCfg);
		} catch (WechatPaymentException $e){
			//不做任何处理
		}
	}

	/**
	 * 以post方式提交xml到对应的接口url
	 * 
	 * @param string $xml  需要post的xml数据
	 * @param string $url  url
	 * @param bool $useCert 是否需要证书，默认不需要
	 * @param int $second   url执行超时时间，默认30s
	 * @param WechatPaymentConfig $WxCfg
	 * @throws WechatPaymentException
	 */
	private static function postXmlCurl($xml, $url, $useCert = false, $second = 60,$WxCfg)
	{
        $TZ_orig = date_default_timezone_get();

        #MAKE EST
        date_default_timezone_set('EST');

        #MAKEMILLISECOND
        $timestamp = round(microtime(true) * 1000);

        #REVERT TZ ORIG
        date_default_timezone_set($TZ_orig);

        $nonce_str = self::getNonceStr();

        $sign = self::gen_signature($timestamp, $WxCfg, $nonce_str);
        $verifying_sig = array(
            'm_number'  => $WxCfg->getMCHID(),
            'timestamp' => $timestamp,
            'nonce_str' => $nonce_str,
            'sign'      => $sign
        );

        $gateway_params = http_build_query(array_merge($verifying_sig,$xml));

        $gateway_request_url = $url.'?'.$gateway_params;

        $data = wp_remote_post( $gateway_request_url, array(
            'method'    => 'POST',
            'headers'   => array("Content-type" => "application/json;charset=UTF-8"),
            'timeout'   => 90,
            'sslverify' => false,
        ) );

        if ( is_wp_error( $data ) )
            throw new WechatPaymentException('There is issue for connecting payment gateway. Sorry for the inconvenience.');

        if ( empty( $data['body'] ) )
            throw new WechatPaymentException('OmiPay\'s Response was not getting any data.');

        // get body response while get not error

		//返回结果
		if($data){
			return $data;
		} else {
			throw new WechatPaymentException("curl出错，错误码:");
		}
	}
	
	
	/**
	 *
	 * 申请退款，WechatPaymentRefund中out_trade_no、transaction_id至少填一个且
	 * out_refund_no、total_fee、refund_fee、op_user_id为必填参数
	 * appid、mchid、spbill_create_ip、nonce_str不需要填入
	 * @param WechatPaymentRefund $inputObj
	 * @param int $timeOut
	 * @param WechatPaymentConfig $WxCfg
	 * @throws XH_Wx_Pay_Exception
	 * @return 成功时返回，其他抛异常
	 */
	public static function refund($inputObj, $timeOut = 60,$WxCfg)
	{
		$url = "https://api.mch.weixin.qq.com/secapi/pay/refund";
		//检测必填参数
		if(!$inputObj->IsOut_trade_noSet() && !$inputObj->IsTransaction_idSet()) {
			throw new XH_Wx_Pay_Exception("退款申请接口中，out_trade_no、transaction_id至少填一个！");
		}else if(!$inputObj->IsOut_refund_noSet()){
			throw new XH_Wx_Pay_Exception("退款申请接口中，缺少必填参数out_refund_no！");
		}else if(!$inputObj->IsTotal_feeSet()){
			throw new XH_Wx_Pay_Exception("退款申请接口中，缺少必填参数total_fee！");
		}else if(!$inputObj->IsRefund_feeSet()){
			throw new XH_Wx_Pay_Exception("退款申请接口中，缺少必填参数refund_fee！");
		}else if(!$inputObj->IsOp_user_idSet()){
			throw new XH_Wx_Pay_Exception("退款申请接口中，缺少必填参数op_user_id！");
		}
		$inputObj->SetAppid($WxCfg->getAPPID());//公众账号ID
		$inputObj->SetMch_id($WxCfg->getMCHID());//商户号
		$inputObj->SetNonce_str(self::getNonceStr());//随机字符串
	
		$inputObj->SetSign($WxCfg);//签名
		$xml = $inputObj->ToXml();
		$startTimeStamp = self::getMillisecond();//请求开始时间
		$response = self::postXmlCurl($xml, $url, true, $timeOut,$WxCfg);
		$result = WechatPaymentResults::Init($response,$WxCfg);
		self::reportCostTime($url, $startTimeStamp, $result,$WxCfg);//上报请求花费时间
	
		return $result;
	}
	
	
	/**
	 * 获取毫秒级别的时间戳
	 */
	private static function getMillisecond()
	{
		//获取毫秒的时间戳
		$time = explode ( " ", microtime () );
		$time = $time[1] . ($time[0] * 1000);
		$time2 = explode( ".", $time );
		$time = $time2[0];
		return $time;
	}

    public static function gen_signature($timestamp, $WxCfg, $nonce)
    {
        $gen_sig = strtoupper(md5("{$WxCfg->getMCHID()}&{$timestamp}&{$nonce}&{$WxCfg->getKEY()}") );
        return $gen_sig;
    }
}


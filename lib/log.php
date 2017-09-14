<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly

interface ILogHandler
{
	public function write($msg);
	
}

class CLogFileHandler implements ILogHandler
{
	private $handle = null;
	
	public function __construct($file = '')
	{
		try {
			$this->handle = @fopen($file,'a');
		} catch (Exception $e) {
		}
	}
	
	public function write($msg)
	{
		if($this->handle)
		fwrite($this->handle, $msg, 4096);
	}
	
	public function __destruct()
	{
		if($this->handle)
		fclose($this->handle);
	}
}

class Log
{
	private $handler = null;
	private $level = 15;
	
	private static $instance = null;
	
	private function __construct(){}

	private function __clone(){}
	
	public static function Init($handler = null,$level = 15)
	{
		if(!self::$instance instanceof self)
		{
			self::$instance = new self();
			self::$instance->__setHandle($handler);
			self::$instance->__setLevel($level);
		}
		return self::$instance;
	}
	
	
	private function __setHandle($handler){
		$this->handler = $handler;
	}
	
	private function __setLevel($level)
	{
		$this->level = $level;
	}
	
	public static function DEBUG($msg)
	{
		if(self::$instance)
		self::$instance->write(1, $msg);
	}
	
	public static function WARN($msg)
	{if(self::$instance)
		self::$instance->write(4, $msg);
	}
	
	public static function ERROR($msg)
	{if(!self::$instance){return;}
		$debugInfo = debug_backtrace();
		$stack = "[";
		foreach($debugInfo as $key => $val){
			if(array_key_exists("file", $val)){
				$stack .= ",file:" . $val["file"];
			}
			if(array_key_exists("line", $val)){
				$stack .= ",line:" . $val["line"];
			}
			if(array_key_exists("function", $val)){
				$stack .= ",function:" . $val["function"];
			}
		}
		$stack .= "]";
		self::$instance->write(8, $stack . $msg);
	}
	
	public static function INFO($msg)
	{if(!self::$instance){return;}
		self::$instance->write(2, $msg);
	}
	
	private function getLevelStr($level)
	{
		switch ($level)
		{
		case 1:
			return 'debug';
		break;
		case 2:
			return 'info';	
		break;
		case 4:
			return 'warn';
		break;
		case 8:
			return 'error';
		break;
		default:
				
		}
	}
	
	protected function write($level,$msg)
	{
        $WxCfg = $weChatOptions = get_option('woocommerce_wechatpay_settings');;
        if($WxCfg['WX_debug']){
            if(($level & $this->level) == $level )
            {
                $msg = '['.date('Y-m-d H:i:s').']['.$this->getLevelStr($level).'] '.$msg."\n";
                $this->handler->write($msg);
            }
        }


	}
}

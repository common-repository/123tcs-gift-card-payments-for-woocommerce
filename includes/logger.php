<?php
namespace TCS_GCP;

if(!defined( 'ABSPATH' )) exit; /* Don't allow direct access */

class Logger
{
	private static $instance = null;
	
	private $log_level_debug = false;
	private $use_WooCommerce_logger = false;
	private $logger = null;
	
	/* $context may hold arbitrary data.
	 * If provided a "source", it will be used to group the logs.
	 */
	private $context = array( 'source' => '123TCS-Gift-Card-Payments' );

	private function __construct()
	{
		$this->log_level_debug = false;
		$this->use_WooCommerce_logger = false;
		$this->logger = null;
	}
	
	public static function getInstance()
	{
		if(!self::$instance)
		{
			self::$instance = new \TCS_GCP\Logger();
		}

		return self::$instance;
	}
	
	public function set_log_level_debug()
	{
		$this->log_level_debug = true;
	}
	
	public function set_log_level_production()
	{
		$this->log_level_debug = false;
	}
	
	public function use_WooCommerce_logger()
	{
		$this->use_WooCommerce_logger = true;
		$this->logger = wc_get_logger();
	}
	
	public function debug($message)
	{
	    if($this->log_level_debug)
	    {
    		if($this->use_WooCommerce_logger)
    		{
    			$this->logger->debug($message, $this->context);
    		}
    	
    		$this->write_to_log('[DEBUG]   '.$message);
	    }
	}
	
	public function info($message)
	{
		if($this->use_WooCommerce_logger)
		{
			$this->logger->info($message, $this->context);
		}
		
		$this->write_to_log('[INFO]    '.$message);
	}
	
	public function warning($message)
	{
		if($this->use_WooCommerce_logger)
		{
			$this->logger->warning($message, $this->context);
		}
		
		$this->write_to_log('[WARNING] '.$message);
	}
	
	public function error($message)
	{
		if($this->use_WooCommerce_logger)
		{
			$this->logger->error($message, $this->context);
		}
		
		$this->write_to_log('[ERROR]   '.$message);
	}
	
	private function write_to_log($message)
	{
		error_log('[TCS-GCP] '.$message);
	}
}
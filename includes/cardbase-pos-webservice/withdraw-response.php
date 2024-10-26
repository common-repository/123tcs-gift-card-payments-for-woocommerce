<?php
namespace TCS_GCP;

if(!defined( 'ABSPATH' )) exit; /* Don't allow direct access */

require_once plugin_dir_path( __FILE__ ) . '../logger.php';

class WithdrawResponse
{
    private $logger = null;
    private $request_id;
    
    public function __construct( $withdrawSimpleXml )
    {
        $this->logger = \TCS_GCP\Logger::getInstance();
        
        $this->request_id = (string)$withdrawSimpleXml->Header->RequestID;
        
        $this->logger->debug('Withdraw request_id: '.$this->request_id);
    }

    public function get_request_id()
    {
        return $this->request_id;
    }
}
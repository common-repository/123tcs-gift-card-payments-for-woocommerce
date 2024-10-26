<?php
namespace TCS_GCP;

if(!defined( 'ABSPATH' )) exit; /* Don't allow direct access */

require_once plugin_dir_path( __FILE__ ) . '../logger.php';

class RollbackResponse
{
    private $logger = null;
    
    public function __construct( $withdrawSimpleXml )
    {
        $this->logger = \TCS_GCP\Logger::getInstance();
    }
}
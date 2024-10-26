<?php
namespace TCS_GCP;

if(!defined( 'ABSPATH' )) exit; /* Don't allow direct access */

require_once plugin_dir_path( __FILE__ ) . '../logger.php';

class CardInfoResponse
{
    private $logger = null;
    private $status;
    private $balance_string;

    public function __construct( $cardInfoSimpleXml )
    {
        $this->logger = \TCS_GCP\Logger::getInstance();
        
        $this->status = (string)$cardInfoSimpleXml->Body->CardInfoResponse->Card->Status;
        $this->balance_string = (string)$cardInfoSimpleXml->Body->CardInfoResponse->Card->Value->Amount;
        
        $this->logger->debug('Card status: '.$this->status);
        $this->logger->debug('Card balance: '.$this->balance_string);
    }
    
    public function is_active()
    {
        if("Active" == $this->status)
        {
            return true;
        } 
        else
        {
            $this->logger->warning( "CardInfo status: ".$this->status );
            return false;
        }
    }
    
    public function get_status()
    {
        return $this->status;
    }
    
    public function balance_as_euro_cent_string()
	{
	    return $this->balance_string;
	}
	
	public function balance_as_float()
	{
	    // Convert card value to same format as order value
        $balance_euro_string_two_decimals = substr_replace( $this->balance_string, '.', -2, 0 );
        
	    return (float)$balance_euro_string_two_decimals;
	}
}
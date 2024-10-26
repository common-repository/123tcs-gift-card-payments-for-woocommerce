<?php
namespace TCS_GCP;
use Exception;

if(!defined( 'ABSPATH' )) exit; /* Don't allow direct access */

require_once plugin_dir_path( __FILE__ ) . '../logger.php';
require_once plugin_dir_path( __FILE__ ) . '../settings.php';
require_once plugin_dir_path( __FILE__ ) . 'card-info-request.php';
require_once plugin_dir_path( __FILE__ ) . 'card-info-response.php';
require_once plugin_dir_path( __FILE__ ) . 'withdraw-request.php';
require_once plugin_dir_path( __FILE__ ) . 'withdraw-response.php';
require_once plugin_dir_path( __FILE__ ) . 'rollback-request.php';
require_once plugin_dir_path( __FILE__ ) . 'rollback-response.php';

class CardbasePosWebservice
{
    private $logger = null;
    private $endpoint;
    private $merchant_id;
    private $secret_key;
    
    private $header_status;
    private $header_result;
    private $header_requestID;
    private $header_remark;
    
    private $last_request_id;
    
    public function __construct()
    {
        $this->logger = \TCS_GCP\Logger::getInstance();
        
        $settings = new \TCS_GCP\Settings();
        
        // Get Merchant Id and Secret Key is for active mode
        $test_mode = $settings->is_testmode_active();
        $this->endpoint = $test_mode ? 'https://pos-test.123tcs.io/v4' : "https://pos.123tcs.io/v4";
        $this->merchant_id = $test_mode ? $settings->get_test_merchant_id() : $settings->get_live_merchant_id();
        $this->secret_key = $test_mode ? $settings->get_test_secret_key() : $settings->get_live_secret_key();
    }
    
    public function get_last_request_id()
    {
        return $this->last_request_id;
    }
    
    /**
     * When updating the settings page we want to validate the new values
     * and not the ones that are alreay in the database
     */
    public function force_set_credentials($merchant_id, $secret_key, $test_mode)
    {
        $this->merchant_id = $merchant_id;
        $this->secret_key = $secret_key;
        
        $this->endpoint = $test_mode ? 'https://pos-test.123tcs.io/v4' : "https://pos.123tcs.io/v4";
    }
    
    public function cardInfo_request($card_number, $card_verification_code)
    {
        $request = new \TCS_GCP\CardInfoRequest($card_number, $card_verification_code, $this->merchant_id, $this->secret_key);
        
        try
        {
            $simpleXml = $this->soapRequest($request);
        }
        catch(Exception $e)
        {
            $message = sprintf( esc_html__( 'Error for card number %s with verification code %s.', 'tcs-gift-card-payments-for-woocommerce' ), $card_number, $card_verification_code );
            
            $this->logger->error("CardInfo Request error");
            
            throw new Exception( $message.' '.$e->getMessage() );
        }
        
        return new \TCS_GCP\CardInfoResponse($simpleXml);
    }
    
    public function withdraw_request($card_number, $card_verification_code, $amount_to_withdraw_as_EuroCentsString, $receipt_number, $orderTotal_Amount_as_EuroCentsString)
	{
	    $this->logger->debug("Starting withdraw ".$amount_to_withdraw_as_EuroCentsString." eurocents for card: ".$card_number);
	    
		$request = new \TCS_GCP\WithdrawRequest($card_number, $card_verification_code, $this->merchant_id, $this->secret_key, $amount_to_withdraw_as_EuroCentsString, $receipt_number, $orderTotal_Amount_as_EuroCentsString);
		
		try
        {
            $simpleXml = $this->soapRequest($request);
        }
        catch(Exception $e)
        {
            $message = sprintf( esc_html__( 'Error for card number %s with verification code %s.', 'tcs-gift-card-payments-for-woocommerce' ), $card_number, $card_verification_code );
            
            $this->logger->error("Withdraw Request error");
            
            throw new Exception( $message.' '.$e->getMessage() );
        }
        
        return new \TCS_GCP\WithdrawResponse($simpleXml);
	}
	
	public function rollback_request($card_number, $card_verification_code, $original_request_id)
	{
	    $this->logger->info('Starting rollback for request: '. $original_request_id);
	    
	    $request = new \TCS_GCP\RollbackRequest($card_number, $card_verification_code, $this->merchant_id, $this->secret_key, $original_request_id);
	    
	    try
	    {
		    $simpleXml = $this->soapRequest($request);
	    }
	    catch(Exception $e)
	    {
	        $message = sprintf( esc_html__( 'Error for card number %s with verification code %s.', 'tcs-gift-card-payments-for-woocommerce' ), $card_number, $card_verification_code );
            
            $this->logger->error("Rollback Request error");
            
            throw new Exception( $message.' '.$e->getMessage() );
	    }
	    
	    return new \TCS_GCP\RollbackResponse($simpleXml);
	}
    
    /**
     * Do the SOAP request.
     * Returns a WP_Error or a simplexml object.
     */
    private function soapRequest($request)
    {
        $result = '';
        
        $this->logger->debug('Starting SOAP request');
        
        $url = $this->endpoint;
        
        $headers_args = array(
            'Content-type'      => 'application/soap+xml;charset=UTF-8;action="'.$request->get_action().'"',
            'Accept-Encoding'   => 'gzip,deflate',
            'Content-length'    => strlen( $request->get_xml() )
        );
        
        $args = array(
            'headers'   => $headers_args,
            'body'      => $request->get_xml()
            );
        
        try
        {
            // Send the request to 123CTS
            $response = wp_remote_post( $url, $args );
            
            if( is_wp_error( $response ))
            {
                $message = __('An error occurred in the request, please contact the webshop administator', 'tcs-gift-card-payments-for-woocommerce');
                
                $this->logger->error( 'Error for remote post: '.$response->get_error_message());
                
                throw new Exception( $message );
            }
            
            $response_code = wp_remote_retrieve_response_code( $response );
            if( $response_code != 200 )
            {
                $message = __('An error occurred in the request, please contact the webshop administator', 'tcs-gift-card-payments-for-woocommerce');
                
                $this->logger->error( 'Request error. Response code: '.$response_code );
                $this->logger->error( 'Request error. Response message: '.wp_remote_retrieve_response_message( $response ) );
                
                $this->logger->debug('http response: '.print_r($response, true));
                
                throw new Exception( $message );
            }
            
            $xml_string = wp_remote_retrieve_body( $response );
            
            $this->logger->debug('body: '.print_r($xml_string, true));
            
            // Converting
            $xml_string = str_replace("s:","",$xml_string); // Used in production environment response
            $xml_string = str_replace("h:","",$xml_string); // Used in production environment response
            $xml_string = str_replace("soap:","",$xml_string); // Used in test environment response
            $xml_string = str_replace("v4:","",$xml_string); // Used in test environment response
    
            // Converting to XML
            $result = simplexml_load_string($xml_string);
            
            // Check response status and result codes
            $this->header_status = $result->Header->Status;
            $this->header_result = $result->Header->Result;
            $this->header_requestID = $result->Header->RequestID;
            $this->header_remark = $result->Header->Remark;
            
            if(("0" != $this->header_status) || ("Success" != $this->header_result))
            {
                $this->logger->error('SOAP request response error:');
                $this->logger->error('status:    '.$this->header_status);
                $this->logger->error('result:    '.$this->header_result);
                $this->logger->error('requestID: '.$this->header_requestID);
                $this->logger->error('remark:    '.$this->header_remark);
                
                switch($this->header_status)
                {
                    case '10':
                        $message = __('Unknown error, please contact the webshop administator', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "An unknown error occurred";
                        break;
                    case '50':
                        $message = __('System error, please contact the webshop administator', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "System exception occurred";
                        break;
                    case '110':
                        $message = __('System error, please contact the webshop administator', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "No Merchant ID entered";
                        break;
                    case '120':
                        $message = __('Entered amount invalid', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Entered amount invalid";
                        break;
                    case '150':
                        $message = __('Card number not found', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Card number not found";
                        break;
                    case '160':
                        $message = __('Card status unknown', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Card status unknown";
                        break;
                    case '170':
                        $message = __('Card type unknown', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Card type unknown";
                        break;
                    case '210':
                        $message = __('Card status not valid', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Card status not valid";
                        break;
                    case '220':
                        $message = __('Card not activated', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Card not activated";
                        break;
                    case '221':
                        $message = __('Card already activated', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Card already activated";
                        break;
                    case '222':
                        $message = __('Card is deactivated', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Card is deactivated";
                        break;
                    case '230':
                        $message = __('Card suspended', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Card suspended";
                        break;
                    case '240':
                        $message = __('Card expired', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Card expired";
                        break;
                    case '243':
                        $message = __('Entered start date wrong', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Entered start date wrong";
                        break;
                    case '244':
                        $message = __('Card Startdate is too high', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Card Startdate is too high";
                        break;
                    case '245':
                        $message = __('Current date is lower than card startdate', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Current date is lower than card startdate";
                        break;
                    case '250':
                        $message = __('Card is registered', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Card is registered";
                        break;
                    case '310':
                        $message = __('EAN code not found', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "EAN code not found";
                        break;
                    case '300':
                        $message = __('No more numbers available', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "No more numbers available";
                        break;
                    case '311':
                        $message = __('Category not found', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Category not found";
                        break;
                    case '400':
                        $message = __('Entered amount exceeds the maximum allowable value', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Entered amount exceeds the maximum allowable value";
                        break;
                    case '410':
                        $message = __('Entered amount is less than the minimum allowable value', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Entered amount is less than the minimum allowable value";
                        break;
                    case '420':
                        $message = __('Wrong currency used', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Wrong currency used";
                        break;
                    case '430':
                        $message = __('Transactions limit reached', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Transactions limit reached";
                        break;
                    case '431':
                        $message = __('Daily transaction limit reached', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Daily transaction limit reached";
                        break;
                    case '432':
                        $message = __('Transaction not allowed today', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Transaction not allowed toda";
                        break;
                    case '433':
                        $message = __('Transaction not allowed rollback', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Transaction not allowed rollback";
                        break;
                    case '435':
                        $message = __('Transaction no longer allowed', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Transaction no longer allowed";
                        break;
                    case '436':
                        $message = __('Card already in use', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Card already in use";
                        break;
                    case '440':
                        $message = __('Maximum number of refills exceeded', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Maximum number of refills exceeded";
                        break;
                    case '441':
                        $message = __('Card activated elsewhere', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Card activated elsewhere";
                        break;
                    case '442':
                        $message = __('Transaction was already refunded', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Transaction was already refunded";
                        break;
                    case '443':
                        $message = __('Transaction reference not found', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Transaction reference not found";
                        break;
                    case '444':
                        $message = __('Amount is too high', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Amount is too high";
                        break;
                    case '445':
                        $message = __('Transaction requestID is not unique', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Transaction requestID is not unique";
                        break;
                    case '446':
                        $message = __('CustomerID is not equal', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "CustomerID is not equal";
                        break;
                    case '447':
                        $message = __('No card(s) found for this CustomerID', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "No card(s) found for this CustomerID";
                        break;
                    case '450':
                        $message = __('Current amount insufficient', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Current amount insufficient";
                        break;
                    case '460':
                        $message = __('Amount outside allowed range', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Amount outside allowed range";
                        break;
                    case '500':
                        $message = __('System error, please contact webshop administrator', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "System error, please contact webshop administrator";
                        break;
                    case '510':
                        $message = __('User not active', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "User not active";
                        break;
                    case '520':
                        $message = __('Not allowed to use this card', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Not allowed to use this card";
                        break;
                    case '550':
                        $message = __('Not allowed to use this card', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Not allowed to use this card";
                        break;
                    case '572':
                        $message = __('User is not allowed to do a rollback', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "User is not allowed to do a rollback";
                        break;
                    case '600':
                        $message = __('Internal failure, please contact webshop administrator', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Internal failure, please contact webshop administrator";
                        break;
                    case '610':
                        $message = __('Internal failure, please contact webshop administrator', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Internal failure, please contact webshop administrator";
                        break;
                    case '620':
                        $message = __('Request ID not found', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Request ID not found";
                        break;
                    case '630':
                        $message = __('Invalid request ID', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Invalid request ID";
                        break;
                    case '640':
                        $message = __('Request ID not correct', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Request ID not correct";
                        break;
                    case '650':
                        $message = __('Transaction already rolled back', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Transaction already rolled back";
                        break;
                    case '700':
                        $message = __('Request can not be validated, please contact webshop administrator', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "Request can not be validated, please contact webshop administrator";
                        break;
                    default:
                        $message = __('An unknown error occured, please contact webshop administrator', 'tcs-gift-card-payments-for-woocommerce');
                        $log_message = "An unknown error occured, please contact webshop administrator";
                }
                
                $this->logger->error( $log_message);
                
                throw new Exception( $message );
            }
        }
        catch(Exception $e)
        {
            $this->logger->info('EndPoint: '.$this->endpoint);
            $this->logger->info('Action: '.$request->get_action());
            $this->logger->info('Request: '.$request->get_xml());
        
            throw new Exception( $e->getMessage() );
        }
        finally
        {
            $this->last_request_id = $request->get_request_id();
        }
        
        return $result;
    }
    
    public function get_status()
    {
        return $this->header_status;
    }
    
    public function get_result()
    {
        return $this->header_result;
    }
}
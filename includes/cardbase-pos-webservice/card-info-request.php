<?php
namespace TCS_GCP;

if(!defined( 'ABSPATH' )) exit; /* Don't allow direct access */

class CardInfoRequest
{
    private $request_id;
    private $card_number;
    private $card_verification_code;
    private $merchant_id;
    private $hash;

    public function __construct($card_number, $card_verification_code, $merchant_id, $secret_key)
    {
        $this->card_number = $card_number;
        $this->card_verification_code = $card_verification_code;
        $this->merchant_id = $merchant_id;
   
        $this->request_id = uniqid('tcs_gcp_', true);
        
        // Create hash
        $string_to_hash = $merchant_id.$this->request_id.$card_number.$card_verification_code.$secret_key;
        $this->hash = sha1($string_to_hash);
    }
    
    public function get_request_id()
    {
        return $this->request_id;
    }
    
    public function get_action()
    {
        return "http://ws.tcs-cms.nl/wsdl/pointofsale/v4/IGiftcard/CardInfo";
    }
    
    public function get_xml()
    {
        ob_start();
        ?>
        <soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:v4="http://ws.tcs-cms.nl/wsdl/pointofsale/v4">
            <soap:Header>
                <v4:RequestID><?php echo $this->request_id ?></v4:RequestID>
                <v4:PointOfSale>
                    <v4:MerchantID><?php echo $this->merchant_id ?></v4:MerchantID>
                    <!--Optional:-->
                    <v4:DeviceID></v4:DeviceID>
                    <!--Optional:-->
                    <v4:OperatorID></v4:OperatorID>
                </v4:PointOfSale>
                <v4:Hash><?php echo $this->hash ?></v4:Hash>
            </soap:Header>
            <soap:Body>
                <v4:CardInfoRequest>
                    <v4:Card>
                        <v4:Number><?php echo $this->card_number ?></v4:Number>
                        <!--Optional:-->
                        <v4:VerificationCode><?php echo $this->card_verification_code ?></v4:VerificationCode>
                    </v4:Card>
                    <!--Optional:-->
                    <v4:CustomerID></v4:CustomerID>
                </v4:CardInfoRequest>
            </soap:Body>
        </soap:Envelope>
        <?php
        return ob_get_clean();
    }
}
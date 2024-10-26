<?php
namespace TCS_GCP;

if(!defined( 'ABSPATH' )) exit; /* Don't allow direct access */

class WithdrawRequest
{
    private $request_id;
    private $card_number;
    private $card_verification_code;
    private $merchant_id;
    private $hash;
    private $value_amount;
    private $value_currency;
    private $receipt_number;
    private $orderTotal_Amount;
    private $orderTotal_Currency;
    private $remark;
    private $transactionTag;

    public function __construct($card_number, $card_verification_code, $merchant_id, $secret_key, $amount_to_withdraw_as_EuroCentsString, $receipt_number, $orderTotal_Amount_as_EuroCentsString)
    {
        $this->card_number = $card_number;
        $this->card_verification_code = $card_verification_code;
        $this->merchant_id = $merchant_id;
   
        $this->request_id = uniqid('tcs_gcp_', true);
        
        $this->value_amount = $amount_to_withdraw_as_EuroCentsString;
        $this->value_currency = "EUR";
        $this->receipt_number = $receipt_number;
        $this->orderTotal_Amount = $orderTotal_Amount_as_EuroCentsString;
        $this->orderTotal_Currency = "EUR";
        $this->remark = "";
        $this->transactionTag = "";
   
        // Create hash
        $string_to_hash = $merchant_id.$this->request_id.$card_number.$card_verification_code.$this->value_currency.$this->value_amount.$this->receipt_number.$secret_key;
        $this->hash = sha1($string_to_hash);
    }
    
    public function get_request_id()
    {
        return $this->request_id;
    }
    
    public function get_action()
    {
        return "http://ws.tcs-cms.nl/wsdl/pointofsale/v4/IGiftcard/Withdraw";
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
                <v4:WithdrawRequest>
                    <v4:Card>
                        <v4:Number><?php echo $this->card_number ?></v4:Number>
                        <!--Optional:-->
                        <v4:VerificationCode><?php echo $this->card_verification_code ?></v4:VerificationCode>
                    </v4:Card>
                    <v4:Value>
                        <v4:Amount><?php echo $this->value_amount ?></v4:Amount>
                        <v4:Currency><?php echo $this->value_currency ?></v4:Currency>
                    </v4:Value>
                    <!--Optional:-->
                    <v4:CustomerID></v4:CustomerID>
                    <!--Optional:-->
                    <v4:Receiptnumber><?php echo $this->receipt_number ?></v4:Receiptnumber>
                    <!--Optional:-->
                    <v4:OrderTotal>
                        <v4:Amount><?php echo $this->orderTotal_Amount ?></v4:Amount>
                        <v4:Currency><?php echo $this->orderTotal_Currency ?></v4:Currency>
                    </v4:OrderTotal>
                    <!--Optional:-->
                    <v4:Products>
                        <!--Zero or more repetitions:-->
                        <v4:EAN></v4:EAN>
                    </v4:Products>
                    <!--Optional:-->
                    <v4:Categories>
                        <!--Zero or more repetitions:-->
                        <v4:Category></v4:Category>
                    </v4:Categories>
                    <!--Optional:-->
                    <v4:Remark><?php echo $this->remark ?></v4:Remark>
                    <!--Optional:-->
                    <v4:TransactionTag><?php echo $this->transactionTag ?></v4:TransactionTag>
                </v4:WithdrawRequest>
            </soap:Body>
        </soap:Envelope>
        <?php
        return ob_get_clean();
    }
}
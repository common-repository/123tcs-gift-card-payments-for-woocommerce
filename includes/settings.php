<?php
namespace TCS_GCP;
use Exception;

if(!defined( 'ABSPATH' )) exit; /* Don't allow direct access */

require_once plugin_dir_path( __FILE__ ) . 'logger.php';
require_once plugin_dir_path( __FILE__ ) . 'cardbase-pos-webservice/cardbase-pos-webservice.php';

class Settings
{
    private $logger = null;
    
    public function __construct()
    {
        $this->logger = \TCS_GCP\Logger::getInstance();
    }
    
    /**
     * Register and define our settings
     */
    public function init()
    {
        /* Define the setting args */
        $args = array(
            'type'              => 'string',
            'sanitize_callback' => [$this, 'validate'],
            'default'           => NULL
        );
        
        /* Register our settings */
        register_setting('TCS_GCP_plugin_options', 'TCS_GCP_plugin_options', $args);
    }
    
    /**
     * Creates an initial settings record in the database
     * To be called on activation of the plugin
     */
    public function create_settings()
    {
        add_option('TCS_GCP_plugin_options', array(
            'live_merchant_id' => '',
            'live_secret_key' => '',
            'is_testmode_active' => true,
            'test_merchant_id' => '',
            'test_secret_key' => '',
            'is_open_loop_giftcard' => true,
            'gift_card_name' => '123TCS Gift Card',
            'media_selector_attachment_id' => 0
        ));
    }
    
    /**
     * Deletes all settings from the database
     * To be called when uninstalling the plugin
     */
    public function delete_settings()
    {
        delete_option('TCS_GCP_plugin_options');
    }
    
    public function validate($input)
    {
        $this->logger->debug('Settings validate ');
        
        $valid = array();
        
        $valid['live_merchant_id'] = sanitize_text_field($input['live_merchant_id']);
        $valid['live_secret_key'] = sanitize_text_field($input['live_secret_key']);
        $valid['is_testmode_active'] = isset( $input['is_testmode_active'] ) && true == $input['is_testmode_active'] ? true : false;
        $valid['test_merchant_id'] = sanitize_text_field($input['test_merchant_id']);
        $valid['test_secret_key'] = sanitize_text_field($input['test_secret_key']);
        $valid['is_open_loop_giftcard'] = isset( $input['is_open_loop_giftcard'] ) && true == $input['is_open_loop_giftcard'] ? true : false;
        $valid['gift_card_name'] = sanitize_text_field($input['gift_card_name']);
        $valid['media_selector_attachment_id'] = sanitize_text_field($input['media_selector_attachment_id']);
        
        if(!empty($valid['live_secret_key']))
        {
            if(!preg_match('/[a-zA-Z0-9]{32,32}/', $valid['live_secret_key']))
            {
                add_settings_error(
                    'TCS_GCP_plugin_options',
                    esc_attr( 'settings_updated' ),
                    __('The Live Secret Key should consist of 32 alfanumerical characters without spaces.', 'tcs-gift-card-payments-for-woocommerce'),
                    'error'
                );
            }
        }
        
        if(!empty($valid['test_secret_key']))
        {
            if(!preg_match('/[a-zA-Z0-9]{32,32}/', $valid['test_secret_key']))
            {
                add_settings_error(
                    'TCS_GCP_plugin_options',
                    esc_attr( 'settings_updated' ),
                    __('The Test Secret Key should consist of 32 alfanumerical characters without spaces.', 'tcs-gift-card-payments-for-woocommerce'),
                    'error'
                );
            }
        }
        
        // Get Merchant Id and Secret Key is for active mode
        $test_mode = $valid['is_testmode_active'];
        $MerchantID = $test_mode ? $valid['test_merchant_id'] : $valid['live_merchant_id'];
        $SecretKey = $test_mode ? $valid['test_secret_key'] : $valid['live_secret_key'];
        $dummy_card_number = $test_mode ? '6051460004131299545' : '6051464390000017994';
        $dummy_card_verification_code = $test_mode ? '63236' : '18597';
        
        /* Check the validity of the Merchant Id and Secret key for the active mode when they are set */
        if( !empty($MerchantID) &&
            !empty($SecretKey) )
        {
            $webservice = new \TCS_GCP\CardbasePosWebservice();
            
            $webservice->force_set_credentials($MerchantID, $SecretKey, $test_mode);
            
            try
            {
                $card_info_response = $webservice->cardInfo_request($dummy_card_number, $dummy_card_verification_code);
                
                $prefix = $test_mode ? 'Test ' : 'Live ';
                
                add_settings_error(
                    'TCS_GCP_plugin_options',
                    esc_attr( 'settings_updated' ),
                    $prefix.__('Merchant Id and Secret Key successfully validated.', 'tcs-gift-card-payments-for-woocommerce'),
                    'success'
                );
            }
            catch(Exception $e)
            {
                $status = $webservice->get_status();
                
                if('500' == $status)
                {
                    $message = __('MerchantID not found', 'tcs-gift-card-payments-for-woocommerce');
                    $message = $test_mode ? 'Test '.$message : $message;
                }
                else if('700' == $status) /* Validation error */
                {
                    $message = __('Secret Key not valid', 'tcs-gift-card-payments-for-woocommerce');
                    $message = $test_mode ? 'Test '.$message : $message;
                }
                else
                {
                    $message = __('Not able to check credentials', 'tcs-gift-card-payments-for-woocommerce');
                }
                
                $this->logger->error('Validation error for credentials. '.$message);
            
                add_settings_error(
                    'TCS_GCP_plugin_options',
                    esc_attr( 'settings_updated' ),
                    '<strong>123TCS Gift Card Payments for WooCommerce</strong> '.$message,
                    'error'
                );
            }
        }
        
        return $valid;
    }
    
    public function notices()
    {
        settings_errors('TCS_GCP_plugin_options');
    }
    
    public function get_live_merchant_id()
    {
        $options = get_option( 'TCS_GCP_plugin_options' );
        
        return $options['live_merchant_id'];
    }
    
    public function get_live_merchant_id_field()
    {
        $value = $this->get_live_merchant_id();
        
        echo '<input id="live_merchant_id" name="TCS_GCP_plugin_options[live_merchant_id]" type="text" value="'.esc_attr($value).'">';
    }
    
    public function get_live_secret_key()
    {
        $options = get_option( 'TCS_GCP_plugin_options' );
        
        return $options['live_secret_key'];
    }
    
    public function get_live_secret_key_field()
    {
        $value = $this->get_live_secret_key();
        
        echo '<input id="live_secret_key" name="TCS_GCP_plugin_options[live_secret_key]" type="text" value="'.esc_attr($value).'">';
    }
    
    public function is_testmode_active()
    {
        $options = get_option( 'TCS_GCP_plugin_options' );
        
        return $options['is_testmode_active'];
    }
    
    public function get_activate_test_mode_field()
    {
        $value = $this->is_testmode_active();
        
        $html = sprintf('<input id="testmode_active" name="TCS_GCP_plugin_options[is_testmode_active]" value="1"'.checked(1, $value, false). 
            'type="checkbox"><p>%s</p>', __('Activate the test mode if you want to test the plugin without using real payments.', 'tcs-gift-card-payments-for-woocommerce'));
        echo $html;
    }
    
    public function get_test_merchant_id()
    {
        $options = get_option( 'TCS_GCP_plugin_options' );
        
        return $options['test_merchant_id'];
    }
    
    public function get_test_merchant_id_field()
    {
        $value = $this->get_test_merchant_id();
        
        echo '<input id="test_merchant_id" name="TCS_GCP_plugin_options[test_merchant_id]" type="text" value="'.esc_attr($value).'">';
    }
    
    public function get_test_secret_key()
    {
        $options = get_option( 'TCS_GCP_plugin_options' );
        
        return $options['test_secret_key'];
    }
    
    public function get_test_secret_key_field()
    {
        $value = $this->get_test_secret_key();
        
        echo '<input id="test_secret_key" name="TCS_GCP_plugin_options[test_secret_key]" type="text" value="'.esc_attr($value).'">';
    }
    
    public function is_open_loop_giftcard()
    {
        $options = get_option( 'TCS_GCP_plugin_options', true );
        
        return $options['is_open_loop_giftcard'];
    }
    
    public function get_open_loop_giftcard_field()
    {
        $value = $this->is_open_loop_giftcard();
        
        $html = sprintf('<input id="open_loop_giftcard" name="TCS_GCP_plugin_options[is_open_loop_giftcard]" value="1"'.checked(1, $value, false). 
            'type="checkbox"><p>%s</p>', __('Check this box when your Gift Card is an open loop card. This limits the maximum amount to withdraw from a single gift card to €50,- to comply with regulations.', 'tcs-gift-card-payments-for-woocommerce'));
        echo $html;
    }
    
    public function get_gift_card_name()
    {
        $options = get_option( 'TCS_GCP_plugin_options' );
        
        return $options['gift_card_name'];
    }
    
    public function get_gift_card_name_field()
    {
        $value = $this->get_gift_card_name();
        
        echo '<input id="gift_card_name" name="TCS_GCP_plugin_options[gift_card_name]" type="text" value="'.esc_attr($value).'">';
    }
    
    public function get_gift_card_logo()
    {
        $options = get_option( 'TCS_GCP_plugin_options' );
        
        return $options['media_selector_attachment_id'];
    }
    
    function media_selector_settings_page_callback()
    {
        wp_enqueue_media();
        
        $value = $this->get_gift_card_logo();
        
        if(intval($value) > 0)
        {
            $image = wp_get_attachment_url( $value );
        }
        else
        {
            $image = plugin_dir_url( __FILE__ ) . '../assets/images/logo-123tcs-white.png';
        }
        
    	?>
    	<div class='image-preview-wrapper'>
    		<img id='image-preview' src='<?php echo $image; ?>' width='100' height='75' style='max-height: 75px; width: 100px;'>
    		<p></p><?php _e('Use aspect ratio 4 : 3', 'tcs-gift-card-payments-for-woocommerce'); ?></p>
    	</div>
    	<input id="upload_image_button" type="button" class="button" value="<?php _e( 'Upload image' ); ?>" />
    	<input type='hidden' name='TCS_GCP_plugin_options[media_selector_attachment_id]' id='image_attachment_id' value=''>
    	<?php
    }
}
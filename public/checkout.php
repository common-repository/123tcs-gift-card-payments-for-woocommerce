<?php
namespace TCS_GCP;
use Exception;

if(!defined( 'ABSPATH' )) exit; /* Don't allow direct access */

require_once plugin_dir_path( __FILE__ ) . '../includes/logger.php';
require_once plugin_dir_path( __FILE__ ) . '../includes/settings.php';
require_once plugin_dir_path( __FILE__ ) . '../includes/cardbase-pos-webservice/cardbase-pos-webservice.php';

class Checkout
{
    private $logger = null;
    
	public function __construct()
	{
		$this->logger = \TCS_GCP\Logger::getInstance();
	}
	
	public function init()
    {
        $settings_okay = false;
        
        $settings = new \TCS_GCP\Settings();
        
        // Check if Merchant Id and Secret Key is set for active mode
        $test_mode = $settings->is_testmode_active();
        if($test_mode &&
            !empty( $settings->get_test_merchant_id() ) &&
            !empty( $settings->get_test_secret_key() ) )
        {
            $settings_okay = true;
        }
        else if( !$test_mode &&
                !empty( $settings->get_live_merchant_id() ) &&
                !empty( $settings->get_live_secret_key() ) )
        {
            $settings_okay = true;
        }
        	
        if($settings_okay)
        {
            $this->register_scripts_and_styles();
    	    add_action('wp_enqueue_scripts', [$this, 'conditionally_enqueue_scripts_and_styles']);
    	    
            // Display Gift Card entry fields at checkout
    	    add_action('woocommerce_review_order_before_payment', [$this, 'get_html_for_giftcard_entry']);
    	    
    	    // Process AJAX update_checkout to add gift card
    	    add_action('woocommerce_checkout_update_order_review', [$this, 'add_gift_card_to_session'], 10, 1);
    	    
    	    // To subtract the Gift Cards from the total
    	    add_filter( 'woocommerce_calculated_total', [$this, 'filter_calculated_total'], 10, 2 );
    	    
    	    // Display gift cards after they are added
    	    add_action('woocommerce_cart_totals_before_order_total', [$this, 'added_gift_cards_html']);
    	    add_action('woocommerce_review_order_before_order_total', [$this, 'added_gift_cards_html']);
    	    
    		// Hook into WooCommerce process_checkout
    		add_action('woocommerce_checkout_order_processed', [$this, 'checkout_order_process_gift_cards'], 10, 3);
    		
    		// Hook into wp_enqueue_scripts as a place to determine if we're on the order-pay endpoint payment page
            add_action('wp_enqueue_scripts', [$this, 'handle_on_order_pay_page']);
            add_action('woocommerce_checkout_after_terms_and_conditions', [$this, 'show_available_gift_cards_on_order_pay_endpoint']);
            
            // Hook into the pay action on the pay page
            add_action('woocommerce_before_pay_action', [$this, 'before_pay_action'], 10, 1);
    		
    		// Remove gift cards from session
    		add_action('woocommerce_cart_emptied', [$this, 'clear_session']);
    		
    		// Display Gift Card details on the Thank You page
    		add_action( 'woocommerce_order_details_after_order_table', [$this, 'thank_you_page_show_gift_card_details_after_order_table'] );
    		
    		// Rollback on status changed to failed or canceled
    		add_action('woocommerce_order_status_failed', [$this, 'order_status_failed'], 10, 2);
    		add_action('woocommerce_order_status_cancelled', [$this, 'order_status_cancelled'], 10, 2);
    		
    		// Log status changes for debug purposes
    		add_action('woocommerce_order_status_changed', [$this, 'order_status_changed'], 10, 4);
    		add_action('woocommerce_order_status_completed', [$this, 'order_status_completed', 10, 2]);
    		add_action('woocommerce_payment_complete', [$this, 'payment_complete'], 10, 1);
    		add_filter('woocommerce_order_needs_payment', [$this, 'order_needs_payment'], 10, 2);
        }
    }
    
    public function admin_init()
    {
        // Register AJAX
	    add_action( 'wp_ajax_remove_gift_card', [$this, 'remove_gift_card_from_session'] );
	    add_action( 'wp_ajax_nopriv_remove_gift_card', [$this, 'remove_gift_card_from_session'] );
    }
    
    public function register_scripts_and_styles()
	{
        wp_register_script( 
	        'tcs_gcp_checkout_script',                                  // Unique name
	        plugin_dir_url( __FILE__ ) . 'js/checkout.js',              // URL to the file
	        array(),                                                    // Dependencies
	        filemtime(plugin_dir_path( __FILE__ ) . 'js/checkout.js'),  // Version
	        true );                                                     // Output in footer
	        
        wp_register_style( 
	        'tcs_gcp_checkout_style',                                   // Unique name
	        plugin_dir_url( __FILE__ ) . 'css/checkout.css',            // URL to the file
	        array(),                                                    // Dependencies
	        filemtime(plugin_dir_path( __FILE__ ) . 'css/checkout.css') // Version
	    );
	}
	
	public function conditionally_enqueue_scripts_and_styles()
	{
	    if(is_checkout())
        {
            wp_enqueue_script('tcs_gcp_checkout_script');
            
            $result = wp_localize_script('tcs_gcp_checkout_script', 'checkout_ajax_object',
                array(
                    'ajaxurl'           => admin_url( 'admin-ajax.php' ),
                    'nonce'             => wp_create_nonce( "gift_card_nonce" ),
                    'processing_text'   => __('Processing...', 'tcs-gift-card-payments-for-woocommerce')
                )
            );
	    
    	    if($result == false)
    	    {
    	        $this->logger->error('Checkout failed to localize script');
    	    }
    	    
    	    wp_enqueue_style('tcs_gcp_checkout_style');
        }
	}
	
	public function get_html_for_giftcard_entry()
	{
        include plugin_dir_path( __FILE__ ) . 'templates/gift-card-entry.php';
	}
	
	/**
	 * The Add Gift Card button in the front end triggers the WooCommerce script
	 * update_checkout which sends all form data to the backend using AJAX.
	 * This function hooks into woocommerce_checkout_update_order_review to check
	 * if the fields to add a gift card are set. If both fields are set and not empty
	 * we start checking for a valid giftcard.
	 */
	public function add_gift_card_to_session( $posted_data)
    {
        $this->logger->debug('Updating order review');
        
        // Parsing posted data on checkout
        $post = array();
        $vars = explode('&', $posted_data);
        foreach ($vars as $k => $value){
            $v = explode('=', urldecode($value));
            $post[$v[0]] = $v[1];
        }
        
        /* If the fields are not set or both fields are empty it's not
         *  
         */
        if( !isset( $post['tcs-gcp-gift-card-number'] ) ) return;
        
        if( !isset( $post['tcs-gcp-gift-card-validation-code'] ) ) return;
        
        if( empty( $post['tcs-gcp-gift-card-number'] ) &&
            empty( $post['tcs-gcp-gift-card-validation-code'] ) ) return;
            
        $this->logger->debug('AJAX request received to add gift card');
            
        // Sanitize the data
        $card_number = sanitize_text_field($post['tcs-gcp-gift-card-number']);
        $card_validation_code = sanitize_text_field($post['tcs-gcp-gift-card-validation-code']);
        
        $this->logger->debug('Checking card number: '.$card_number.' with validation code: '.$card_validation_code);
        
        try
        {
            if( empty( $card_number ) )
            {
                $this->logger->error('Card number empty');
                throw new Exception( __('Card number should not be empty.', 'tcs-gift-card-payments-for-woocommerce') );
            }
            
            if( empty( $card_validation_code ) )
            {
                $this->logger->error('Card validation code empty');
                $message = sprintf( esc_html__( 'No validation code entered for card %s', 'tcs-gift-card-payments-for-woocommerce' ), $card_number );
                throw new Exception( $message );
            }
            
            // Check the format
            if(!preg_match('/[0-9]{19,19}/', $card_number)) {
                $this->logger->error( 'Card not 19 numbers' );
                $message = sprintf( esc_html__( 'The card number should consist of 19 numbers. You entered card number %s with validation code: %s', 'tcs-gift-card-payments-for-woocommerce' ), $card_number, $card_validation_code );
                throw new Exception( $message );
            }
            
            if(!preg_match('/[0-9]{5,5}/', $card_validation_code)) {
                $this->logger->error('Verification code not 5 numbers');
                $message = sprintf( esc_html__( 'The verification code should consist of 5 numbers. You entered card number %s with validation code: %s', 'tcs-gift-card-payments-for-woocommerce' ), $card_number, $card_validation_code );
                throw new Exception( $message );
            }
            
            $webservice = new \TCS_GCP\CardbasePosWebservice();
            
            // Get information about the Gift Card by sending a CardInfo request to 123TCS
            $card_info = $webservice->cardInfo_request($card_number, $card_validation_code);
            
            if( !$card_info->is_active() )
            {
                $this->logger->error('Card is not active, but has status: '.$card_info->get_status());
                $message = sprintf( esc_html__('Gift Card %s with validation code %s cannot be used it has the status %s.', 'tcs-gift-card-payments-for-woocommerce'), $card_number, $card_validation_code, $card_info->get_status() );
                throw new Exception( $message );
            }
            
            if( 0 == $card_info->balance_as_float())
            {
                $this->logger->error('Card has no balance');
                $message = sprintf( esc_html__('Gift Card %s with validation code %s has no balance.', 'tcs-gift-card-payments-for-woocommerce'), $card_number, $card_validation_code );
                throw new Exception( $message );
            }
            
            // Get already added Gift Cards from session
            $gift_cards = WC()->session->get( 'tcs_gcp_gift_cards' );
            
            // The first time create an array that can be used to store the session data
            if( empty($gift_cards) )
            {
                $gift_cards = array();
            }
            
            // Add the new Gift Card to the session
            $gift_cards[$card_number] = array(
                'card_validation_code'  => $card_validation_code,
                'balance'               => $card_info->balance_as_float(),
                'status'                => $card_info->get_status()
            );
            
            $this->logger->debug('Gift Card(s) in session: '.print_r($gift_cards, true));
            
            WC()->session->set( 'tcs_gcp_gift_cards' , $gift_cards );
        }
        catch(Exception $e)
        {
            wc_add_notice( $e->getMessage(), 'error' );
        }
    }
    
    /**
     * Remove the gift card from the session.
     * Called by AJAX request
     */
    public function remove_gift_card_from_session()
    {
        $this->logger->debug('AJAX request received to remove gift card');
        
        $okay_to_continue = true;
        
        // Check if the nonce is okay
        $result = check_ajax_referer( 'gift_card_nonce', 'nonce' );
        
        if(false == $result)
        {
            $this->logger->error('Nonce invalid');
            $okay_to_continue = false;
            $message = __('Request could not be validated, please refresh the page and try again', 'tcs-gift-card-payments-for-woocommerce');
            wc_add_notice( $message, 'error' );
        }
        
        if( $okay_to_continue )
        {
            // Check if data is set
            if( !isset($_POST['card_number']) ||
                empty($_POST['card_number']) )
            {
                $this->logger->error('Can not remove card because number is empty');
                $okay_to_continue = false;
                $message = __('Unable to remove card', 'tcs-gift-card-payments-for-woocommerce');
                wc_add_notice( $message, 'error' );
            }
        }
        
        if( $okay_to_continue )
        {
            // Get Gift Cards from session
            $gift_cards = WC()->session->get( 'tcs_gcp_gift_cards' );
            
            $this->logger->debug('Gift Card(s) in session: '.print_r($gift_cards, true));
            
            // Get the gift card number from the AJAX data and sanitize the data
            $card_number = sanitize_text_field($_POST['card_number']);
            
            $this->logger->info( 'Card number: '.$card_number);
            
            if(!empty($gift_cards))
            {
                foreach($gift_cards as $giftcard_number => $giftcard_data)
                {
                    if( $card_number == $giftcard_number )
                    {
                        $this->logger->info( 'Removing gift card '.$giftcard_number.' from session');
                        
                        // Unset the giftcard
                        unset($gift_cards[$giftcard_number]);
                        
                        // Update the session
                        WC()->session->set( 'tcs_gcp_gift_cards' , $gift_cards );
                        
                        break;
                    }
                }
            }
        }
        
        wp_die(); // This is required to terminate immediately and return a proper response
    }
    
    /**
     * Subtract the gift card(s) from the total
     */
    public function filter_calculated_total( $total, $cart )
    {
        $this->logger->debug('Calculating totals');
        $this->logger->debug('Total before: '.round( $total, $cart->dp ));
        
        // Store the original total in the session for later use
        WC()->session->set( 'tcs_gcp_original_total' , $total );
        
        // Get Gift Cards from session
        $gift_cards = WC()->session->get( 'tcs_gcp_gift_cards' );
        
        if(!empty($gift_cards))
        {
            $webservice = new \TCS_GCP\CardbasePosWebservice();
            
            foreach($gift_cards as $giftcard_number => $giftcard_data)
            {
                try
                {
                    // Always use the latest Gift Card details, so do a CardInfo request to 123TCS
                    $card_info = $webservice->cardInfo_request($giftcard_number, $giftcard_data['card_validation_code']);
                    
                    $giftcard_data['balance'] = $card_info->balance_as_float();
                    $giftcard_data['status'] = $card_info->get_status();
                    
                    $max_amount_to_withdraw_from_card = $this->get_max_amount_to_withdraw_from_card( $giftcard_data['balance'] );
                
                    if($total > $max_amount_to_withdraw_from_card)
                    {
                        $giftcard_data['withdraw'] = $max_amount_to_withdraw_from_card;
                    }
                    else
                    {
                        $giftcard_data['withdraw'] = $total;
                    }
                    
                    $total = $total - $giftcard_data['withdraw'];
                    
                    $gift_cards[$giftcard_number] = $giftcard_data;
                }
                catch(Exception $e)
                {
                    unset($gift_cards[$giftcard_number]);
                    $this->logger->error('Exception in calculating totals: '.$e->getMessage());
                    $this->logger->error('Removed Gift Card: '.$giftcard_number);
                }
            }
        }
        
        $this->logger->debug('Gift Card(s) in session: '.print_r($gift_cards, true));
        
        WC()->session->set( 'tcs_gcp_gift_cards' , $gift_cards );
        
        $this->logger->debug('Total remaining: '.round( $total, $cart->dp ));
        
        return round( $total, $cart->dp );
    }
    
    /**
     * NOTE: The do_action('woocommerce_review_order_before_order_total') is called in the middle of a table in the template and 
     * expects a table row to be echoed by the add_action hook. If you just echo text or, as in the question, an input, it falls 
     * outside the table, appears before not after the table and presumably gets left behind on ajax updates so appears more than 
     * once. So, something like the following will work in the add_action hook (the table has 2 columns):
     */
    public function added_gift_cards_html()
    {
        $this->logger->debug('Outputting html for gift card(s) in order review');
        
        // Get Gift Cards from session
        $gift_cards = WC()->session->get( 'tcs_gcp_gift_cards' );
        
        if(!empty($gift_cards))
        {
            $settings = new \TCS_GCP\Settings();
            
            foreach($gift_cards as $giftcard_number => $giftcard_data)
            {
                ?>
                <tr class="tcs-gcp-gift-card">
                    <th><?php echo $settings->get_gift_card_name().' '.wc_price($giftcard_data['balance']); ?><br>
                    <?php echo $giftcard_number; ?></th>
                    <td>-<?php echo wc_price( $giftcard_data['withdraw']).' '; ?>
                        <small class="tcs-gcp-remove-gift-card-button tcs-gcp-clickable" data-card_number="<?php echo $giftcard_number ?>"><?php _e('(remove card)', 'tcs-gift-card-payments-for-woocommerce'); ?></small>
                    </td>
                </tr>
                <?php
            }
        }
    }
    
    /**
     * Hooked into checkout_order_processed
     */
    public function checkout_order_process_gift_cards($order_id, $posted_data, $order)
    {
        $this->logger->debug('Starting checkout for gift card(s)');
        
        // Store the original total in the order meta for later use
        $original_total = WC()->session->get( 'tcs_gcp_original_total' );
        $order->update_meta_data( 'tcs_gcp_original_total', $original_total );
        $order->save();
        
        // Get Gift Cards from session
        $gift_cards = WC()->session->get( 'tcs_gcp_gift_cards' );
        
        if( !empty( $gift_cards ) )
        {
            try
            {
               $this->withdraw_gift_cards($order, $gift_cards);
            }
            catch(Exception $e)
            {
                // Retrow exception so it can be caught by WooCommerce process_checkout()
                throw new Exception( $e->getMessage() );
            }
        }
    }
    
    /** Hook into wp_enqueue_scripts as a place to determine if 
     * we're on the order-pay endpoint payment page
     */
    public function handle_on_order_pay_page()
    {
        if( !is_wc_endpoint_url( 'order-pay' ) ) return;
        
        $this->logger->debug("On the payment page (endpoint: order-pay)");

        global $wp;
        
        $order_id = $wp->query_vars['order-pay'];
        $order = wc_get_order( $order_id );
        
        if( $order )
        {
            $this->rollback_for_order( $order );
            
            // Get the original total in from the order meta
            $original_total = $order->get_meta( 'tcs_gcp_original_total' );
            
            $order->set_total( $original_total );
            $order->save();
        }
    }
    
    /** Determine if we're on the order-pay endpoint payment page
     * and show available gift cards
     */
    public function show_available_gift_cards_on_order_pay_endpoint()
    {
        if( !is_wc_endpoint_url( 'order-pay' ) ) return;
        
        $this->logger->debug('Outputting html on the payment page (endpoint: order-pay)');

        global $wp;
        
        $order_id = $wp->query_vars['order-pay'];
        $order = wc_get_order( $order_id );
        
        if( $order )
        {
            // Check if there are Gift Cards in the order
            $gift_cards = $order->get_meta( 'tcs_gcp_gift_cards' );
            
            if( !empty($gift_cards) )
            {
                $this->logger->debug('Order has the following gift card(s): '.print_r($gift_cards, true));
                
                if(!empty($gift_cards))
                {
                    $settings = new \TCS_GCP\Settings();
                    
                    $total = $order->get_total();
                    
                    ?>
                    <h2 class="woocommerce-order-details__title"><?php printf( __('The following %s gift card(s) will be subtracted from the total on payment:', 'tcs-gift-card-payments-for-woocommerce'), $settings->get_gift_card_name() ); ?></h2>
                    <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
                        <thead>
                            <tr>
                                <th class="woocommerce-table__product-name product-name"><?php _e('Card number', 'tcs-gift-card-payments-for-woocommerce'); ?></th>
                                <th class="woocommerce-table__product-table product-total"><?php _e('Amount', 'tcs-gift-card-payments-for-woocommerce'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                    <?php
                    foreach($gift_cards as $giftcard_number => $giftcard_data)
                    {
                        $max_amount_to_withdraw_from_card = $this->get_max_amount_to_withdraw_from_card( $giftcard_data['balance'] );
                        
                        if($total > $max_amount_to_withdraw_from_card)
                        {
                            $giftcard_data['withdraw'] = $max_amount_to_withdraw_from_card;
                        }
                        else
                        {
                            $giftcard_data['withdraw'] = $total;
                        }
                        
                        $total = $total - $giftcard_data['withdraw'];
                    
                        ?>
                        <tr class="tcs-gcp-gift-card">
                            <th><?php echo $giftcard_number; ?></th>
                            <td>-<?php echo wc_price( $giftcard_data['withdraw']); ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th class="woocommerce-table__product-name product-name"><?php _e('Remaining amount', 'tcs-gift-card-payments-for-woocommerce'); ?></th>
                                <th class="woocommerce-table__product-table product-total"><?php echo wc_price( $total ) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                    <?php
                }
            }
        }
    }
    
    /**
     * This function is triggered when a payment is started from the payment
     * page (order-pay endpoint).
     */
    public function before_pay_action( $order)
    {
        $this->logger->debug('Starting gift card payment from payment page (endpoint: order-pay)');
        
        // Check if there are Gift Cards in the order
        $gift_cards = $order->get_meta( 'tcs_gcp_gift_cards' );
        
        if( !empty($gift_cards) )
        {
            $this->logger->debug('Order has the following gift card(s): '.print_r($gift_cards, true));
            
            // Again withdraw all available gift cards
            try
            {
               $this->withdraw_gift_cards($order, $gift_cards);
            }
            catch(Exception $e)
            {
                // Retrow exception so it can be caught by WooCommerce process_checkout()
                throw new Exception( $e->getMessage() );
            }
            
            // Get the original total in from the order meta
            $total = $order->get_meta( 'tcs_gcp_original_total' );
            
            // Subtract the gift cards from the total for the remaining payment
            foreach($gift_cards as $giftcard_number => $giftcard_data)
            {
                $max_amount_to_withdraw_from_card = $this->get_max_amount_to_withdraw_from_card( $giftcard_data['balance'] );
                
                if($total > $max_amount_to_withdraw_from_card)
                {
                    $giftcard_data['withdraw'] = $max_amount_to_withdraw_from_card;
                }
                else
                {
                    $giftcard_data['withdraw'] = $total;
                }
                
                $total = $total - $giftcard_data['withdraw'];
            }
            
            $order->set_total( $total );
            $order->save();
        }
    }
    
    /* Remove Gift Card data from session
     * We have it now available in the order data in the database
     */
    public function clear_session()
    {
        $this->logger->debug('Clearing gift cards from session');
        WC()->session->__unset( 'tcs_gcp_gift_cards' );
        WC()->session->__unset( 'tcs_gcp_original_total' );
    }
    
    public function thank_you_page_show_gift_card_details_after_order_table( $order )
    {
        $this->logger->debug('Outputting html for gift cards on the thank you page');
        
        $gift_cards = $order->get_meta( 'tcs_gcp_gift_cards' );
        
        if( !empty($gift_cards) )
        {
            $settings = new \TCS_GCP\Settings();
            ?>
            <h2 class="woocommerce-order-details__title"><?php echo $settings->get_gift_card_name(); ?></h2>
            <table class="woocommerce-table woocommerce-table--order-details shop_table order_details">
                <thead>
                    <tr>
                        <th class="woocommerce-table__product-name product-name"><?php _e('Card number', 'tcs-gift-card-payments-for-woocommerce'); ?></th>
                        <th class="woocommerce-table__product-table product-total"><?php _e('Amount withdrawn', 'tcs-gift-card-payments-for-woocommerce'); ?></th>
                    </tr>
                </thead>
                <tbody>
            <?php
            foreach($gift_cards as $giftcard_number => $giftcard_data)
            {
                ?>
                <tr class="woocommerce-table__line-item order_item">
                    <td class="woocommerce-table__product-name product-name"><?php echo $giftcard_number; ?></td>
                    <td class="woocommerce-table__product-total product-total"><?php echo wc_price( isset($giftcard_data['paid']) ? $giftcard_data['paid'] : 0 ); ?></td>
                </tr>
                <?php
            }
            ?>
                </tbody>
            </table>
            <?php
        }
    }
    
    private function withdraw_gift_cards($order, $giftcards)
    {
        $this->logger->debug('Starting withdraw for following gift card(s): '.print_r($giftcards, true));
            
        $webservice = new \TCS_GCP\CardbasePosWebservice();
        
        try
        {
            // Do withdraw for each available Gift Card in the checkout
            foreach($giftcards as $giftcard_number => $giftcard_data)
            {
                if( 0 < $giftcard_data['withdraw'] )
                {
                    $withdraw = (string)number_format ($giftcard_data['withdraw'], 2, ".", "");
                    $amount_to_withdraw_as_EuroCentsString = str_replace( ".","",$withdraw ); // Convert to euroCentsString
                    $orderTotal_Amount_as_EuroCentsString = str_replace( ".", "", $order->get_meta( 'tcs_gcp_original_total' ) ); // Convert to euroCentsString
                    
                    $withdraw = $webservice->withdraw_request( $giftcard_number, $giftcard_data['card_validation_code'], $amount_to_withdraw_as_EuroCentsString, $order->get_id(), $orderTotal_Amount_as_EuroCentsString );
                    
                    // Store information needed for rollback
                    $giftcards[$giftcard_number]['withdraw_request_id'] = $withdraw->get_request_id();
                    
                    // Update balance and set amount that is withdrawn
                    $giftcards[$giftcard_number]['balance'] = $giftcard_data['balance'] - $giftcard_data['withdraw'];
                    $giftcards[$giftcard_number]['paid'] = $giftcard_data['withdraw'];
                    
                    // Add order note
                    $note = sprintf( esc_html__('Paid %s with Gift Card %s', 'tcs-gift-card-payments-for-woocommerce'), wc_price($giftcard_data['withdraw']), $giftcard_number );
                    $order->add_order_note( $note );
                }
            }
        }
        catch(Exception $e)
        {
            $error_message = $e->getMessage();
            
            // Log the error
            $this->logger->error('Withdraw error: '.$error_message);
            $this->logger->error('Last request id before catching error: '.$webservice->get_last_request_id());
            
            // Add order notice
            $order->add_order_note( $error_message );
            
            $this->rollback($giftcards, $order);
            
            // Retrow exception so it can be caught by WooCommerce process_checkout()
            throw new Exception( $e->getMessage() );
        }
        finally
        {
            // update the session data
            WC()->session->set( 'tcs_gcp_gift_cards' , $giftcards );
            
            // Add / update order meta
            $order->update_meta_data( 'tcs_gcp_gift_cards', $giftcards );
            $order->save();

            $this->logger->debug('Order gift card meta after withdraw: '.print_r($order->get_meta('tcs_gcp_gift_cards'), true));
        }
    }
    
    /**
     * Perform rollback
     */
    private function rollback($gift_cards, $order)
    {
        $this->logger->info('Starting rollback for gift cards: '.print_r($gift_cards, true));
        
        $webservice = new \TCS_GCP\CardbasePosWebservice();
        
        foreach($gift_cards as $giftcard_number => $giftcard_data)
        {
            $this->logger->debug('Giftcard number: '.$giftcard_number);
            
            // Check if a withdraw was done that can be rolled back
            if( ( isset( $giftcard_data['withdraw_request_id'] ) ) &&
                ( !empty( $giftcard_data['withdraw_request_id'] ) ) )
            {
                try
                {
                    $webservice->rollback_request($giftcard_number, $giftcard_data['card_validation_code'], $giftcard_data['withdraw_request_id']);
                    
                    if( ( isset( $giftcard_data['paid'] ) ) &&
                        ( !empty( $giftcard_data['paid'] ) ) )
                    {
                        $gift_cards[$giftcard_number]['balance'] = $giftcard_data['balance'] + $giftcard_data['paid'];
                        
                        // Remove the request id to avoid trying rollback twice
                        unset($gift_cards[$giftcard_number]['withdraw_request_id']);
                        unset($gift_cards[$giftcard_number]['paid']);
                    }
                    
                    // Add order notice
                    $note = sprintf( esc_html__('Rolled back %s for Gift Card %s. Original request id: %s', 'tcs-gift-card-payments-for-woocommerce'), wc_price($giftcard_data['paid']), $giftcard_number, $giftcard_data['withdraw_request_id'] );
                    $order->add_order_note( $note );
                }
                catch(Exception $e)
                {
                    $error_message = $e->getMessage();
                    
                    // Log the error
                    $this->logger->error('Rollback error: '.$error_message);
                    $this->logger->error('Last request id before catching error: '.$webservice->get_last_request_id());
                    
                    // Add order notice
                    $note = sprintf( esc_html__('Rollback failed for Gift Card %s. Original request id: %s', 'tcs-gift-card-payments-for-woocommerce'), wc_price($giftcard_data['paid']), $giftcard_number, $giftcard_data['withdraw_request_id'] );
                    $order->add_order_note( $note.' '.$error_message );
            
                    // Retrow exception so it can be caught by WooCommerce process_checkout()
                    throw new Exception( $error_message );
                }
            }
            else
            {
                $this->logger->debug('No rollback for this gift card');
            }
        }
        
        return $gift_cards;
    }
    
    /**
     * Executed when the status is changed to failed.
     */
    function order_status_failed( $order_id, $order ) {
        $this->logger->debug('Order '.$order_id.' changed status to failed');
        
        $this->rollback_for_order($order);
        
        // Get the original total in from the order meta
        $original_total = $order->get_meta( 'tcs_gcp_original_total' );
        
        $order->set_total( $original_total );
        $order->save();
    }
    
    /**
     * Executed when the status is changed to canceled.
     */
    function order_status_cancelled( $order_id, $order ) {
        $this->logger->debug('Order '.$order_id.' changed status to cancelled');
        
        $this->rollback_for_order($order);
        
        // Get the original total in from the order meta
        $original_total = $order->get_meta( 'tcs_gcp_original_total' );
        
        $order->set_total( $original_total );
        $order->save();
    }
    
    private function rollback_for_order( $order )
    {
        // Check if there are Gift Cards in the order
        $gift_cards = $order->get_meta( 'tcs_gcp_gift_cards' );
        
        if( !empty($gift_cards) )
        {
            $this->logger->debug("There are gift cards in the order. Let's try to roll them back");
            
            try
            {
                $gift_cards = $this->rollback($gift_cards, $order);
            }
            catch(Exception $e)
            {
                // Log the error
                $this->logger->error( 'Rollback error: '.$e->getMessage() );
            }
            
            $this->logger->debug('After rollback order: '.print_r($gift_cards, true));
            
            // update the session data
            WC()->session->set( 'tcs_gcp_gift_cards' , $gift_cards );
            
            // Update order meta
            $order->update_meta_data( 'tcs_gcp_gift_cards', $gift_cards );
            $order->save();
        }
    }
    
    /**
     * Executed when the status is changed.
     */
    public function order_status_changed( $order_id, $from_status, $to_status, $order )
    {
        $this->logger->debug('Order '.$order_id.' changed status from '.$from_status.' to '.$to_status);
    }
    
    /**
     * Executed when the status is changed to completed.
     */
    public function order_status_completed( $order_id, $order )
    {
        $this->logger->debug('Order '.$order_id.' changed to status completed');
    }
    
    /**
     * Executed when payment is completed.
     */
    public function payment_complete( $order_id )
    {
       $this->logger->debug('Order '.$order_id.' completed payment');
    }
    
    public function order_needs_payment( $order_needs_payment, $order )
    {
        $payment_needed = $order_needs_payment ? 'true' : 'false';
        $this->logger->debug('Order '. $order->get_id().' needs payment = '.$payment_needed); 
        
        return $order_needs_payment;
    }
    
    /**
     * Limit max amount to withdraw
     * for open loop gift cards
     */
    private function get_max_amount_to_withdraw_from_card( $card_balance )
    {
        $max_amount_to_withdraw = $card_balance;
        
        $settings = new \TCS_GCP\Settings();
        
        $is_open_loop_card = $settings->is_open_loop_giftcard();
        
        if( $is_open_loop_card )
        {
            if( $card_balance > (float)50 )
            {
                $max_amount_to_withdraw = (float)50;
            }
        }
        
        return $max_amount_to_withdraw;
    }
}
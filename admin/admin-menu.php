<?php
namespace TCS_GCP;

if(!defined( 'ABSPATH' )) exit; /* Don't allow direct access */

require_once plugin_dir_path( __FILE__ ) . '../includes/logger.php';
require_once plugin_dir_path( __FILE__ ) . '../includes/settings.php';

class AdminMenu
{
    private $logger = null;
   
    public function __construct()
    {
        $this->logger = \TCS_GCP\Logger::getInstance();
    }
    
    /**
     * Add a menu for our settings page
     */
    public function init()
    {
        $settings = new \TCS_GCP\Settings();
        
        add_menu_page(
            __( 'TCS Gift Card Payments', 'tcs-gift-card-payments-for-woocommerce' ),   // Page title
            __( 'TCS Gift Card Payments', 'tcs-gift-card-payments-for-woocommerce' ),   // Menu title
            'manage_options',                                                           // Minimum required capability
            'tcs-gcp-settings-page',                                                    // Menu slug
            [$this, 'options_page'],                                                    // Function that outputs the page
            'dashicons-admin-generic',                                                  // Icon url
            55                                                                          // Position
        );
        
        /* Add settings sections */
        add_settings_section(
            'TCS_GCP_keys',                                                         // HTML Id tag
            __('123TCS Keys', 'tcs-gift-card-payments-for-woocommerce'),            // Title
            [$this, 'TCS_GCP_Plugin_Keys_Section_text'],                            // Callback to echo section explanation
            'tcs-gcp-settings-page'                                                 // The ?page=tcs-gcp-settings-page part of the page url
        );
        
        add_settings_section(
            'TCS_GCP_gift_card',                                                    // HTML Id tag
            __('Gift Card name and logo', 'tcs-gift-card-payments-for-woocommerce'),// Title
            [$this, 'TCS_GCP_Plugin_Gift_Card_Section_text'],                       // Callback to echo section explanation
            'tcs-gcp-settings-page'                                                 // The ?page=tcs-gcp-settings-page part of the page url
        );
        
        /* Create our settings fields */
        add_settings_field(
            'live_merchant_id',                                                 // HTML Id tag
            __('Live Merchant ID', 'tcs-gift-card-payments-for-woocommerce'),   // Title
            [$settings, 'get_live_merchant_id_field'],                          // Callback to echo the form field
            'tcs-gcp-settings-page',                                            // Settings page on which to show the section
            'TCS_GCP_keys'                                                      // Section of the settings page in which to show the field
        );
        
        add_settings_field(
            'live_secret_key',                                                  // HTML Id tag
            __('Live Secret Key', 'tcs-gift-card-payments-for-woocommerce'),    // Title
            [$settings, 'get_live_secret_key_field'],                           // Callback to echo the form field
            'tcs-gcp-settings-page',                                            // Settings page on which to show the section
            'TCS_GCP_keys'                                                      // Section of the settings page in which to show the field
        );
        
        add_settings_field(
            'activate_test_mode',                                               // HTML Id tag
            __('Activate test mode', 'tcs-gift-card-payments-for-woocommerce'), // Title
            [$settings, 'get_activate_test_mode_field'],                        // Callback to echo the form field
            'tcs-gcp-settings-page',                                            // Settings page on which to show the section
            'TCS_GCP_keys'                                                      // Section of the settings page in which to show the field
        );
        
        add_settings_field(
            'test_merchant_id',                                                 // HTML Id tag
            __('Test Merchant ID', 'tcs-gift-card-payments-for-woocommerce'),   // Title
            [$settings, 'get_test_merchant_id_field'],                          // Callback to echo the form field
            'tcs-gcp-settings-page',                                            // Settings page on which to show the section
            'TCS_GCP_keys'                                                      // Section of the settings page in which to show the field
        );
        
        add_settings_field(
            'test_secret_key',                                                  // HTML Id tag
            __('Test Secret Key', 'tcs-gift-card-payments-for-woocommerce'),    // Title
            [$settings, 'get_test_secret_key_field'],                           // Callback to echo the form field
            'tcs-gcp-settings-page',                                            // Settings page on which to show the section
            'TCS_GCP_keys'                                                      // Section of the settings page in which to show the field
        );
        
        add_settings_field(
            'is_open_loop_card',                                                // HTML Id tag
            __('Open loop card', 'tcs-gift-card-payments-for-woocommerce'),     // Title
            [$settings, 'get_open_loop_giftcard_field'],                        // Callback to echo the form field
            'tcs-gcp-settings-page',                                            // Settings page on which to show the section
            'TCS_GCP_keys'                                                      // Section of the settings page in which to show the field
        );
        
        add_settings_field(
            'gift_card_name',                                                   // HTML Id tag
            __('Gift Card name', 'tcs-gift-card-payments-for-woocommerce'),     // Title
            [$settings, 'get_gift_card_name_field'],                            // Callback to echo the form field
            'tcs-gcp-settings-page',                                            // Settings page on which to show the section
            'TCS_GCP_gift_card'                                                 // Section of the settings page in which to show the field
        );
        
        add_settings_field(
            'gift_card_logo',                                                   // HTML Id tag
            __('Gift Card logo', 'tcs-gift-card-payments-for-woocommerce'),     // Title
            [$settings, 'media_selector_settings_page_callback'],               // Callback to echo the form field
            'tcs-gcp-settings-page',                                            // Settings page on which to show the section
            'TCS_GCP_gift_card'                                                 // Section of the settings page in which to show the field
        );
    }
    
    /**
     * Create the settings page
     */
    public function options_page()
    {
        ?>
        <div class="wrap">
            <h2><?php _e('TCS Gift Card Payments for WooCommerce settings', 'tcs-gift-card-payments-for-woocommerce' ) ?></h2>
            <form action="options.php" method="post">
                <?php
                settings_fields('TCS_GCP_plugin_options');                                              // References the whitelisted option declared with register_setting()
                do_settings_sections('tcs-gcp-settings-page');                                          // Output all sections and form fields
                submit_button(__('Save changes', 'tcs-gift-card-payments-for-woocommerce'), 'primary'); // Display the form submission button
                ?>
            </form>
        </div>
        <?php
    }
    
    public function TCS_GCP_Plugin_Keys_Section_text()
    {
        $html = sprintf('<p>%s</p>', __('Enter your TCS Merchant ID and Secret keys here', 'tcs-gift-card-payments-for-woocommerce'));
        echo $html;
    }
    
    public function TCS_GCP_Plugin_Gift_Card_Section_text()
    {
        $html = sprintf('<p>%s</p>', __('Enter your Gift Card name and Gift Card logo here', 'tcs-gift-card-payments-for-woocommerce'));
        echo $html;
    }
}
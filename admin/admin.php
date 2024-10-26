<?php
namespace TCS_GCP;

if(!defined( 'ABSPATH' )) exit; /* Don't allow direct access */

require_once plugin_dir_path( __FILE__ ) . '../includes/logger.php';
require_once plugin_dir_path( __FILE__ ) . 'admin-menu.php';
require_once plugin_dir_path( __FILE__ ) . 'admin-order-page.php';
require_once plugin_dir_path( __FILE__ ) . '../includes/settings.php';

class Admin
{
    private $logger = null;
    private $woocommerce_active = false;
	private $admin_notices = array();
  
	public function __construct($logger)
	{
		$this->logger = \TCS_GCP\Logger::getInstance();

        $settings = new \TCS_GCP\Settings();
        
        // Check if Merchant Id and Secret Key is set for active mode
        $test_mode = $settings->is_testmode_active();
        if($test_mode)
        {
            ob_start();
            include plugin_dir_path( __FILE__ ) . 'templates/admin-notice-test-mode-active.php';
    		$this->admin_notices[] = ob_get_clean();
    		
            if( ( empty( $settings->get_test_merchant_id() ) ) ||
                ( empty( $settings->get_test_secret_key() ) ) )
            {
                ob_start();
                include plugin_dir_path( __FILE__ ) . 'templates/admin-notice-no-test-credentials-found.php';
        		$this->admin_notices[] = ob_get_clean();
            }
        }
        else
        {
            if( ( empty( $settings->get_live_merchant_id() ) ) ||
                ( empty( $settings->get_live_secret_key() ) ) )
            {
                ob_start();
                include plugin_dir_path( __FILE__ ) . 'templates/admin-notice-no-live-credentials-found.php';
        		$this->admin_notices[] = ob_get_clean();
            }
        }
	}
	
	public function init()
    {
        $this->register_script();
	    add_action('admin_enqueue_scripts', [$this, 'conditionally_enqueue_script']);
        
        $settings = new \TCS_GCP\Settings();
        
        $settings->init();
        
        add_action('admin_notices', [$settings, 'notices']);
        
        // Display details on admin order page
        $admin_order_page = new \TCS_GCP\AdminOrderPage();
        
        $admin_order_page->init();
    }
    
    public function menu()
    {
        $menu = new \TCS_GCP\AdminMenu();
        
        $menu->init();
    }
	
	/**
	 * To be used by the WordPress admin_notices hook
	 */
	public function notices()
	{
		foreach ($this->admin_notices as $notice)
		{
			echo $notice;
		}
	}
	
	/**
	 * Ads the "WooCommerce not found notice to
	 * the $admin_notices array
	 */
	public function add_admin_notice_WooCommerce_not_found()
	{
	    ob_start();
        include plugin_dir_path( __FILE__ ) . 'templates/admin-notice-woocommerce-not-found.php';
		$this->admin_notices[] = ob_get_clean();
	}
	
	public function register_script()
	{
	    wp_register_script( 
	        'tcs_gcp_admin_script',                                     // Unique name
	        plugin_dir_url( __FILE__ ) . 'js/admin.js',                 // URL to the file
	        array(),                                                    // Dependencies
	        filemtime(plugin_dir_path( __FILE__ ) . 'js/admin.js'),     // Version
	        true );                                                     // Output in footer
	}
	
	public function conditionally_enqueue_script($hook)
	{
	    if('toplevel_page_tcs-gcp-settings-page' != $hook)
	    {
	        return;
	    }
        
        wp_enqueue_script('tcs_gcp_admin_script');
        
        $saved_attachment_post_id = get_option( 'media_selector_attachment_id', 0 );
        
        $result = wp_localize_script('tcs_gcp_admin_script', 'admin_ajax_object',
            array(
                'saved_attachment_post_id' => $saved_attachment_post_id
            )
        );
    
	    if($result == false)
	    {
	        $this->logger->debug('Admin failed to localize script');
	    }
	}
}
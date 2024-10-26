<?php
namespace TCS_GCP;

if(!defined( 'ABSPATH' )) exit; /* Don't allow direct access */

require_once plugin_dir_path( __FILE__ ) . '../public/checkout.php';
require_once plugin_dir_path( __FILE__ ) . '../admin/admin.php';
require_once plugin_dir_path( __FILE__ ) . 'logger.php';

class Setup
{
    private $logger = NULL;
    private $woocommerce_active = false;
    
    public function __construct()
    {
        $this->logger = \TCS_GCP\Logger::getInstance();
        $this->logger->set_log_level_debug();
        
        $this->woocommerce_active = in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')));
        
        if(!$this->woocommerce_active)
        {
            $this->logger->error('WooCommerce not installed or not active.');
        }
        else
        {
            $this->logger->use_WooCommerce_logger();
        }
    }
    
    public function boot()
    {
        if(is_admin())
        {
            // $this->logger->debug('Setup boot as admin');
            
            $admin = new \TCS_GCP\Admin($this->woocommerce_active);
            
            add_action('admin_init', [$admin, 'init']);
            add_action('admin_menu', [$admin, 'menu'] );
            add_action('admin_notices', [$admin, 'notices']);
            
            /**
             * AJAX calls are done with is_admin returning true.
             */
            if($this->woocommerce_active)
            {
                $checkout = new \TCS_GCP\Checkout();
            
                add_action('init', [$checkout, 'admin_init']);
            }
            
            if(!$this->woocommerce_active)
            {
                $admin->add_admin_notice_WooCommerce_not_found();
            }
        }
        else
        {
            // $this->logger->debug('Setup boot as public');
            
            if($this->woocommerce_active)
            {
                $checkout = new \TCS_GCP\Checkout();
            
                add_action('init', [$checkout, 'init']);
            }
        }
    }
}
<?php

/**
 * Plugin Name:       		123TCS Gift Card Payments for WooCommerce
 * Plugin URI:        		https://www.123tcs.com/
 * Description:       		This plugin extends WooCommerce with the option to (partially) pay an order with a 123TCS Gift Card.
 * Version:           		1.6.0
 * Requires at least: 		5.7
 * Tested up to:            5.8.1
 * Requires PHP:      		7.2
 * Author:            		123TCS
 * Author URI:              https://www.123tcs.com/
 * License:                 GPL v2 or later
 * License URI:             https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       		tcs-gift-card-payments-for-woocommerce
 * Domain Path:       		/languages
 * WC requires at least: 	5.1.0
 * WC tested up to: 		5.8.0
 */
 
if(!defined( 'ABSPATH' )) exit; /* Don't allow direct access */

require_once plugin_dir_path( __FILE__ ) . 'includes/settings.php';
require_once plugin_dir_path( __FILE__ ) . 'admin/updateChecker.php';

register_activation_hook(__FILE__, 'TCS_GCP_activate');
function TCS_GCP_activate()
{
	$settings = new \TCS_GCP\Settings();
        
    $settings->create_settings();
}

register_deactivation_hook(__FILE__, 'TCS_GCP_deactivate');
function TCS_GCP_deactivate()
{	
    // Currently nothing to do on deactivate
}

add_action('plugins_loaded', 'TCS_GCP_bootstrap');
function TCS_GCP_bootstrap()
{
    load_plugin_textdomain( 'tcs-gift-card-payments-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    
    require_once plugin_dir_path( __FILE__ ) . 'includes/setup.php';
    
    $slug = '123tcs-gift-card-payments-for-woocommerce';
    $basename = plugin_basename( __FILE__ );
    $version = '1.6.0';
    
    new \TCS_GCP\UpdateChecker($slug, $basename, $version);
    
    $setup = new \TCS_GCP\Setup();
    
    $setup->boot();
}
 
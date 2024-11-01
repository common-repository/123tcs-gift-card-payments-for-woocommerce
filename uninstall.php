<?php
if(!defined( 'ABSPATH' )) exit; /* Don't allow direct access */

/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 */

// If uninstall not called from WordPress, then exit.
if(!defined( 'WP_UNINSTALL_PLUGIN' )) exit;

require_once plugin_dir_path( __FILE__ ) . 'includes/settings.php';

$settings = new \TCS_GCP\Settings();
        
$settings->delete_settings();
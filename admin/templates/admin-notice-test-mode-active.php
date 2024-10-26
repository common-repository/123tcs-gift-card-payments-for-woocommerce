<?php

if(!defined( 'ABSPATH' )) exit; /* Don't allow direct access */

$url = get_admin_url(null, 'admin.php?page=tcs-gcp-settings-page');
$link = sprintf( 
    wp_kses( 
        __('<strong>123TCS Gift Card Payments for WooCommerce</strong> The test mode is active. Please <a href="%s">deactivate this</a> before going into production', 'tcs-gift-card-payments-for-woocommerce'), 
        array(  
            'a'         => array( 'href' => array() ), 
            'strong'    => array() 
        )
    ), 
    esc_url( $url ) );

?>

<div class="notice notice-warning">
	<p><?php echo $link ?></p>
</div>
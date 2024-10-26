<?php

if(!defined( 'ABSPATH' )) exit; /* Don't allow direct access */

$url = get_admin_url(null, 'admin.php?page=tcs-gcp-settings-page');
$link = sprintf( 
    wp_kses( 
        __('<strong>123TCS Gift Card Payments for WooCommerce</strong> The Test Merchant Id and / or Test Secret Key is missing. Please <a href="%s">set the Merchant Id and Secret Key</a> to use 123TCS Gift Card Payments for WooCommerce', 'tcs-gift-card-payments-for-woocommerce'), 
        array(  
            'a'         => array( 'href' => array() ), 
            'strong'    => array() 
        )
    ), 
    esc_url( $url ) );

?>

<div class="notice notice-error">
	<p><?php echo $link ?></p>
</div>

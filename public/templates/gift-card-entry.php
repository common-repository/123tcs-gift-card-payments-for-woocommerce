<?php

if(!defined( 'ABSPATH' )) exit; /* Don't allow direct access */

require_once plugin_dir_path( __FILE__ ) . '../../includes/settings.php';

$settings = new \TCS_GCP\Settings();

$value = $settings->get_gift_card_logo();
        
if(intval($value) > 0)
{
    $this->logger->debug('Value for logo = '.$value);
    $image = wp_get_attachment_url( $value );
}
else
{
    $this->logger->debug('Using default logo');
    $image = plugin_dir_url( __FILE__ ) . '../../assets/images/logo-123tcs-white.png';
}

?>

<div id="tcs-gcp-gift-card" class="tcs-gcp">
    <div class="tcs-gcp-title-and-logo-box">
        <h3 class="tcs-gcp-heading"><?php echo $settings->get_gift_card_name(); ?></h3>
        <div class="tcs-gcp-image-wrapper">
            <img src="<?php echo $image; ?>" class="tcs-gcp-gift-card-logo" width='100' height='75' alt="<?php _e('Gift Card logo', 'tcs-gift-card-payments-for-woocommerce') ?>">
        </div>
    </div>
    <div class="tcs-gcp-card-entry">
        <div class="tcs-gcp-card-entry-fields">
            <?php
             woocommerce_form_field( 
                'tcs-gcp-gift-card-number',                                     // Key
                array(                                                          // Args
                    'type'          => 'text',
                    'class'         => array('tcs-gcp-form-field form-row-wide'),
                    'label'         => __('Card number', 'tcs-gift-card-payments-for-woocommerce'),
                    'placeholder'   => '0000000000000000000',
                ),
                null);                                                          // Value
                
            woocommerce_form_field( 
                'tcs-gcp-gift-card-validation-code',                            // Key
                array(                                                          // Args
                    'type'          => 'text',
                    'class'         => array('tcs-gcp-form-field form-row-wide'),
                    'label'         => __('Validation code', 'tcs-gift-card-payments-for-woocommerce'),
                    'placeholder'   => '00000',
                ),
                null);                                                          // Value
             ?>
             <input type="button" id="tcs-gcp-add-gift-card-button" class="tcs-gcp-button button alt" value="<?php _e('Add Gift Card', 'tcs-gift-card-payments-for-woocommerce')  ?>">
        </div>
    </div>
</div>

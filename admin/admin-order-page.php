<?php
namespace TCS_GCP;

if(!defined( 'ABSPATH' )) exit; /* Don't allow direct access */

require_once plugin_dir_path( __FILE__ ) . '../includes/logger.php';
require_once plugin_dir_path( __FILE__ ) . '../includes/settings.php';

class AdminOrderPage
{
    private $logger = null;
    
    public function __construct()
    {
        $this->logger = \TCS_GCP\Logger::getInstance();
    }
    
    public function init()
    {
        add_action('woocommerce_admin_order_totals_after_tax', [$this, 'admin_order_totals_after_tax'], 10, 1 );
    }
    
    public function admin_order_totals_after_tax( $order_id )
    {
        $this->logger->debug('Outputting html for admin order details for order: '.$order_id);
        
        $order = wc_get_order( $order_id );
        
        if ( $order )
        {
            $original_total = $order->get_meta( 'tcs_gcp_original_total' );
     
            $giftcards = $order->get_meta( 'tcs_gcp_gift_cards' );
            
            $this->logger->debug('Order meta gift cards: '.print_r( $giftcards, true ));
            $this->logger->debug('Order meta original total: '.print_r( $order->get_meta( 'tcs_gcp_original_total' ), true ) );
            
            if( !empty($giftcards) )
            {
                $settings = new \TCS_GCP\Settings();
                
                foreach($giftcards as $giftcard_number => $giftcard_data)
                {
                    if( ( isset( $giftcard_data['paid'] ) ) &&
                        ( !empty( $giftcard_data['paid'] ) ) )
                    {
                        ?>
                        <tr>
                            <td class="label"><?php echo $settings->get_gift_card_name();?> <?php echo $giftcard_number; ?>:</td>
                            <td width="1%"></td>
                            <td>-<?php echo wc_price($giftcard_data['paid']); ?></td>
                        </tr>
                        <?php
                    }
                }
            }
        }
        else
        {
            error_log('Order id not found for order: '.$order_id);
        }
    }
}
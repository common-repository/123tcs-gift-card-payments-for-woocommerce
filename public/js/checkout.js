jQuery(document).ready(function($)
{
    $("#tcs-gcp-add-gift-card-button").click(function()
    {
        // Disable the button to avoid multiple clicks while processing
        $("#tcs-gcp-add-gift-card-button").attr("disabled", true);
        
        // Store the original text and change the text to reflect the busy state
        $original_text = $("#tcs-gcp-add-gift-card-button").attr('value');
        $("#tcs-gcp-add-gift-card-button").attr('value', checkout_ajax_object.processing_text);
        
        // Send the checkout form data to the server using AJAX to add the gift card
        $('body').trigger('update_checkout');
        
        // Once checkout has been updated
        $('body').on('updated_checkout', function()
        {
            // Clear the fields
            $('#tcs-gcp-gift-card-number').val('');
            $('#tcs-gcp-gift-card-validation-code').val('');
        });
        
        // Enable the button when processing is done
        $("#tcs-gcp-add-gift-card-button").attr('value', $original_text);
        $("#tcs-gcp-add-gift-card-button").attr("disabled", false);
    });
    
    $(document).on('click', '.tcs-gcp-remove-gift-card-button',function(event)
    {
        var el = event.target;
        var card_number = el.getAttribute("data-card_number");
        
        var data = {
            'action': 'remove_gift_card',
        	'nonce': checkout_ajax_object.nonce,
        	'card_number': card_number
        };
        
        // Set cursor to progess to reflect the busy state
        $("body").css("cursor", "progress");
        
        // Try to remove the Gift Card
        $.post(checkout_ajax_object.ajaxurl, data, function(response)
        {
            // Restore the cursor
            $("body").css("cursor", "default");
            
            // Let WooCommerce do the AJAX to refresh the overview
            $('body').trigger('update_checkout');
        });
    });
    
});
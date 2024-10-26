jQuery( document ).ready( function( $ )
{

	// Uploading files
	var file_frame;
	var wp_media_post_id = wp.media.model.settings.post.id; // Store the old id
	var set_to_post_id = admin_ajax_object.saved_attachment_post_id; // Set this

	$('#upload_image_button').on('click', function( event )
	{
		event.preventDefault();

		// Create the media frame.
		file_frame = wp.media.frames.file_frame = wp.media({
			title: 'Select a image to upload',
			button: {
				text: 'Use this image',
			},
			multiple: false	// Set to true to allow multiple files to be selected
		});

		// When an image is selected, run a callback.
		file_frame.on( 'select', function() {
			// We set multiple to false so only get one image from the uploader
			attachment = file_frame.state().get('selection').first().toJSON();

			// Do something with attachment.id and/or attachment.url here
			$( '#image-preview' ).attr( 'src', attachment.url ).css( 'width', 'auto' );
			$( '#image_attachment_id' ).val( attachment.id );

			// Restore the main post ID
			wp.media.model.settings.post.id = wp_media_post_id;
		});

		// Finally, open the modal
		file_frame.open();
	});

	// Restore the main ID when the add media button is pressed
	jQuery( 'a.add_media' ).on( 'click', function()
	{
		wp.media.model.settings.post.id = wp_media_post_id;
	});
});
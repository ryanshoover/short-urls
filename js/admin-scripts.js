// Control the "COPY URL" button behavior
jQuery( document ).ready( function($) {

	// Show the modal, and set a timeout to remove it
	function showCopiedAlert() {
		$('#wpwrap').after('<div id="modal-copied" class="modal copied"><p>Your shortened URL is copied!</p></div>').siblings('#modal-copied').each(function() {
			$(this).fadeIn(500);
			setTimeout( function() { $('#modal-copied').fadeOut().promise().done( function() {
				$(this).remove(); });
			}, 4000);
		});
	}

	$()

	// Copy the URL and show the modal if the button is clicked
	$('#btn-copy-url').click( function( e ) {
		e.preventDefault();
	    $('#wpeurl_link_display_url').select();
	    success = document.execCommand("copy");
	    if ( success ) {
	    	showCopiedAlert();
	    }
	} );

	// Remove the modal if it's clicked
	$(document).bind('DOMNodeInserted', function(e) {
		var element = e.target;

		if ( 'modal-copied' == element.id ) {
			$(element).click( function() { $(this).remove(); });
		}
	});

	// Automatically trigger a copy of the URL
	$('#btn-copy-url').click();
});

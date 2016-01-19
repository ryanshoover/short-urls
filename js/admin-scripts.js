// Control the "COPY URL" button behavior
jQuery( document ).ready( function($) {

	function showCopiedAlert() {
		$('#wpwrap').after('<div id="modal-copied" class="modal copied"><p>Your shortened URL is copied!</p></div>').siblings('#modal-copied').each(function() {
			$(this).fadeIn(500);
			setTimeout( function() { $('#modal-copied').fadeOut().promise().done( function() {
				$(this).remove()});
			}, 4000);
		});
	}

	$()

	$('#btn-copy-url').click( function( e ) {
		e.preventDefault();
	    $('#wpeurl_link_display_url').select();
	    success = document.execCommand("copy");
	    if ( success ) {
	    	showCopiedAlert();
	    }
	} );

	// Automatically trigger a copy of the URL
	$('#btn-copy-url').click();
});

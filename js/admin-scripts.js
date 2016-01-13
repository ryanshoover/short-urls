// Control the "COPY URL" button behavior
jQuery( document ).ready( function($) {

	$('#btn-copy-url').click( function( e ) {
		e.preventDefault();
	    $('#wpeurl_link_display_url').select();
	    document.execCommand("copy");
	} );
});

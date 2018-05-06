/**
 * Better Font Awesome Admin JS
 *
 * @since 1.0.10
 */
( function( $ ) {

	'use strict';

	$( document ).ready( function() {

		$( '.bfa-save-settings-button' ).on( 'click', function() {

			$( '.bfa-ajax-response-holder' ).empty();
			$( '.bfa-loading-gif' ).fadeIn();

			var $bfaSettingsForm, data = {}, version, minified, remove_existing_fa, hide_admin_notices;

			$bfaSettingsForm = $( '#bfa-settings-form' );

			$( '[name^="better-font-awesome_options["]' ).each( function() {
				var $input = $( this );
				var name = $input.prop( 'id' );
				var val;

				if ( $input.is( 'select' ) ) {
					val = $input.val();
				} else if ( $input.is( ':checkbox' ) ) {
					val = $input.is( ':checked' ) ? 1 : 0;
				}

				data[name] = val;
			});

			data.action = 'bfa_save_options';
			console.log(data);

			$.post(
				bfa_ajax_object.ajax_url, // Array passed via wp_localize_script()
				data,
				function( response ) {
					$( '.bfa-loading-gif' ).fadeOut( function() {
						$( '.bfa-ajax-response-holder' ).html( response ).slideDown().delay(2000).fadeTo(600, 0).delay(300).slideUp().fadeTo(0, 100);
					});
				}
			);

		});
	});
})( jQuery );

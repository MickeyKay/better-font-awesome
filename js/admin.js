/**
 * Better Font Awesome Admin JS
 *
 * @since 1.0.10
 */
( function( $ ) {

	'use strict';

	$( function() {

		$( '.bfa-save-settings-button' ).on( 'click', function() {

			$( '.bfa-ajax-response-holder' ).empty();
			$( '.bfa-loading-gif' ).fadeIn();

			var $bfaSettingsForm, data, include_v4_shim, remove_existing_fa, hide_admin_notices;

			$bfaSettingsForm = $( '#bfa-settings-form' );

			include_v4_shim = $bfaSettingsForm.find( 'input#include_v4_shim' ).is( ':checked' ) ? 1 : 0;
			remove_existing_fa = $bfaSettingsForm.find( 'input#remove_existing_fa' ).is( ':checked' ) ? 1 : 0;
			hide_admin_notices = $bfaSettingsForm.find( 'input#hide_admin_notices' ).is( ':checked' ) ? 1 : 0;

			data = {
				'action': 'bfa_save_options',
				'include_v4_shim': include_v4_shim,
				'remove_existing_fa': remove_existing_fa,
				'hide_admin_notices': hide_admin_notices,
			};

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

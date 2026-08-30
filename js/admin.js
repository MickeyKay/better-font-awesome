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

			var $bfaSettingsForm, data, include_v4_shim, remove_existing_fa, hide_admin_notices, nonce;

			$bfaSettingsForm = $( '#bfa-settings-form' );

			nonce = $bfaSettingsForm.find( 'input[name="_wpnonce"]' ).val();
			include_v4_shim = $bfaSettingsForm.find( 'input#include_v4_shim' ).is( ':checked' ) ? 1 : 0;
			remove_existing_fa = $bfaSettingsForm.find( 'input#remove_existing_fa' ).is( ':checked' ) ? 1 : 0;
			hide_admin_notices = $bfaSettingsForm.find( 'input#hide_admin_notices' ).is( ':checked' ) ? 1 : 0;

			data = {
				'action': 'bfa_save_options',
				'bfa_nonce': nonce,
				'include_v4_shim': include_v4_shim,
				'remove_existing_fa': remove_existing_fa,
				'hide_admin_notices': hide_admin_notices,
			};

			$.post(
				bfa_ajax_object.ajax_url, // Array passed via wp_localize_script()
				data,
				function() {}, // Empty success handler since success/errors handled below.
			).always( function( response, status, thing ) {
				var message, messageClass;

				if ('success' == status) {
					message = response;
					messageClass = 'updated';
				} else {
					message = response.responseText;
					messageClass = 'error';
				}

				$( '.bfa-loading-gif' ).fadeOut( function() {
					$( '.bfa-ajax-response-holder' )
						.html( `<div class="${messageClass}"><p>${message}</p></div>` )
						.slideDown()
						.delay(2000)
						.fadeTo(600, 0)
						.delay(300)
						.slideUp()
						.fadeTo(0, 100);
				});
			});
		});

		$( '.bfa-refresh-metadata-button' ).on( 'click', function() {
			var $button, $response, $spinner;

			$button = $( this );
			$response = $( '.bfa-refresh-response' );
			$spinner = $( '.bfa-refresh-metadata-spinner' );
			$button.prop( 'disabled', true );
			$spinner.addClass( 'is-active' );
			$response.empty();

			$.post(
				bfa_ajax_object.ajax_url,
				{
					'action': 'bfa_refresh_release_data',
					'bfa_nonce': bfa_ajax_object.refresh_nonce,
				}
			).done( function( response ) {
				var message;

				message = response && response.data && response.data.message ? response.data.message : '';
				$response.text( message );
				if ( response && response.success && response.data.status ) {
					$( '.bfa-metadata-status-value' ).text( response.data.status );
				}
			}).fail( function( response ) {
				var message;

				message = response.responseJSON && response.responseJSON.data && response.responseJSON.data.message ? response.responseJSON.data.message : response.statusText;
				$response.text( message );
			}).always( function() {
				$button.prop( 'disabled', false );
				$spinner.removeClass( 'is-active' );
			});
		});
	});
})( jQuery );

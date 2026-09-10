/* global bfaPro */
( function () {
	'use strict';
	const { __, sprintf } = wp.i18n;
	const form = document.getElementById( 'bfa-pro-form' );
	if ( ! form ) { return; }
	const status = document.getElementById( 'bfa-pro-status' );
	let generation = 0;
	let timer;
	async function send( operation, data = {} ) {
		const controller = new AbortController();
		const timeout = setTimeout( () => controller.abort(), 20000 );
		try {
			const response = await fetch( bfaPro.url, {
				method: 'POST', credentials: 'same-origin', signal: controller.signal,
				body: new URLSearchParams( { action: 'bfa_pro', nonce: bfaPro.nonce, operation, ...data } ),
			} );
			let result;
			try { result = await response.json(); } catch { throw new Error( __( 'Connection interrupted. Reload to resume or start again.', 'better-font-awesome' ) ); }
			if ( ! result.success ) { throw new Error( result.data?.message || __( 'Connection request failed. Reload to resume.', 'better-font-awesome' ) ); }
			return result.data;
		} finally { clearTimeout( timeout ); }
	}
	async function run( operation, data = {} ) {
		const mine = ++generation;
		clearTimeout( timer );
		form.setAttribute( 'aria-busy', 'true' );
		status.textContent = __( 'Preparing Kit connection...', 'better-font-awesome' );
		try {
			const state = await send( operation, data );
			if ( mine !== generation ) { return; }
			if ( state.pending ) {
				// translators: 1: current catalog page, 2: total pages.
				status.textContent = state.message || ( state.pages ? sprintf( __( 'Preparing Pro icons: page %1$d of %2$d.', 'better-font-awesome' ), state.page, state.pages ) : __( 'Validating Kit and preparing Pro icons...', 'better-font-awesome' ) );
				// Polling remains bounded and can resume after a reload, without WP-Cron.
				const delay = Math.max( 250, Math.min( 30000, ( state.retryAt * 1000 ) - Date.now() ) );
				timer = setTimeout( () => run( 'step', { id: state.operation } ), delay );
			} else if ( state.activationRequired ) {
				status.textContent = __( 'Activating Pro icons...', 'better-font-awesome' );
				window.location.reload();
			} else {
				// translators: 1: Font Awesome version, 2: comma-separated styles.
				status.textContent = state.message || ( state.connected ? sprintf( __( 'Connected: Font Awesome %1$s. Styles: %2$s.', 'better-font-awesome' ), state.version, state.styles.join( ', ' ) ) : __( 'Pro is inactive. The effective Free delivery setting applies.', 'better-font-awesome' ) );
				form.setAttribute( 'aria-busy', 'false' );
				if ( [ 'pause', 'disconnect' ].includes( operation ) ) { window.location.reload(); }
			}
		} catch ( error ) {
			if ( mine === generation ) {
				status.textContent = error.message || __( 'Connection interrupted. Reload to resume or start again.', 'better-font-awesome' );
				form.setAttribute( 'aria-busy', 'false' );
			}
		}
	}
	form.addEventListener( 'submit', ( event ) => {
		event.preventDefault();
		const token = document.getElementById( 'bfa-pro-token' );
		const data = { kit: document.getElementById( 'bfa-pro-kit' ).value.trim(), token: token.value };
		token.value = '';
		run( 'connect', data );
	} );
	form.querySelectorAll( '[data-pro-action]' ).forEach( ( button ) => {
		button.addEventListener( 'click', () => run( button.dataset.proAction ) );
	} );
	run( 'status' );
}() );

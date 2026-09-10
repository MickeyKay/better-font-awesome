/* global bfaPro */
( function () {
	'use strict';
	const { __, sprintf } = wp.i18n;
	const form = document.getElementById( 'bfa-pro-form' );
	if ( ! form ) { return; }
	const status = document.getElementById( 'bfa-pro-status' );
	const token = document.getElementById( 'bfa-pro-token' );
	const find = document.getElementById( 'bfa-pro-find' );
	const select = document.getElementById( 'bfa-pro-kit' );
	const connect = document.getElementById( 'bfa-pro-connect' );
	const accountStatus = document.getElementById( 'bfa-pro-account-status' );
	const summary = document.getElementById( 'bfa-pro-kit-help' );
	let discovery = 0;
	let account = { id: '', kits: [] };
	let generation = 0;
	let timer;
	async function send( operation, data = {} ) {
		const controller = new AbortController();
		const timeout = setTimeout( () => controller.abort(), operation === 'find' ? 30000 : 20000 );
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
		selectionChanged();
		status.textContent = __( 'Preparing Kit connection...', 'better-font-awesome' );
		try {
			const state = await send( operation, data );
			if ( mine !== generation ) { return; }
			if ( state.account && discovery === 0 ) { showAccount( state.account ); }
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
				// translators: 1: active Kit name, 2: Font Awesome version, 3: comma-separated styles.
				const connection = state.connected ? sprintf( __( 'Connected: %1$s. Font Awesome %2$s. Styles: %3$s.', 'better-font-awesome' ), state.kitName || state.kit, state.version, state.styles.join( ', ' ) ) : __( 'Pro is inactive. The effective Free delivery setting applies.', 'better-font-awesome' );
				status.textContent = state.connected ? [ connection, state.message ].filter( Boolean ).join( ' ' ) : state.message || connection;
				selectionChanged();
				if ( [ 'pause', 'disconnect' ].includes( operation ) ) { window.location.reload(); }
			}
		} catch ( error ) {
			if ( mine === generation ) {
				status.textContent = error.message || __( 'Connection interrupted. Reload to resume or start again.', 'better-font-awesome' );
				selectionChanged();
			}
		}
	}
	function selectionChanged() {
		const kit = account.kits.find( ( item ) => item.id === select.value );
		connect.disabled = ! kit?.supported;
		summary.textContent = kit?.summary || __( 'Choose a Kit, then click Connect Kit to validate its catalog and activate it.', 'better-font-awesome' );
	}
	function clearChoices() {
		account = { id: '', kits: [] };
		select.replaceChildren( new Option( __( 'Choose a Kit', 'better-font-awesome' ), '' ) );
		select.disabled = true;
		selectionChanged();
	}
	function showAccount( value ) {
		clearChoices();
		account = value;
		account.kits.forEach( ( kit ) => {
			let label = kit.name || __( 'Unnamed Kit', 'better-font-awesome' );
			if ( ! kit.name || account.kits.filter( ( item ) => item.name === kit.name ).length > 1 ) {
				// Extend a shared identifier prefix only as far as needed to distinguish it.
				let length = Math.min( 8, kit.id.length );
				while ( length < kit.id.length && account.kits.some( ( item ) => item.id !== kit.id && item.id.slice( 0, length ) === kit.id.slice( 0, length ) ) ) { length++; }
				label += ` (${ kit.id.slice( 0, length ) })`;
			}
			if ( ! kit.supported ) {
				// translators: %s: Kit name.
				label = sprintf( __( '%s (unsupported)', 'better-font-awesome' ), label );
			}
			// Unsupported options remain selectable; the linked summary explains why.
			select.add( new Option( label, kit.id ) );
		} );
		select.disabled = ! account.authorized || ! account.kits.length;
		accountStatus.textContent = account.authorized ? ( account.kits.length ? __( 'Account authorized. Choose a Kit to connect.', 'better-font-awesome' ) : __( 'Account authorized, but no Kits were found. Create a Kit in Font Awesome, then Find Kits again.', 'better-font-awesome' ) ) : ( account.saved ? __( 'A token is saved. Find Kits to refresh the available list.', 'better-font-awesome' ) : __( 'Enter an API token, then Find Kits. Account authorization does not activate a Kit.', 'better-font-awesome' ) );
	}
	async function findKits() {
		const mine = ++discovery;
		const value = token.value;
		token.value = '';
		clearChoices();
		find.setAttribute( 'aria-busy', 'true' );
		accountStatus.textContent = __( 'Finding Kits...', 'better-font-awesome' );
		try {
			const state = await send( 'find', { token: value } );
			if ( mine !== discovery ) { return; }
			showAccount( state.account );
		} catch ( error ) {
			if ( mine === discovery ) {
				accountStatus.textContent = ( error.message || __( 'Could not find Kits.', 'better-font-awesome' ) ) + ' ' + __( 'The active connection is unchanged. Check the token and try Find Kits again.', 'better-font-awesome' );
			}
		} finally {
			if ( mine === discovery ) { find.setAttribute( 'aria-busy', 'false' ); }
		}
	}
	token.addEventListener( 'input', () => {
		++discovery;
		clearChoices();
		find.setAttribute( 'aria-busy', 'false' );
		accountStatus.textContent = __( 'Token changed. Find Kits again before choosing a Kit.', 'better-font-awesome' );
	} );
	find.addEventListener( 'click', findKits );
	select.addEventListener( 'change', selectionChanged );
	token.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Enter' ) { event.preventDefault(); findKits(); }
	} );
	form.addEventListener( 'submit', ( event ) => {
		event.preventDefault();
		if ( ! connect.disabled ) { run( 'connect', { kit: select.value, id: account.id } ); }
	} );
	form.querySelectorAll( '[data-pro-action]' ).forEach( ( button ) => {
		button.addEventListener( 'click', () => run( button.dataset.proAction ) );
	} );
	run( 'status' );
}() );

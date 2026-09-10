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
	const provider = document.getElementById( 'bfa-provider' );
	const panel = document.getElementById( 'bfa-pro-panel' );
	const saved = document.getElementById( 'bfa-pro-saved' );
	const entry = document.getElementById( 'bfa-pro-token-entry' );
	const cancelToken = document.getElementById( 'bfa-pro-cancel-token' );
	const kitControls = document.getElementById( 'bfa-pro-kit-controls' );
	const refreshKits = document.getElementById( 'bfa-pro-refresh-kits' );
	const spinner = document.getElementById( 'bfa-pro-spinner' );
	const feedback = document.getElementById( 'bfa-pro-discovery-feedback' );
	const kitFeedback = document.getElementById( 'bfa-pro-kit-feedback' );
	const kitSpinner = document.getElementById( 'bfa-pro-kit-spinner' );
	const refreshActive = form.querySelector( '[data-pro-action="refresh"]' );
	let editingToken = false;
	let hasSavedToken = false;
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
		const accountGeneration = discovery;
		clearTimeout( timer );
		selectionChanged();
		if ( operation !== 'status' ) {
			kitSpinner.classList.add( 'is-active' );
			if ( operation !== 'step' ) { status.textContent = __( 'Working...', 'better-font-awesome' ); }
		}
		try {
			const state = await send( operation, data );
			if ( mine !== generation ) { return; }
			refreshActive.hidden = ! state.kit;
			kitSpinner.classList.toggle( 'is-active', Boolean( state.pending || state.activationRequired ) );
			if ( state.account && accountGeneration === discovery && ! editingToken ) { showAccount( state.account ); }
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
				// translators: %s: active Kit name.
				const connection = state.connected ? sprintf( __( 'Connected: %s.', 'better-font-awesome' ), state.kitName || state.kit ) : '';
				status.textContent = state.connected ? [ connection, state.message ].filter( Boolean ).join( ' ' ) : state.message || connection;
				selectionChanged();
				if ( operation === 'disconnect' ) { window.location.reload(); }
			}
		} catch ( error ) {
			if ( mine === generation ) {
				kitSpinner.classList.remove( 'is-active' );
				status.textContent = error.message || __( 'Connection interrupted. Reload to resume or start again.', 'better-font-awesome' );
				selectionChanged();
			}
		}
	}
	function selectionChanged() {
		const kit = account.kits.find( ( item ) => item.id === select.value );
		connect.disabled = ! kit?.supported || needsFreeSave();
		summary.textContent = kit?.summary || '';
	}
	function clearChoices() {
		account = { id: '', kits: [] };
		select.replaceChildren( new Option( __( 'Choose a Kit', 'better-font-awesome' ), '' ) );
		select.disabled = true;
		selectionChanged();
	}
	function showAccount( value ) {
		editingToken = false;
		hasSavedToken = value.saved;
		tokenControls();
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
		if ( account.saved ) { refreshKits.after( feedback ); }
		accountStatus.textContent = account.authorized && ! account.kits.length ? __( 'No Kits found. Create one in Font Awesome, then refresh.', 'better-font-awesome' ) : '';
	}
	async function findKits( reuseSaved = false ) {
		const mine = ++discovery;
		const value = reuseSaved ? '' : token.value;
		token.value = '';
		clearChoices();
		( reuseSaved ? refreshKits : find ).after( feedback );
		( reuseSaved ? refreshKits : find ).setAttribute( 'aria-busy', 'true' );
		spinner.classList.add( 'is-active' );
		accountStatus.textContent = reuseSaved ? __( 'Refreshing Kits...', 'better-font-awesome' ) : __( 'Connecting...', 'better-font-awesome' );
		try {
			const state = await send( 'find', { token: value } );
			if ( mine !== discovery ) { return; }
			showAccount( state.account );
		} catch ( error ) {
			if ( mine === discovery ) {
				accountStatus.textContent = error.message || __( 'Could not connect. Please try again.', 'better-font-awesome' );
			}
		} finally {
			if ( mine === discovery ) { find.setAttribute( 'aria-busy', 'false' ); refreshKits.setAttribute( 'aria-busy', 'false' ); spinner.classList.remove( 'is-active' ); }
		}
	}
	token.addEventListener( 'input', () => {
		++discovery;
		clearChoices();
		find.setAttribute( 'aria-busy', 'false' ); refreshKits.setAttribute( 'aria-busy', 'false' );
		spinner.classList.remove( 'is-active' );
		accountStatus.textContent = '';
	} );
	find.addEventListener( 'click', () => findKits() );
	refreshKits.addEventListener( 'click', () => findKits( true ) );
	select.addEventListener( 'change', selectionChanged );
	token.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Enter' ) { event.preventDefault(); findKits(); }
	} );
	form.addEventListener( 'submit', ( event ) => {
		event.preventDefault();
		if ( ! connect.disabled ) { connect.after( kitFeedback ); run( 'connect', { kit: select.value, id: account.id } ); }
	} );
	form.querySelectorAll( '[data-pro-action]' ).forEach( ( button ) => {
		button.addEventListener( 'click', () => {
			if ( button.dataset.proAction === 'disconnect' && ! window.confirm( __( 'Delete the saved token and disconnect the Kit? Saved icons will not be changed.', 'better-font-awesome' ) ) ) { return; }
			button.after( kitFeedback );
			run( button.dataset.proAction );
		} );
	} );
	function tokenControls() {
		saved.hidden = ! hasSavedToken || editingToken;
		entry.hidden = hasSavedToken && ! editingToken;
		cancelToken.hidden = ! hasSavedToken;
		kitControls.hidden = ! hasSavedToken || editingToken;
	}
	document.getElementById( 'bfa-pro-update-token' ).addEventListener( 'click', () => {
		++discovery;
		spinner.classList.remove( 'is-active' );
		accountStatus.textContent = '';
		editingToken = true;
		tokenControls();
		find.after( feedback );
		token.focus();
	} );
	cancelToken.addEventListener( 'click', () => {
		++discovery;
		editingToken = false;
		token.value = '';
		spinner.classList.remove( 'is-active' );
		find.setAttribute( 'aria-busy', 'false' ); refreshKits.setAttribute( 'aria-busy', 'false' );
		tokenControls();
		run( 'status' );
		document.getElementById( 'bfa-pro-update-token' ).focus();
	} );
	function selectedProvider() { return provider.value; }
	function needsFreeSave() { return selectedProvider() === 'kit-css' && provider.dataset.saved === 'bundled-local'; }
	function providerChanged() {
		const mode = selectedProvider();
		panel.hidden = mode !== 'kit-css';
		document.querySelectorAll( '.bfa-free-setting' ).forEach( row => { row.hidden = mode === 'kit-css'; } );
		document.getElementById( 'asset_delivery' ).checked = mode === 'bundled-local';
		document.getElementById( 'bfa-provider-input' ).value = mode;
		token.disabled = needsFreeSave();
		find.disabled = needsFreeSave();
		refreshKits.disabled = needsFreeSave();
		form.querySelector( '[data-pro-action="refresh"]' ).disabled = needsFreeSave();
		document.getElementById( 'bfa-provider-help' ).textContent = needsFreeSave() ? __( 'Save Settings to switch off local delivery before setting up a hosted Kit.', 'better-font-awesome' ) : ( mode === 'kit-css' ? __( 'CSS and fonts load from Font Awesome.', 'better-font-awesome' ) : '' );
		selectionChanged();
	}
	if ( window.location.hash === '#bfa-kit' ) { provider.value = 'kit-css'; }
	window.addEventListener( 'hashchange', () => {
		if ( window.location.hash === '#bfa-kit' ) { provider.value = 'kit-css'; providerChanged(); }
	} );
	provider.addEventListener( 'change', () => {
		history.replaceState( null, '', window.location.pathname + window.location.search + ( selectedProvider() === 'kit-css' ? '#bfa-kit' : '' ) );
		providerChanged();
	} );
	document.addEventListener( 'bfa:settings-saved', event => {
		if ( event.detail.provider !== provider.dataset.saved ) { window.location.reload(); }
	} );
	tokenControls();
	const deliveryStatus = document.getElementById( 'bfa-delivery-status' );
	if ( deliveryStatus ) { provider.parentElement.append( deliveryStatus ); }
	providerChanged();
	run( 'status' );
}() );

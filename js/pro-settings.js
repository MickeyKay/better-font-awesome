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
	const retry = document.getElementById( 'bfa-pro-retry' );
	const accountStatus = document.getElementById( 'bfa-pro-account-status' );
	const summary = document.getElementById( 'bfa-pro-kit-help' );
	const details = document.getElementById( 'bfa-pro-kit-details' );
	const detailsToggle = document.getElementById( 'bfa-pro-kit-details-toggle' );
	const facts = document.getElementById( 'bfa-pro-kit-facts' );
	const disconnectKit = document.getElementById( 'bfa-pro-disconnect-kit' );
	const warning = document.getElementById( 'bfa-pro-kit-warning' );
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
	let activeKit = null;
	let generation = 0;
	let connecting = false;
	let retryAction;
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
		retry.hidden = true;
		if ( [ 'connect', 'refresh' ].includes( operation ) ) { retryAction = { operation, data }; }
		connecting = operation !== 'status';
		selectionChanged();
		if ( operation !== 'status' ) {
			status.classList.remove( 'screen-reader-text' );
			kitSpinner.classList.add( 'is-active' );
			if ( operation !== 'step' ) { status.textContent = __( 'Working...', 'better-font-awesome' ); }
		}
		try {
			const state = await send( operation, data );
			if ( mine !== generation ) { return; }
			connecting = Boolean( state.pending || state.activationRequired );
			activeKit = state.connected ? { id: state.kit, name: state.kitName || state.kit, version: state.version, styles: state.styles } : null;
			status.classList.toggle( 'screen-reader-text', Boolean( state.connected && ! connecting && ! state.message ) );
			selectionChanged();
			refreshActive.hidden = ! state.kit;
			kitSpinner.classList.toggle( 'is-active', Boolean( state.pending || state.activationRequired ) );
			if ( state.account && accountGeneration === discovery && ! editingToken ) { showAccount( state.account ); }
			if ( state.pending ) {
				const progress = {
					icons: __( 'Loading Pro icons...', 'better-font-awesome' ),
					'free-coverage': __( 'Checking icon compatibility...', 'better-font-awesome' ),
					verify: __( 'Verifying kit...', 'better-font-awesome' ),
				};
				status.textContent = state.message || progress[ state.phase ] || __( 'Validating kit...', 'better-font-awesome' );
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
				retry.hidden = ! state.error || ! retryAction;
				selectionChanged();
				if ( operation === 'disconnect' ) { window.location.reload(); }
				if ( operation === 'disconnect-kit' ) {
					history.replaceState( null, '', window.location.pathname + window.location.search );
					window.location.reload();
				}
			}
		} catch ( error ) {
			if ( mine === generation ) {
				connecting = false;
				retry.hidden = ! retryAction;
				kitSpinner.classList.remove( 'is-active' );
				status.classList.remove( 'screen-reader-text' );
				status.textContent = error.message || __( 'Connection interrupted. Reload to resume or start again.', 'better-font-awesome' );
				selectionChanged();
			}
		}
	}
	function selectionChanged() {
		const kit = account.kits.find( ( item ) => item.id === select.value ) || ( activeKit && select.value === activeKit.id ? { ...activeKit, supported: true, details: activeKit } : null );
		select.options[ 0 ].disabled = Boolean( activeKit );
		select.disabled = connecting || needsFreeSave() || ! account.authorized || ! account.kits.length;
		retry.disabled = connecting || needsFreeSave();
		disconnectKit.hidden = ! activeKit || select.value !== activeKit.id;
		disconnectKit.disabled = connecting || needsFreeSave();
		showDetails( kit );
		warning.textContent = kit && ! kit.supported ? kit.reason || __( 'This kit is unsupported. See Kit details for requirements.', 'better-font-awesome' ) : '';
	}
	function showDetails( kit ) {
		// Older saved discovery lists can use the validated active snapshot, without HTTP.
		const data = kit?.details || ( kit?.id === activeKit?.id ? activeKit : null );
		facts.replaceChildren();
		if ( data && Array.isArray( data.styles ) ) {
			const styles = { solid: __( 'Solid', 'better-font-awesome' ), regular: __( 'Regular', 'better-font-awesome' ), light: __( 'Light', 'better-font-awesome' ), thin: __( 'Thin', 'better-font-awesome' ), brands: __( 'Brands', 'better-font-awesome' ) };
			const unknown = __( 'Unknown', 'better-font-awesome' );
			const license = data.license ?? ( kit.supported ? 'pro' : '' );
			const technology = data.technology ?? ( kit.supported ? 'webfonts' : '' );
			const compatibility = data.compatibility ?? ( kit.supported ? true : null );
			const rows = [
				[ __( 'Icons', 'better-font-awesome' ), { pro: 'Pro', free: 'Free' }[ license ] || unknown ],
				[ __( 'Technology', 'better-font-awesome' ), { webfonts: __( 'Web fonts', 'better-font-awesome' ), svg: 'SVG' }[ technology ] || unknown ],
				[ __( 'Version', 'better-font-awesome' ), data.version || unknown ],
				[ __( 'Older version compatibility', 'better-font-awesome' ), compatibility === null ? unknown : ( compatibility ? __( 'Enabled', 'better-font-awesome' ) : __( 'Disabled', 'better-font-awesome' ) ) ],
			];
			if ( kit.supported ) { rows.push( [ __( 'Classic styles', 'better-font-awesome' ), Object.keys( styles ).filter( style => data.styles.includes( style ) ).map( style => styles[ style ] ).join( ', ' ) ] ); }
			rows.forEach( ( [ label, value ] ) => {
				const row = document.createElement( 'div' );
				const term = document.createElement( 'dt' );
				const definition = document.createElement( 'dd' );
				term.textContent = label;
				definition.textContent = value;
				row.append( term, definition );
				facts.append( row );
			} );
		}
		facts.hidden = ! facts.childElementCount;
		summary.textContent = kit && ( ! kit.supported || facts.hidden ) ? kit.summary : '';
		summary.hidden = ! summary.textContent;
		detailsToggle.hidden = facts.hidden && summary.hidden;
		details.hidden = detailsToggle.hidden || detailsToggle.getAttribute( 'aria-expanded' ) !== 'true';
	}
	detailsToggle.addEventListener( 'click', () => {
		const expanded = detailsToggle.getAttribute( 'aria-expanded' ) !== 'true';
		detailsToggle.setAttribute( 'aria-expanded', String( expanded ) );
		details.hidden = ! expanded;
	} );
	function clearChoices() {
		retryAction = undefined;
		retry.hidden = true;
		account = { id: '', kits: [] };
		select.replaceChildren( new Option( __( 'Choose a kit', 'better-font-awesome' ), '' ) );
		if ( activeKit ) {
			select.add( new Option( activeLabel( activeKit.name ), activeKit.id, false, true ) );
		}
		select.disabled = true;
		selectionChanged();
	}
	function showAccount( value ) {
		const previous = select.value;
		editingToken = false;
		hasSavedToken = value.saved;
		tokenControls();
		clearChoices();
		select.replaceChildren( new Option( __( 'Choose a kit', 'better-font-awesome' ), '' ) );
		account = value;
		account.kits.forEach( ( kit ) => {
			let label = kit.name || __( 'Unnamed kit', 'better-font-awesome' );
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
			if ( kit.id === activeKit?.id ) { label = activeLabel( label ); }
			// Unsupported options remain selectable; the linked summary explains why.
			select.add( new Option( label, kit.id ) );
		} );
		if ( activeKit && ! account.kits.some( kit => kit.id === activeKit.id ) ) {
			const option = new Option( activeLabel( activeKit.name ), activeKit.id );
			option.disabled = true;
			select.add( option );
		}
		select.value = [ ...select.options ].some( option => option.value === previous ) && previous ? previous : activeKit?.id || '';
		selectionChanged();
		if ( account.saved ) { refreshKits.after( feedback ); }
		accountStatus.textContent = account.authorized && ! account.kits.length ? __( 'No kits found. Create one in Font Awesome, then refresh.', 'better-font-awesome' ) : '';
	}
	function activeLabel( name ) {
		// translators: %s: Kit name.
		return sprintf( __( '%s (active)', 'better-font-awesome' ), name );
	}
	async function findKits( reuseSaved = false ) {
		const mine = ++discovery;
		const value = reuseSaved ? '' : token.value;
		token.value = '';
		clearChoices();
		( reuseSaved ? refreshKits : find ).after( feedback );
		( reuseSaved ? refreshKits : find ).setAttribute( 'aria-busy', 'true' );
		spinner.classList.add( 'is-active' );
		accountStatus.textContent = reuseSaved ? __( 'Refreshing kits...', 'better-font-awesome' ) : __( 'Connecting...', 'better-font-awesome' );
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
	select.addEventListener( 'change', () => {
		detailsToggle.setAttribute( 'aria-expanded', 'false' );
		selectionChanged();
		const kit = account.kits.find( item => item.id === select.value );
		if ( kit?.supported && ! select.disabled ) {
			select.after( kitFeedback );
			run( 'connect', { kit: kit.id, id: account.id } );
		}
	} );
	retry.addEventListener( 'click', () => {
		if ( retryAction && ! retry.disabled ) { run( retryAction.operation, retryAction.data ); }
	} );
	token.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Enter' ) { event.preventDefault(); findKits(); }
	} );
	form.addEventListener( 'submit', ( event ) => {
		event.preventDefault();
	} );
	form.querySelectorAll( '[data-pro-action]' ).forEach( ( button ) => {
		button.addEventListener( 'click', () => {
			if ( button.dataset.proAction === 'disconnect' && ! window.confirm( __( 'Delete the saved token and disconnect the kit? Saved icons will not be changed.', 'better-font-awesome' ) ) ) { return; }
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
		document.getElementById( 'bfa-provider-help' ).textContent = needsFreeSave() ? __( 'Save Settings to switch off local delivery before setting up a hosted kit.', 'better-font-awesome' ) : '';
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

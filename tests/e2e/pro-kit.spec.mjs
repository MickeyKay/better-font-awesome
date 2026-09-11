import { expect, test } from '@playwright/test';
import fs from 'node:fs';

const settings = '/wp-admin/options-general.php?page=better-font-awesome#bfa-kit';
const font = fs.readFileSync( new URL( '../../vendor/mickey-kay/better-font-awesome-library/inc/font-awesome-7-fallback/webfonts/fa-solid-900.woff2', import.meta.url ) );
const css = `@font-face{font-family:BFA-Synthetic-Pro;src:url(https://ka-p.fontawesome.com/synthetic.woff2) format('woff2');font-weight:100 900;font-style:normal;font-display:block}.fa,.fas,.far,.fal,.fat,.fab{font-family:BFA-Synthetic-Pro!important}.fa,.fas{font-weight:900}.far,.fab{font-weight:400}.fal{font-weight:300}.fat{font-weight:100}.fa:before,.fas:before,.far:before,.fal:before,.fat:before,.fab:before{content:'\\f024'}`;

async function login( page ) {
	await page.goto( '/wp-login.php' );
	await page.request.post( '/wp-login.php', { form: { log: 'admin', pwd: 'password', testcookie: '1' } } );
	await page.goto( '/wp-admin/' );
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();
}
async function fixture( page, fault = '', enabled = true, families = false ) {
	await page.goto( '/wp-admin/tools.php?page=bfa-pro-fixture' );
	await page.getByLabel( 'Enable synthetic API' ).setChecked( enabled );
	await page.getByLabel( 'Full-sized synthetic catalog' ).check();
	await page.getByLabel( 'All official families' ).setChecked( families );
	await page.locator( 'select[name="fault"]' ).selectOption( fault );
	await page.getByRole( 'button', { name: 'Save fixture' } ).click();
}
async function saveProvider( page, value ) {
	const changed = await page.locator( '#bfa-provider' ).getAttribute( 'data-saved' ) !== value;
	await page.locator( '#bfa-provider' ).selectOption( value );
	if ( changed ) {
		await Promise.all( [ page.waitForEvent( 'load' ), page.getByRole( 'button', { name: 'Save Settings', exact: true } ).click() ] );
	} else {
		await page.getByRole( 'button', { name: 'Save Settings', exact: true } ).click();
		await expect( page.locator( '.bfa-ajax-response-holder' ) ).toContainText( 'Settings saved.' );
	}
}
async function expectKit( frame ) {
	const links = frame.locator( 'link[href^="https://kit.fontawesome.com/KIT_ID.css"]' );
	await expect( links ).toHaveCount( 1 );
	await expect( frame.locator( 'link#bfa-font-awesome-css,link#bfa-font-awesome-v4-shim-css,link[href*="font-awesome-7-fallback"]' ) ).toHaveCount( 0 );
	const imports = await frame.locator( 'style' ).allTextContents();
	expect( imports.join( '' ) ).not.toContain( 'kit.fontawesome.com' );
}

test( 'bounded Pro Connect and Refresh, all editors, saved styles, local switch and failures', async ( { page, context }, testInfo ) => {
	test.setTimeout( 180000 );
	page.setDefaultTimeout( 15000 );
	let blockCss = false;
	let blockFont = false;
	const requests = [];
	await context.route( 'https://kit.fontawesome.com/**', route => blockCss ? route.abort() : route.fulfill( { contentType: 'text/css', headers: { 'access-control-allow-origin': '*' }, body: css } ) );
	await context.route( 'https://ka-p.fontawesome.com/**', route => blockFont ? route.abort() : route.fulfill( { contentType: 'font/woff2', headers: { 'access-control-allow-origin': '*' }, body: font } ) );
	context.on( 'request', request => { if ( request.url().includes( 'fontawesome.com' ) ) { requests.push( { host: new URL( request.url() ).host, type: request.resourceType(), auth: Boolean( request.headers().authorization ) } ); } } );
	await context.route( /^https:\/\/(?:cdnjs\.cloudflare\.com|cdn\.jsdelivr\.net|use\.fontawesome\.com)\//, route => route.abort() );
	page.on( 'dialog', dialog => dialog.accept() );
	await login( page );
	await fixture( page );
	await page.goto( settings );
	await saveProvider( page, 'automatic' );
	await page.goto( settings );
	await page.getByLabel( 'API Key', { exact: true } ).fill( 'SYNTHETIC-NOT-A-CREDENTIAL' );
	await page.getByRole( 'button', { name: 'Connect account', exact: true } ).click();
	await expect( page.getByText( 'API token saved', { exact: true } ) ).toBeVisible();
	let failFirstSelection = true;
	const progressSeen = new Set();
	let expectedProgress = '';
	await page.route( '**/admin-ajax.php', async route => {
		const operation = new URLSearchParams( route.request().postData() ).get( 'operation' );
		if ( failFirstSelection && operation === 'connect' ) {
			failFirstSelection = false;
			await route.fulfill( { status: 400, json: { success: false, data: { message: 'Synthetic connection interruption.' } } } );
		} else if ( [ 'connect', 'step' ].includes( operation ) ) {
			if ( operation === 'step' && expectedProgress ) {
				await expect( page.locator( '#bfa-pro-status' ) ).toHaveText( expectedProgress );
				progressSeen.add( expectedProgress );
			}
			const response = await route.fetch();
			const result = await response.json();
			expectedProgress = { icons: 'Loading Pro icons...', 'free-coverage': 'Checking icon compatibility...', verify: 'Verifying kit...' }[ result.data?.phase ] || '';
			await route.fulfill( { response } );
		} else { await route.continue(); }
	} );
	const started = Date.now();
	await page.getByLabel( 'Kit', { exact: true } ).selectOption( 'KIT_ID' );
	await expect( page.locator( '#bfa-pro-status' ) ).toHaveText( 'Synthetic connection interruption.' );
	const retry = page.getByRole( 'button', { name: 'Retry connection', exact: true } );
	await expect( retry ).toBeVisible();
	await retry.focus();
	await retry.press( 'Enter' );
	await expect( page.locator( '#bfa-pro-status' ) ).toContainText( 'Connected:', { timeout: 60000 } );
	const connectMs = Date.now() - started;
	expect( [ ...progressSeen ] ).toEqual( [ 'Loading Pro icons...', 'Checking icon compatibility...', 'Verifying kit...' ] );
	await expect( page.getByLabel( 'API Key', { exact: true } ) ).toHaveValue( '' );
	await expectKit( page );
	await expect( page.locator( '#default_block_icon_style option[value="thin"]' ) ).toHaveCount( 1 );
	await page.locator( '#default_block_icon_style' ).selectOption( 'thin' );
	await page.getByText( 'Save Settings', { exact: true } ).click();
	await expect( page.locator( '.bfa-ajax-response-holder' ) ).toContainText( 'Settings saved.' );
	// Native iframe: existing dynamic block identity, save and reload.
	await page.goto( '/wp-admin/post-new.php?post_type=bfa_iframe_test' );
	await page.waitForFunction( () => window.wp?.blocks?.getBlockType( 'better-font-awesome/icon' ) );
	const payloadBytes = await page.evaluate( () => new TextEncoder().encode( JSON.stringify( window.bfaBlockEditor ) ).length );
	await page.evaluate( () => {
		wp.data.dispatch( 'core/editor' ).editPost( { title: 'Synthetic Pro acceptance' } );
		wp.data.dispatch( 'core/block-editor' ).resetBlocks( [ 'solid', 'regular', 'light', 'thin', 'site-default' ].map( style => wp.blocks.createBlock( 'better-font-awesome/icon', { iconName: 'pro-fixture', iconStyle: style } ) ) );
	} );
	const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
	await expect( canvas.locator( '.fat.fa-pro-fixture' ) ).toHaveCount( 2 );
	const welcome = page.getByRole( 'button', { name: 'Close', exact: true } );
	if ( await welcome.isVisible() ) { await welcome.click(); }
	await page.evaluate( () => {
		wp.data.dispatch( 'core/block-editor' ).selectBlock( wp.data.select( 'core/block-editor' ).getBlocks()[ 0 ].clientId );
		wp.data.dispatch( 'core/edit-post' ).openGeneralSidebar( 'edit-post/block' );
	} );
	const styleControl = page.getByRole( 'combobox', { name: 'Appearance', exact: true } );
	await expect( styleControl.locator( 'option' ) ).toHaveText( [ 'Site default (Classic - Thin)', 'Classic - Solid', 'Classic - Regular', 'Classic - Light', 'Classic - Thin' ] );
	await expect( page.getByText( /Search all \d+ available Font Awesome Kit icons\./ ) ).toBeVisible();
	for ( const style of [ 'thin', 'light', 'solid' ] ) {
		await styleControl.selectOption( style );
		expect( await page.evaluate( () => wp.data.select( 'core/block-editor' ).getBlocks()[ 0 ].attributes.iconStyle ) ).toBe( style );
	}
	await expectKit( canvas );
	await expect( canvas.locator( 'link[href^="https://kit.fontawesome.com/"]' ) ).toHaveAttribute( 'crossorigin', 'anonymous' );
	expect( await canvas.locator( 'body' ).evaluate( () => document.fonts.load( '100 16px BFA-Synthetic-Pro', '\uf024' ).then( faces => faces.length ) ) ).toBeGreaterThan( 0 );
	await page.evaluate( () => wp.data.dispatch( 'core/editor' ).savePost() );
	const postId = await page.evaluate( () => wp.data.select( 'core/editor' ).getCurrentPostId() );
	await page.goto( `/wp-admin/post.php?post=${ postId }&action=edit` );
	await expect( page.frameLocator( 'iframe[name="editor-canvas"]' ).locator( '.fat.fa-pro-fixture' ) ).toHaveCount( 2 );
	// Authenticated test-only frontend renders the saved block content unchanged.
	await page.goto( `/?bfa_pro_preview=${ postId }` );
	await expectKit( page );
	await expect( page.locator( '.fat.fa-pro-fixture' ) ).toHaveCount( 2 );
	await expect( page.locator( '.fa.fa-mug-saucer' ) ).toHaveCount( 1 );
	await expect( page.locator( '.fa.fa-flag' ) ).toHaveCount( 1 );
	await expect( page.locator( '.fab.fa-github' ) ).toHaveCount( 1 );
	// Classic and hybrid use existing TinyMCE mce_css loading and picker.
	const pickerMs = {};
	for ( const postType of [ 'bfa_classic_test', 'post' ] ) {
		await page.goto( `/wp-admin/post-new.php?post_type=${ postType }` );
		await page.waitForFunction( () => window.tinymce?.editors?.some( editor => editor.initialized ) );
		const welcome = page.getByRole( 'button', { name: 'Close', exact: true } );
		if ( await welcome.isVisible() ) { await welcome.click(); }
		const id = postType === 'post' ? 'bfa_hybrid_editor' : 'content';
		await page.waitForFunction( id => window.tinymce?.get( id )?.initialized, id );
		await expectKit( page.frameLocator( `#${ id }_ifr` ) );
		const before = Date.now();
		const button = page.locator( '.bfa-iconpicker .iconpicker-component' ).first();
		await button.click();
		const search = page.locator( '.iconpicker-search' ).filter( { visible: true } ).first();
		await search.fill( 'pro-fixture' );
		await expect( page.locator( '.iconpicker-item:visible .fat.fa-pro-fixture' ).first() ).toBeVisible();
		pickerMs[ postType ] = Date.now() - before;
		await page.locator( '.iconpicker-item:visible .fat.fa-pro-fixture' ).first().click();
		const content = await page.evaluate( id => tinymce.get( id ).getContent(), id );
		expect( content ).toContain( 'style="thin"' );
	}
	await fixture( page, 'auth' );
	await page.goto( settings );
	await page.getByRole( 'button', { name: 'Refresh active kit', exact: true } ).click();
	await expect( page.locator( '#bfa-pro-status' ) ).toContainText( 'Authorization failed' );
	await expectKit( page );
	await fixture( page );
	await page.goto( settings );
	await page.getByRole( 'button', { name: 'Refresh active kit', exact: true } ).click();
	await expect( page.locator( '#bfa-pro-status' ) ).toContainText( 'Connected:', { timeout: 60000 } );
	// Block delivery without changing account state. Markup/configuration survives.
	blockCss = true;
	await page.goto( `/?bfa_pro_preview=${ postId }` );
	await expect( page.locator( '.fat.fa-pro-fixture' ) ).toHaveCount( 2 );
	await expectKit( page );
	expect( await page.locator( '.fat.fa-pro-fixture' ).first().evaluate( el => getComputedStyle( el ).fontFamily ) ).not.toContain( 'BFA-Synthetic-Pro' );
	blockCss = false; blockFont = true;
	await page.reload();
	await expectKit( page );
	const loaded = await page.evaluate( () => document.fonts.load( '100 16px BFA-Synthetic-Pro', '\uf024' ).then( faces => faces.length ).catch( () => 0 ) );
	expect( loaded ).toBe( 0 );
	blockFont = false;
	expect( requests.every( request => ! request.auth && request.type !== 'script' ) ).toBe( true );
	await page.goto( settings );
	await saveProvider( page, 'bundled-local' );
	requests.length = 0;
	await page.reload();
	await expect( page.locator( 'link[href*="kit.fontawesome.com"]' ) ).toHaveCount( 0 );
	expect( requests ).toEqual( [] );
	await saveProvider( page, 'automatic' );
	await page.goto( settings );
	await expect( page.locator( '#bfa-pro-status' ) ).toHaveText( '' );
	await page.getByRole( 'button', { name: 'Delete token', exact: true } ).click();
	await expect( page.getByLabel( 'API Key', { exact: true } ) ).toBeVisible( { timeout: 15000 } );
	await fixture( page, '', false );
	const evidence = testInfo.outputPath( 'synthetic-performance.json' );
	fs.writeFileSync( evidence, JSON.stringify( { connectMs, payloadBytes, pickerMs, realProAssets: false } ) );
	await testInfo.attach( 'synthetic-performance', { path: evidence, contentType: 'application/json' } );
} );

test( 'token-first onboarding: names, keyboard selection, retry, stale responses and preserved connection', async ( { page, context } ) => {
	test.setTimeout( 120000 );
	await context.route( /https:\/\/(?:kit|ka-p|use)\.fontawesome\.com\//, route => route.abort() );
	page.on( 'dialog', dialog => dialog.accept() );
	await login( page );
	await fixture( page );
	await page.goto( settings );
	const token = page.getByLabel( 'API Key', { exact: true } );
	const select = page.getByRole( 'combobox', { name: 'Kit', exact: true } );
	const find = page.getByRole( 'button', { name: 'Connect account', exact: true } );
	const retry = page.getByRole( 'button', { name: 'Retry connection', exact: true } );
	const accountStatus = page.locator( '#bfa-pro-account-status' );
	await expect( token ).toHaveAttribute( 'type', 'password' );
	await expect( page.getByRole( 'link', { name: 'Get an API token (opens in a new tab)' } ) ).toHaveAttribute( 'href', 'https://fontawesome.com/account#api-tokens' );
	await expect( accountStatus ).toHaveAttribute( 'role', 'status' );
	await expect( page.locator( '#bfa-pro-kit' ) ).toHaveAttribute( 'aria-describedby', 'bfa-pro-selection-help bfa-pro-kit-warning' );
	await expect( page.getByRole( 'button', { name: 'Connect Kit', exact: true } ) ).toHaveCount( 0 );
	const operations = [];
	const responses = [];
	page.on( 'request', request => {
		if ( request.url().includes( 'admin-ajax.php' ) ) { operations.push( new URLSearchParams( request.postData() ).get( 'operation' ) ); }
	} );
	page.on( 'response', async response => {
		if ( response.url().includes( 'admin-ajax.php' ) ) { responses.push( await response.text().catch( () => '' ) ); }
	} );
	await expect( page.locator( '#bfa-provider option' ) ).toHaveText( [ 'CDN', 'Local (no CDN)', 'Font Awesome kit' ] );
	// Provider previews only toggle relevant controls, without server acquisition.
	await expect( page.locator( '#include_v4_shim' ) ).toBeHidden();
	await expect( page.locator( '#default_block_icon_style' ) ).toBeVisible();
	await page.getByRole( 'combobox', { name: 'Font Awesome source' } ).selectOption( 'automatic' );
	await expect( page.locator( '#bfa-pro-panel' ) ).toBeHidden();
	await expect( page.locator( '#bfa-provider-help' ) ).toHaveText( 'Loads free icons from a CDN and automatically updates to the latest version.' );
	await expect( page.locator( '#include_v4_shim' ) ).toBeVisible();
	await page.locator( '#bfa-provider' ).selectOption( 'bundled-local' );
	await expect( page.locator( '#bfa-provider-help' ) ).toHaveText( 'Serves bundled free icons from your site. No CDN requests or automatic icon updates.' );
	await page.getByRole( 'combobox', { name: 'Font Awesome source' } ).selectOption( 'kit-css' );
	expect( operations ).not.toContain( 'find' );
	expect( operations ).not.toContain( 'connect' );
	await expect( accountStatus ).toHaveText( '' );
	await expect( page.locator( '#bfa-pro-status' ) ).toHaveText( '' );
	await expect( page.locator( '[data-pro-action="refresh"]' ) ).toBeHidden();
	let releaseInitial;
	const initialGate = new Promise( resolve => { releaseInitial = resolve; } );
	const holdInitial = async route => {
		if ( new URLSearchParams( route.request().postData() ).get( 'operation' ) === 'find' ) { await initialGate; }
		await route.continue();
	};
	await page.route( '**/admin-ajax.php', holdInitial );
	await token.fill( 'SYNTHETIC-NOT-A-CREDENTIAL' );
	const beforeLoading = await token.boundingBox();
	await token.press( 'Enter' );
	await expect( accountStatus ).toHaveText( 'Connecting...' );
	await expect( page.locator( '#bfa-pro-spinner' ) ).toHaveClass( /is-active/ );
	const loading = await accountStatus.boundingBox();
	expect( Math.abs( loading.y + loading.height / 2 - beforeLoading.y - beforeLoading.height / 2 ) ).toBeLessThan( 5 );
	await page.screenshot( { path: test.info().outputPath( 'inline-account-loading.png' ) } );
	releaseInitial();

	await expect( page.getByText( 'API token saved', { exact: true } ) ).toBeVisible();
	await page.unroute( '**/admin-ajax.php', holdInitial );
	await expect( select ).toHaveValue( '' );
	await expect( accountStatus ).toHaveText( '' );
	await expect( page.getByText( 'API token saved', { exact: true } ) ).toBeVisible();
	await expect( token ).toBeHidden();
	await page.screenshot( { path: test.info().outputPath( 'saved-token-settings.png' ), fullPage: true } );
	await page.getByRole( 'button', { name: 'Update token', exact: true } ).click();
	await token.fill( 'SYNTHETIC-CANCELLED-EDIT' );
	await page.getByRole( 'button', { name: 'Cancel', exact: true } ).click();
	await expect( token ).toHaveValue( '' );
	await expect( token ).toBeHidden();
	await expect( select.locator( 'option' ) ).toHaveText( [ 'No kit selected', 'BFA staging (KIT_ID)', 'BFA staging (SECOND)', 'SVG Kit (unsupported)', 'Unnamed kit (UNNAMED)' ] );
	await expect( retry ).toBeHidden();
	expect( operations ).not.toContain( 'connect' );
	expect( operations ).not.toContain( 'step' );
	await select.selectOption( 'SVG_KIT' );
	await expect( page.locator( '#bfa-pro-kit-warning' ) ).toHaveText( 'Set the kit technology to Web Fonts.' );
	await page.getByRole( 'button', { name: 'Kit details', exact: true } ).click();
	await expect( page.locator( '#bfa-pro-kit-facts dd' ) ).toHaveText( [ 'Pro', 'SVG', 'v7 latest (7.3.1)', 'Enabled' ] );
	await expect( page.locator( '#bfa-pro-kit-help' ) ).toContainText( 'Use a published v7' );
	await page.screenshot( { path: test.info().outputPath( 'unsupported-kit-details.png' ), fullPage: true } );
	await expect( retry ).toBeHidden();
	expect( operations ).not.toContain( 'connect' );
	await select.focus();
	// Native select type-ahead works on both macOS and Linux Chromium.
	await select.press( 'b' );
	await expect( select ).toHaveValue( 'KIT_ID' );
	await expect( select ).toBeDisabled();
	expect( operations.filter( operation => operation === 'connect' ) ).toHaveLength( 1 );
	await expect( page.locator( '#bfa-pro-status' ) ).toContainText( 'Connected: BFA staging.', { timeout: 60000 } );
	await expect( select ).toHaveValue( 'KIT_ID' );
	await expect( select.locator( 'option:checked' ) ).toHaveText( 'BFA staging (KIT_ID) (active)' );
	await expect( select.locator( 'option[value=""]' ) ).toBeEnabled();
	await expect( page.getByRole( 'button', { name: 'Disconnect kit', exact: true } ) ).toHaveCount( 0 );
	await expect( page.locator( '#bfa-provider-help' ) ).toHaveText( 'Loads icons from Font Awesome using your Pro subscription and kit.' );
	await expect( page.locator( '#bfa-delivery-status' ) ).toHaveCount( 0 );
	await expect( page.locator( '#bfa-pro-status' ) ).toHaveClass( /screen-reader-text/ );
	await expect( page.locator( '#bfa-pro-kit-details' ) ).toBeHidden();
	const details = page.getByRole( 'button', { name: 'Kit details', exact: true } );
	const actionSpacing = await page.locator( '#bfa-pro-refresh-kits, [data-pro-action="refresh"], #bfa-pro-kit-details-toggle' ).evaluateAll( actions => actions.map( action => {
		const box = action.getBoundingClientRect();
		const icon = action.querySelector( '.dashicons, .bfa-details-caret' ).getBoundingClientRect();
		const label = action.querySelector( '.bfa-action-label' ).getBoundingClientRect();
		return { left: box.left, right: box.right, iconGap: label.left - icon.right };
	} ) );
	for ( const action of actionSpacing ) { expect( action.iconGap ).toBeCloseTo( 4, 0 ); }
	expect( actionSpacing[ 1 ].left - actionSpacing[ 0 ].right ).toBeCloseTo( 20, 0 );
	expect( actionSpacing[ 2 ].left - actionSpacing[ 1 ].right ).toBeCloseTo( 20, 0 );
	await expect( details.locator( '.bfa-details-caret' ) ).toHaveCSS( 'height', '9px' );
	const beforeDetails = operations.length;
	const selectBox = await select.boundingBox();
	const toggleBox = await details.boundingBox();
	expect( Math.abs( selectBox.y + selectBox.height / 2 - toggleBox.y - toggleBox.height / 2 ) ).toBeLessThan( 5 );
	await expect( details ).toHaveAttribute( 'aria-controls', 'bfa-pro-kit-details' );
	await details.focus();
	await details.press( 'Enter' );
	await expect( page.locator( '#bfa-pro-kit-details' ) ).toBeVisible();
	await expect( details ).toHaveAttribute( 'aria-expanded', 'true' );
	await expect( page.locator( '#bfa-pro-kit-facts dt' ) ).toHaveText( [ 'Icons', 'Technology', 'Version', 'Older version compatibility', 'Appearances' ] );
	await expect( page.locator( '#bfa-pro-kit-facts dd' ).filter( { hasNot: page.locator( 'ul' ) } ) ).toHaveText( [ 'Pro', 'Web fonts', /7\./, 'Enabled' ] );
	await expect( page.locator( '#bfa-pro-kit-facts dd li' ) ).toHaveText( [ 'Classic - Solid', 'Classic - Regular', 'Brands', 'Classic - Light', 'Classic - Thin' ] );
	const appearanceRows = await page.locator( '#bfa-pro-kit-facts dd li' ).evaluateAll( items => items.map( item => { const box = item.getBoundingClientRect(); return { top: box.top, bottom: box.bottom }; } ) );
	for ( let i = 1; i < appearanceRows.length; i++ ) { expect( appearanceRows[ i ].top ).toBeGreaterThanOrEqual( appearanceRows[ i - 1 ].bottom ); }
	await page.screenshot( { path: test.info().outputPath( 'kit-details-expanded.png' ), fullPage: true } );
	await details.press( 'Space' );
	await expect( page.locator( '#bfa-pro-kit-details' ) ).toBeHidden();
	await expect( details ).toHaveAttribute( 'aria-expanded', 'false' );
	expect( operations ).toHaveLength( beforeDetails );
	const refreshList = page.getByRole( 'button', { name: 'Refresh kits', exact: true } );
	await refreshList.hover();
	await expect( refreshList ).toHaveCSS( 'text-decoration-line', 'none' );
	await expect( refreshList.locator( '.bfa-action-label' ) ).toHaveCSS( 'text-decoration-line', 'underline' );
	await expect( refreshList.locator( '.dashicons' ) ).toHaveCSS( 'text-decoration-line', 'none' );
	await page.screenshot( { path: test.info().outputPath( 'active-kit-settings.png' ), fullPage: true } );
	// Activation reload must keep saved-token/kit rows visible before AJAX status arrives.
	let releaseStatus;
	const statusGate = new Promise( resolve => { releaseStatus = resolve; } );
	const holdStatus = async route => {
		if ( new URLSearchParams( route.request().postData() ).get( 'operation' ) === 'status' ) { await statusGate; }
		await route.continue();
	};
	await page.route( '**/admin-ajax.php', holdStatus );
	await page.reload();
	await expect( page.locator( '#bfa-pro-kit-controls' ) ).toBeVisible();
	await expect( page.getByText( 'API token saved', { exact: true } ) ).toBeVisible();
	await expect( token ).toBeHidden();
	await expect( select ).toBeDisabled();
	const waitingRow = await page.locator( '#bfa-pro-kit-controls' ).boundingBox();
	releaseStatus();
	await expect( select ).toHaveValue( 'KIT_ID' );
	await expect( select ).toBeEnabled();
	const readyRow = await page.locator( '#bfa-pro-kit-controls' ).boundingBox();
	expect( readyRow.y ).toBeCloseTo( waitingRow.y, 0 );
	await page.unroute( '**/admin-ajax.php', holdStatus );
	const connectedRequests = operations.filter( operation => operation === 'connect' ).length;
	await refreshList.click();
	await expect( page.locator( '#bfa-pro-spinner' ) ).not.toHaveClass( /is-active/ );
	await expect( select ).toHaveValue( 'KIT_ID' );
	expect( operations.filter( operation => operation === 'connect' ) ).toHaveLength( connectedRequests );
	await expect( token ).toHaveValue( '' );
	// Refresh uses the same dropdown-adjacent progress through every acquisition phase.
	await details.click();
	await expect( page.locator( '#bfa-pro-kit-details' ) ).toBeVisible();
	let releaseRefresh;
	const refreshGate = new Promise( resolve => { releaseRefresh = resolve; } );
	const refreshPhases = new Set();
	const checkRefresh = async route => {
		const operation = new URLSearchParams( route.request().postData() ).get( 'operation' );
		if ( operation === 'refresh' ) { await refreshGate; }
		if ( [ 'refresh', 'step' ].includes( operation ) ) {
			await expect( page.locator( '#bfa-pro-kit + #bfa-pro-kit-feedback' ) ).toHaveCount( 1 );
			await expect( page.locator( '#bfa-pro-kit-spinner' ) ).toHaveClass( /is-active/ );
			await expect( page.locator( '#bfa-pro-status' ) ).not.toHaveClass( /screen-reader-text/ );
			await expect( page.locator( '[data-pro-action="refresh"]' ) ).toBeDisabled();
			const response = await route.fetch();
			const result = await response.json();
			if ( result.data?.pending ) { refreshPhases.add( result.data.phase ); }
			await route.fulfill( { response } );
		} else { await route.continue(); }
	};
	await page.route( '**/admin-ajax.php', checkRefresh );
	await page.getByRole( 'button', { name: 'Refresh active kit', exact: true } ).click();
	await expect( select ).toBeDisabled();
	await expect( select ).toHaveValue( 'KIT_ID' );
	await expect( details ).toHaveAttribute( 'aria-expanded', 'false' );
	await expect( page.locator( '#bfa-pro-kit-details' ) ).toBeHidden();
	await expect( page.locator( '#bfa-pro-kit + #bfa-pro-kit-feedback' ) ).toHaveCount( 1 );
	const refreshingSelect = await select.boundingBox();
	const refreshingFeedback = await page.locator( '#bfa-pro-kit-feedback' ).boundingBox();
	expect( Math.abs( refreshingSelect.y + refreshingSelect.height / 2 - refreshingFeedback.y - refreshingFeedback.height / 2 ) ).toBeLessThan( 5 );
	await page.screenshot( { path: test.info().outputPath( 'refresh-kit-loading.png' ), fullPage: true } );
	releaseRefresh();
	await expect( select ).toBeEnabled( { timeout: 60000 } );
	await expect( page.locator( '#bfa-pro-status' ) ).toContainText( 'Connected:' );
	await expect( page.locator( '#bfa-pro-kit-spinner' ) ).not.toHaveClass( /is-active/ );
	await expect( page.locator( '[data-pro-action="refresh"]' ) ).toBeEnabled();
	expect( [ ...refreshPhases ] ).toEqual( [ 'metadata', 'icons', 'free-coverage', 'verify' ] );
	await page.unroute( '**/admin-ajax.php', checkRefresh );
	// Failed selection/retry keeps the active Kit; unsupported choices issue no request.
	await fixture( page, 'auth' );
	await page.goto( settings );
	const beforeReplacement = operations.filter( operation => operation === 'connect' ).length;
	await select.selectOption( 'SECOND' );
	await expect( page.locator( '#bfa-pro-status' ) ).toContainText( 'Authorization failed.' );
	await expect( retry ).toBeVisible();
	await expect( page.locator( 'link[href^="https://kit.fontawesome.com/KIT_ID.css"]' ) ).toHaveCount( 1 );
	await retry.click();
	await expect( retry ).toBeVisible();
	expect( operations.filter( operation => operation === 'connect' ) ).toHaveLength( beforeReplacement + 2 );
	// Failed replacement discovery does not alter the active Kit or its assets.
	await page.goto( settings );
	await page.getByRole( 'button', { name: 'Update token', exact: true } ).click();
	await token.fill( 'SYNTHETIC-BAD-REPLACEMENT' );
	await find.click();
	await expect( accountStatus ).toContainText( 'Authorization failed.' );
	await expect( page.locator( '#bfa-pro-status' ) ).toContainText( 'Connected: BFA staging.' );
	await expect( page.locator( 'link[href^="https://kit.fontawesome.com/KIT_ID.css"]' ) ).toHaveCount( 1 );
	await fixture( page, 'empty-account' );
	await page.goto( settings );
	await page.getByRole( 'button', { name: 'Refresh kits', exact: true } ).click();
	await expect( accountStatus ).toContainText( 'No kits found.' );
	await expect( select ).toHaveValue( 'KIT_ID' );
	await expect( select.locator( 'option:checked' ) ).toHaveText( 'BFA staging (active)' );
	await expect( select ).toBeEnabled();
	await expect( retry ).toBeHidden();
	await fixture( page );
	await page.goto( settings );
	// Delay the old browser response while a newer token completes discovery.
	let releaseOld;
	let oldReady;
	const ready = new Promise( resolve => { oldReady = resolve; } );
	const gate = new Promise( resolve => { releaseOld = resolve; } );
	let held = false;
	await page.route( '**/admin-ajax.php', async route => {
		if ( held || new URLSearchParams( route.request().postData() ).get( 'operation' ) !== 'find' ) { await route.continue(); return; }
		held = true;
		const response = await route.fetch();
		const body = await response.json();
		body.data.account.kits[ 0 ].name = 'Old response';
		oldReady();
		await gate;
		await route.fulfill( { response, json: body, headers: { 'x-bfa-old-response': 'yes' } } );
	} );
	await page.getByRole( 'button', { name: 'Refresh kits', exact: true } ).click();
	await expect( page.locator( '#bfa-pro-spinner' ) ).toHaveClass( /is-active/ );
	await expect( accountStatus ).toHaveText( 'Refreshing kits...' );
	expect( await page.locator( '#bfa-pro-refresh-kits' ).evaluate( el => el.nextElementSibling.id ) ).toBe( 'bfa-pro-discovery-feedback' );
	await ready;
	await page.getByRole( 'button', { name: 'Update token', exact: true } ).click();
	await token.fill( 'SYNTHETIC-NEW-AUTHORIZATION' );
	await find.click();
	await expect( page.getByText( 'API token saved', { exact: true } ) ).toBeVisible();
	const oldResponse = page.waitForResponse( response => response.headers()[ 'x-bfa-old-response' ] === 'yes' );
	releaseOld();
	await ( await oldResponse ).finished();
	await expect( select.locator( 'option' ) ).toHaveText( [ 'No kit selected', 'BFA staging (KIT_ID) (active)', 'BFA staging (SECOND)', 'SVG Kit (unsupported)', 'Unnamed kit (UNNAMED)' ] );
	await expect( select ).toHaveValue( 'KIT_ID' );
	await expect( token ).toHaveValue( '' );
	expect( operations.filter( operation => operation === 'connect' ) ).toHaveLength( beforeReplacement + 2 );
	expect( responses.join( '' ) ).not.toMatch( /SYNTHETIC-(?:NOT-A-CREDENTIAL|BAD-REPLACEMENT|NEW-AUTHORIZATION)|credential|access_token/ );
	const discoveriesBeforeDisconnect = operations.filter( operation => operation === 'find' ).length;
	await expect( page.locator( '#bfa-pro-kit-details' ) ).toBeHidden();
	// A failed unset restores the active selection and keeps its assets.
	let releaseDisconnect;
	const disconnectGate = new Promise( resolve => { releaseDisconnect = resolve; } );
	const failDisconnect = async route => {
		if ( new URLSearchParams( route.request().postData() ).get( 'operation' ) === 'disconnect-kit' ) {
			await disconnectGate;
			await route.fulfill( { status: 500, json: { success: false, data: { message: 'Synthetic disconnect failure.' } } } );
		} else { await route.continue(); }
	};
	await page.route( '**/admin-ajax.php', failDisconnect );
	await select.selectOption( '' );
	await expect( select ).toBeDisabled();
	await expect( select ).toHaveAttribute( 'aria-busy', 'true' );
	await expect( page.locator( '#bfa-pro-kit-spinner' ) ).not.toHaveClass( /is-active/ );
	await expect( page.locator( '#bfa-pro-status' ) ).toHaveClass( /screen-reader-text/ );
	await expect( page.locator( '#bfa-pro-status' ) ).toHaveText( 'Disconnecting kit...' );
	releaseDisconnect();
	await expect( page.locator( '#bfa-pro-status' ) ).toHaveText( 'Synthetic disconnect failure.' );
	await expect( retry ).toBeHidden();
	await expect( select ).toHaveValue( 'KIT_ID' );
	await expect( page.locator( 'link[href^="https://kit.fontawesome.com/KIT_ID.css"]' ) ).toHaveCount( 1 );
	await page.unroute( '**/admin-ajax.php', failDisconnect );
	await select.focus();
	await select.press( 'n' );
	await expect( page.locator( '#bfa-pro-status' ) ).toHaveText( '' );
	await expect( page.locator( '#bfa-provider' ) ).toHaveValue( 'kit-css' );
	await expect( page.locator( '#bfa-pro-panel' ) ).toBeVisible();
	await expect( page.locator( '#bfa-provider-help' ) ).toHaveText( 'Connect your Font Awesome Pro kit. Until connected, free icons load from the CDN.' );
	await expect( page.locator( 'link[href^="https://kit.fontawesome.com/"]' ) ).toHaveCount( 0 );
	await page.reload();
	await expect( page.locator( '#bfa-provider' ) ).toHaveValue( 'kit-css' );
	await expect( page.locator( '#bfa-pro-panel' ) ).toBeVisible();
	await expect( page.getByText( 'API token saved', { exact: true } ) ).toBeVisible();
	await expect( token ).toBeHidden();
	await expect( select ).toHaveValue( '' );
	await expect( select.locator( 'option[value=""]' ) ).toBeEnabled();
	const disconnectRequests = operations.filter( operation => operation === 'disconnect-kit' ).length;
	await select.selectOption( '' );
	expect( operations.filter( operation => operation === 'disconnect-kit' ) ).toHaveLength( disconnectRequests );
	await select.selectOption( 'KIT_ID' );
	await expect( page.locator( '#bfa-pro-status' ) ).toContainText( 'Connected:', { timeout: 60000 } );
	expect( operations.filter( operation => operation === 'find' ) ).toHaveLength( discoveriesBeforeDisconnect );
	expect( await page.evaluate( () => JSON.stringify( window.bfaPro ) ) ).not.toMatch( /token|credential/i );
	await page.getByRole( 'button', { name: 'Delete token', exact: true } ).click();
	await expect( token ).toBeVisible( { timeout: 15000 } );
	await fixture( page, '', false );
} );

test( 'family appearances: defaults, iframe, saved rendering and Classic/hybrid insertion', async ( { page, context } ) => {
	test.setTimeout( 120000 );
	// Public Free font stands in for transport only; this does not prove real Pro glyphs.
	const familyCss = css + '.fad,.fasdt,.fausb{font-family:BFA-Synthetic-Pro!important}.fad:before,.fasdt:before,.fausb:before{content:"\\f024"}';
	await context.route( 'https://kit.fontawesome.com/**', route => route.fulfill( { contentType: 'text/css', headers: { 'access-control-allow-origin': '*' }, body: familyCss } ) );
	await context.route( 'https://ka-p.fontawesome.com/**', route => route.fulfill( { contentType: 'font/woff2', headers: { 'access-control-allow-origin': '*' }, body: font } ) );
	page.on( 'dialog', dialog => dialog.accept() );
	await login( page );
	await fixture( page, '', true, true );
	await page.goto( settings );
	await page.getByLabel( 'API Key', { exact: true } ).fill( 'SYNTHETIC-NOT-A-CREDENTIAL' );
	await page.getByRole( 'button', { name: 'Connect account', exact: true } ).click();
	await expect( page.getByText( 'API token saved', { exact: true } ) ).toBeVisible();
	await page.getByLabel( 'Kit', { exact: true } ).selectOption( 'KIT_ID' );
	await expect( page.locator( '#bfa-pro-status' ) ).toContainText( 'Connected:', { timeout: 60000 } );
	const defaults = page.getByRole( 'combobox', { name: 'Default icon appearance', exact: true } );
	await expect( defaults.locator( 'option[value="duotone-solid"]' ) ).toHaveText( 'Duotone - Solid' );
	await expect( defaults.locator( 'option[value="utility-semibold"]' ) ).toHaveText( 'Utility - Semibold' );
	await defaults.selectOption( 'duotone-solid' );
	await page.getByText( 'Save Settings', { exact: true } ).click();
	await expect( page.locator( '.bfa-ajax-response-holder' ) ).toContainText( 'Settings saved.' );
	await page.goto( '/wp-admin/post-new.php?post_type=bfa_iframe_test' );
	await page.waitForFunction( () => window.wp?.blocks?.getBlockType( 'better-font-awesome/icon' ) );
	await page.evaluate( () => {
		wp.data.dispatch( 'core/editor' ).editPost( { title: 'Synthetic family acceptance' } );
		wp.data.dispatch( 'core/block-editor' ).resetBlocks( [ 'site-default', 'solid', 'duotone-solid', 'sharp-duotone-thin', 'utility-semibold' ].map( iconStyle => wp.blocks.createBlock( 'better-font-awesome/icon', { iconName: 'pro-fixture', iconStyle } ) ) );
	} );
	const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
	await expect( canvas.locator( '.fad.fa-pro-fixture' ) ).toHaveCount( 2 );
	await expect( canvas.locator( '.fas.fa-pro-fixture' ) ).toHaveCount( 1 );
	await expect( canvas.locator( '.fasdt.fa-pro-fixture' ) ).toHaveCount( 1 );
	await expect( canvas.locator( '.fausb.fa-pro-fixture' ) ).toHaveCount( 1 );
	await expectKit( canvas );
	const welcome = page.getByRole( 'button', { name: 'Close', exact: true } );
	if ( await welcome.isVisible() ) { await welcome.click(); }
	await page.evaluate( () => {
		wp.data.dispatch( 'core/block-editor' ).selectBlock( wp.data.select( 'core/block-editor' ).getBlocks()[ 0 ].clientId );
		wp.data.dispatch( 'core/edit-post' ).openGeneralSidebar( 'edit-post/block' );
	} );
	const appearance = page.getByRole( 'combobox', { name: 'Appearance', exact: true } );
	await expect( appearance ).toHaveValue( 'site-default' );
	await expect( appearance.locator( 'option[value="site-default"]' ) ).toHaveText( 'Site default (Duotone - Solid)' );
	await appearance.selectOption( 'sharp-duotone-thin' );
	await expect( canvas.locator( '.fasdt.fa-pro-fixture' ) ).toHaveCount( 2 );
	await appearance.selectOption( 'site-default' );
	await page.evaluate( () => wp.data.dispatch( 'core/editor' ).savePost() );
	const postId = await page.evaluate( () => wp.data.select( 'core/editor' ).getCurrentPostId() );
	await page.reload();
	await expect( canvas.locator( '.fad.fa-pro-fixture' ) ).toHaveCount( 2 );
	await page.goto( `/?bfa_pro_preview=${ postId }` );
	await expect( page.locator( '.fad.fa-pro-fixture' ) ).toHaveCount( 2 );
	await expect( page.locator( '.fasdt.fa-pro-fixture' ) ).toHaveCount( 1 );
	await expectKit( page );
	for ( const postType of [ 'bfa_classic_test', 'post' ] ) {
		await page.goto( `/wp-admin/post-new.php?post_type=${ postType }` );
		const id = postType === 'post' ? 'bfa_hybrid_editor' : 'content';
		await page.waitForFunction( id => window.tinymce?.get( id )?.initialized, id );
		if ( await welcome.isVisible() ) { await welcome.click(); }
		await expectKit( page.frameLocator( `#${ id }_ifr` ) );
		await page.locator( '.bfa-iconpicker .iconpicker-component' ).first().click();
		await page.locator( '.iconpicker-search' ).filter( { visible: true } ).first().fill( 'pro-fixture' );
		await page.locator( '.iconpicker-item:visible .fad.fa-pro-fixture' ).first().click();
		expect( await page.evaluate( id => tinymce.get( id ).getContent(), id ) ).toContain( 'style="duotone-solid"' );
	}
	await page.goto( settings );
	await saveProvider( page, 'bundled-local' );
	await page.goto( `/?bfa_pro_preview=${ postId }` );
	await expect( page.locator( 'link[href*="kit.fontawesome.com"]' ) ).toHaveCount( 0 );
	await expect( page.locator( '.fasdt.fa-pro-fixture' ) ).toHaveCount( 1 );
	await expect( page.locator( '.fausb.fa-pro-fixture' ) ).toHaveCount( 1 );
	await page.goto( settings );
	await saveProvider( page, 'automatic' );
	await page.goto( settings );
	await page.getByRole( 'button', { name: 'Delete token', exact: true } ).click();
	await expect( page.getByLabel( 'API Key', { exact: true } ) ).toBeVisible();
	await fixture( page, '', false );
} );

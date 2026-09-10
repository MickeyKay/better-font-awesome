import { expect, test } from '@playwright/test';
import fs from 'node:fs';

const settings = '/wp-admin/options-general.php?page=better-font-awesome';
const font = fs.readFileSync( new URL( '../../vendor/mickey-kay/better-font-awesome-library/inc/font-awesome-7-fallback/webfonts/fa-solid-900.woff2', import.meta.url ) );
const css = `@font-face{font-family:BFA-Synthetic-Pro;src:url(https://ka-p.fontawesome.com/synthetic.woff2) format('woff2');font-weight:100 900;font-style:normal;font-display:block}.fa,.fas,.far,.fal,.fat,.fab{font-family:BFA-Synthetic-Pro!important}.fa,.fas{font-weight:900}.far,.fab{font-weight:400}.fal{font-weight:300}.fat{font-weight:100}.fa:before,.fas:before,.far:before,.fal:before,.fat:before,.fab:before{content:'\\f024'}`;

async function login( page ) {
	await page.goto( '/wp-login.php' );
	await page.request.post( '/wp-login.php', { form: { log: 'admin', pwd: 'password', testcookie: '1' } } );
	await page.goto( '/wp-admin/' );
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();
}
async function fixture( page, fault = '', enabled = true ) {
	await page.goto( '/wp-admin/tools.php?page=bfa-pro-fixture' );
	await page.getByLabel( 'Enable synthetic API' ).setChecked( enabled );
	await page.getByLabel( 'Full-sized synthetic catalog' ).check();
	await page.locator( 'select[name="fault"]' ).selectOption( fault );
	await page.getByRole( 'button', { name: 'Save fixture' } ).click();
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
	await login( page );
	await fixture( page );
	await page.goto( settings );
	await page.getByLabel( 'Serve Font Awesome locally', { exact: true } ).uncheck();
	await page.getByText( 'Save Settings', { exact: true } ).click();
	await expect( page.locator( '.bfa-ajax-response-holder' ) ).toContainText( 'Settings saved.' );
	await page.reload();
	await page.getByLabel( 'Kit identifier', { exact: true } ).fill( 'KIT_ID' );
	await page.getByLabel( 'Account API token', { exact: true } ).fill( 'SYNTHETIC-NOT-A-CREDENTIAL' );
	const started = Date.now();
	await page.getByRole( 'button', { name: 'Connect Kit', exact: true } ).click();
	await expect( page.locator( '#bfa-pro-status' ) ).toContainText( 'Connected:', { timeout: 60000 } );
	const connectMs = Date.now() - started;
	await expect( page.getByLabel( 'Account API token', { exact: true } ) ).toHaveValue( '' );
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
	const styleControl = page.getByRole( 'combobox', { name: 'Style', exact: true } );
	await expect( styleControl.locator( 'option' ) ).toHaveText( [ 'Site default (Thin)', 'Solid', 'Regular', 'Light', 'Thin' ] );
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
	await page.getByRole( 'button', { name: 'Refresh Kit', exact: true } ).click();
	await expect( page.locator( '#bfa-pro-status' ) ).toContainText( 'Authorization failed' );
	await expectKit( page );
	await fixture( page );
	await page.goto( settings );
	await page.getByRole( 'button', { name: 'Refresh Kit', exact: true } ).click();
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
	await page.getByLabel( 'Serve Font Awesome locally', { exact: true } ).check();
	await page.getByText( 'Save Settings', { exact: true } ).click();
	await expect( page.locator( '.bfa-ajax-response-holder' ) ).toContainText( 'Settings saved.' );
	requests.length = 0;
	await page.reload();
	await expect( page.locator( 'link[href*="kit.fontawesome.com"]' ) ).toHaveCount( 0 );
	expect( requests ).toEqual( [] );
	await page.getByRole( 'button', { name: 'Use automatic Free', exact: true } ).click();
	await expect( page.getByLabel( 'Serve Font Awesome locally', { exact: true } ) ).not.toBeChecked();
	await expect( page.locator( '#bfa-pro-form' ) ).toHaveAttribute( 'aria-busy', 'false' );
	await page.getByRole( 'button', { name: 'Disconnect and forget Kit', exact: true } ).click();
	await expect( page.getByLabel( 'Kit identifier', { exact: true } ) ).toHaveValue( '' );
	await fixture( page, '', false );
	const evidence = testInfo.outputPath( 'synthetic-performance.json' );
	fs.writeFileSync( evidence, JSON.stringify( { connectMs, payloadBytes, pickerMs, realProAssets: false } ) );
	await testInfo.attach( 'synthetic-performance', { path: evidence, contentType: 'application/json' } );
} );

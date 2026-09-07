import { expect, test } from '@playwright/test';

const glyphs = [
	{ classes: 'fas fa-flag', family: 'Font Awesome 7 Free', weight: '900', glyph: '\uf024' },
	{ classes: 'far fa-heart', family: 'Font Awesome 7 Free', weight: '400', glyph: '\uf004' },
	{ classes: 'fab fa-github', family: 'Font Awesome 7 Brands', weight: '400', glyph: '\uf09b' },
	{ classes: 'fa fa-flag', family: 'FontAwesome', weight: '400', glyph: '\uf024' },
	{ classes: 'fa fa-map-marker', family: 'FontAwesome', weight: '400', glyph: '\uf041' },
	{ classes: 'fas fa-flag', family: 'Font Awesome 5 Free', weight: '900', glyph: '\uf024' },
];
const sampleMarkup = `<div id="bfa-local-samples">${ glyphs.map( ( item, index ) =>
	`<i data-sample="${ index }" class="${ item.classes }" style="font-family:'${ item.family }';font-weight:${ item.weight };font-size:32px">&#8203;</i>`
).join( ' ' ) }</div>`;

async function expectLocalGlyphs( frame ) {
	await expect( frame.locator( '#bfa-local-samples' ) ).toBeVisible();
	const results = await frame.evaluate( async ( samples ) => {
		const results = [];
		for ( const [ index, sample ] of samples.entries() ) {
			const element = document.querySelector( `[data-sample="${ index }"]` );
			const style = getComputedStyle( element, '::before' );
			const font = `${ sample.weight } 32px "${ sample.family }"`;
			const faces = await document.fonts.load( font, sample.glyph );
			const raster = ( text ) => {
				const canvas = document.createElement( 'canvas' );
				canvas.width = 64;
				canvas.height = 64;
				const context = canvas.getContext( '2d' );
				context.font = font;
				context.fillText( text, 4, 40 );
				return canvas.toDataURL();
			};
			results.push( {
				content: style.content,
				loaded: faces.filter( ( face ) => 'loaded' === face.status ).length,
				rendersGlyph: raster( sample.glyph ) !== raster( '\u{10ffff}' ),
				width: element.getBoundingClientRect().width,
			} );
		}
		return results;
	}, glyphs );
	for ( const result of results ) {
		expect( result.content ).not.toMatch( /^(none|normal|"")$/ );
		expect( result.loaded ).toBeGreaterThan( 0 );
		expect( result.rendersGlyph ).toBe( true );
		expect( result.width ).toBeGreaterThan( 0 );
	}

	// Inspect nested CSS font URLs, including styles in TinyMCE and WP's canvas.
	const fontUrls = await frame.evaluate( () => {
		const urls = [];
		const inspect = ( rules, base ) => {
			for ( const rule of rules ) {
				if ( rule.cssRules ) {
					inspect( rule.cssRules, base );
				}
				if ( rule.type === CSSRule.FONT_FACE_RULE && /FontAwesome|Font Awesome/.test( rule.cssText ) ) {
					for ( const match of rule.cssText.matchAll( /url\(["']?([^"')]+)["']?\)/g ) ) {
						urls.push( new URL( match[ 1 ], base ).href );
					}
				}
			}
		};
		for ( const sheet of document.styleSheets ) {
			try {
				inspect( sheet.cssRules, sheet.href || document.baseURI );
			} catch {
				// Unrelated cross-origin styles are outside BFA's delivery setting.
			}
		}
		return urls;
	} );
	expect( fontUrls.length ).toBeGreaterThan( 0 );
	const origin = await frame.evaluate( () => window.top.location.origin );
	for ( const url of fontUrls ) {
		expect( new URL( url ).origin ).toBe( origin );
		expect( new URL( url ).pathname ).toContain( '/inc/font-awesome-7-fallback/webfonts/' );
	}
}

async function saveMode( page, mode, beforeSave = () => {}, nativeSubmit = false ) {
	await page.goto( '/wp-admin/options-general.php?page=better-font-awesome' );
	const checkbox = page.getByLabel( 'Serve Font Awesome locally', { exact: true } );
	if ( await checkbox.isChecked() !== ( 'bundled-local' === mode ) ) {
		await checkbox.focus();
		await checkbox.press( 'Space' );
	}
	await page.locator( '#include_v4_shim' ).check();
	beforeSave();
	await Promise.all( [
		page.waitForEvent( 'load' ),
		nativeSubmit
			? page.locator( '#bfa-settings-form' ).evaluate( ( form ) => form.requestSubmit() )
			: page.locator( '.bfa-save-settings-button' ).click(),
	] );
	await expect( page.locator( '#asset_delivery' ) ).toBeChecked( { checked: 'bundled-local' === mode } );
	await page.reload();
	await expect( page.locator( '#asset_delivery' ) ).toBeChecked( { checked: 'bundled-local' === mode } );
	await expect( page.locator( '#bfa-delivery-help' ) ).toHaveText( 'Load icons from your site instead of a third-party CDN. New icons arrive through plugin updates.' );
	await expect( page.locator( '#bfa-delivery-status' ) ).toHaveCount( 0 );
}

test( 'local delivery renders real fonts with third-party requests blocked across all editor surfaces', async ( { page, context } ) => {
	test.setTimeout( 120000 );
	page.setDefaultTimeout( 15000 );
	page.on( 'dialog', ( dialog ) => 'beforeunload' === dialog.type() ? dialog.accept() : dialog.dismiss() );
	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( 'admin' );
	await page.locator( '#user_pass' ).fill( 'password' );
	await page.locator( '#wp-submit' ).click();
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();

	const attempted = [];
	let localSelected = false;
	const failedFonts = [];
	const loadedFonts = [];
	const remoteFontAwesome = /^https?:\/\/(?:[^/]*\.)?(?:fontawesome\.com|cdnjs\.cloudflare\.com|cdn\.jsdelivr\.net|registry\.npmjs\.org)\//;
	await context.route( remoteFontAwesome, ( route ) => {
		if ( localSelected ) {
			attempted.push( route.request().url() );
		}
		return route.abort();
	} );
	page.on( 'requestfailed', ( request ) => {
		// Leaving an editor can cancel an obsolete document's pending font fetch.
		// Each tested document must still load and render its real font faces.
		if ( /\/webfonts\/.*\.woff2/.test( request.url() ) && 'net::ERR_ABORTED' !== request.failure()?.errorText ) {
			failedFonts.push( { url: request.url(), error: request.failure()?.errorText } );
		}
	} );
	page.on( 'response', ( response ) => {
		if ( /\/webfonts\/.*\.woff2/.test( response.url() ) ) {
			( response.ok() ? loadedFonts : failedFonts ).push( response.url() );
		}
	} );

	// Exercise options.php with checked and omitted checkbox values before the AJAX path.
	await saveMode( page, 'bundled-local', () => {}, true );
	await saveMode( page, 'automatic', () => {}, true );
	await saveMode( page, 'bundled-local', () => { localSelected = true; } );
	await test.info().attach( 'local-delivery-settings', { body: await page.screenshot(), contentType: 'image/png' } );
	await page.goto( '/wp-admin/post-new.php?post_type=bfa_iframe_test' );
	await page.waitForFunction( () => Boolean( window.wp?.blocks?.getBlockType( 'better-font-awesome/icon' ) ) );
	const welcome = page.getByRole( 'button', { name: 'Close', exact: true } );
	if ( await welcome.isVisible() ) {
		await welcome.click();
	}
	await page.evaluate( () => {
		window.wp.data.dispatch( 'core/block-editor' ).insertBlocks( [
			window.wp.blocks.createBlock( 'better-font-awesome/icon', { iconName: 'flag' } ),
			window.wp.blocks.createBlock( 'better-font-awesome/icon', { iconName: 'heart', iconStyle: 'regular' } ),
			window.wp.blocks.createBlock( 'better-font-awesome/icon', { iconName: 'github', iconStyle: 'brands' } ),
		] );
	} );
	await expect( page.locator( 'iframe[name="editor-canvas"]' ) ).toBeAttached();
	const canvas = page.frame( { name: 'editor-canvas' } );
	for ( const selector of [ '.fas.fa-flag', '.far.fa-heart', '.fab.fa-github' ] ) {
		await expect( canvas.locator( selector ).first() ).toBeVisible();
	}
	await canvas.evaluate( ( markup ) => document.body.insertAdjacentHTML( 'beforeend', markup ), sampleMarkup );
	await expectLocalGlyphs( canvas );
	await test.info().attach( 'local-native-editor', { body: await page.screenshot(), contentType: 'image/png' } );
	const inlineStyles = await page.evaluate( () => window.wp.data.select( 'core/block-editor' ).getSettings().styles );
	const fontStyles = inlineStyles.filter( ( style ) => /@font-face/.test( style.css ) && /Font Awesome/.test( style.css ) );
	expect( fontStyles.length ).toBeGreaterThan( 0 );
	for ( const style of fontStyles ) {
		expect( style.baseURL ).toContain( '/inc/font-awesome-7-fallback/css/' );
		expect( new URL( style.baseURL, page.url() ).origin ).toBe( new URL( page.url() ).origin );
	}

	const content = `<!-- wp:better-font-awesome/icon {"iconName":"flag"} /-->\n<!-- wp:shortcode -->[icon name="heart" style="regular"] [icon name="github" style="brands"]<!-- /wp:shortcode -->\n<!-- wp:html -->${ sampleMarkup }<!-- /wp:html -->`;
	const post = await page.evaluate( async ( content ) => window.wp.apiFetch( {
		path: '/wp/v2/posts', method: 'POST', data: { title: 'Local delivery browser fixture', content, status: 'publish' },
	} ), content );
	try {
		await page.goto( post.link );
		await expectLocalGlyphs( page );
		await test.info().attach( 'local-frontend', { body: await page.screenshot(), contentType: 'image/png' } );
		await expect( page.locator( '.wp-block-better-font-awesome-icon .fas.fa-flag' ) ).toBeVisible();

		for ( const [ path, editorId ] of [
			[ '/wp-admin/post-new.php?post_type=bfa_classic_test', 'content' ],
			[ '/wp-admin/post-new.php', 'bfa_hybrid_editor' ],
		] ) {
			await page.goto( path );
			const close = page.getByRole( 'button', { name: 'Close', exact: true } );
			if ( await close.isVisible() ) {
				await close.click();
			}
			await page.waitForFunction( ( id ) => Boolean( window.tinymce?.get( id )?.initialized ), editorId );
			const metaBoxesToggle = page.getByRole( 'button', { name: 'Meta Boxes', exact: true } );
			if ( await metaBoxesToggle.count() && 'false' === await metaBoxesToggle.getAttribute( 'aria-expanded' ) ) {
				await metaBoxesToggle.focus();
				await metaBoxesToggle.press( 'Enter' );
			}
			const picker = page.locator( '.bfa-iconpicker' ).last();
			await picker.scrollIntoViewIfNeeded();
			await picker.locator( 'button.iconpicker-component' ).click();
			await picker.locator( '.iconpicker-search' ).fill( 'flag' );
			await picker.locator( '.iconpicker-item[title*="Flag"]' ).first().click();
			await page.waitForFunction( ( id ) => window.tinymce.get( id ).getContent().includes( '[icon' ), editorId );
			await page.evaluate( ( { editorId, sampleMarkup } ) => window.tinymce.get( editorId ).setContent( sampleMarkup ), { editorId, sampleMarkup } );
			await expectLocalGlyphs( page.frame( { name: `${ editorId }_ifr` } ) );
			await test.info().attach( `local-${ editorId }`, { body: await page.screenshot(), contentType: 'image/png' } );
		}
		expect( attempted ).toEqual( [] );
		expect( failedFonts ).toEqual( [] );
		for ( const name of [ 'fa-solid-900.woff2', 'fa-regular-400.woff2', 'fa-brands-400.woff2', 'fa-v4compatibility.woff2' ] ) {
			expect( loadedFonts.some( ( url ) => url.includes( name ) ) ).toBe( true );
		}
	} finally {
		await saveMode( page, 'automatic' );
		await page.goto( '/wp-admin/post-new.php?post_type=bfa_iframe_test' );
		await page.waitForFunction( () => Boolean( window.wp?.apiFetch ) );
		await page.evaluate( async ( id ) => window.wp.apiFetch( { path: `/wp/v2/posts/${ id }?force=true`, method: 'DELETE' } ), post.id );
	}
} );

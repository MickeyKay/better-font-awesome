import { expect, test } from '@playwright/test';

const fontAwesomeStylesheetPattern =
	/\/vendor\/mickey-kay\/better-font-awesome-library\/inc\/font-awesome-7-fallback\/css\/all\.min\.css(?:\?.*)?$/;
const fontAwesomeWebfontPattern = /\/webfonts\/[^/?]+\.woff2(?:[?#].*)?$/;
const metadataRequestPattern =
	/^https:\/\/(?:api\.fontawesome\.com|registry\.npmjs\.org|cdn\.jsdelivr\.net)\//;

const controlledBoundaryClass = 'bfa-e2e-justification-boundary';
const controlledBoundaryCss = `
.${ controlledBoundaryClass } {
	border: 4px solid transparent !important;
	box-sizing: border-box !important;
	inline-size: 640px !important;
	margin-inline: 0 !important;
	max-inline-size: 640px !important;
	padding-inline: 40px 56px !important;
}

.${ controlledBoundaryClass } .wp-block-better-font-awesome-icon {
	margin-inline: 0 !important;
	max-inline-size: none !important;
}
`;

async function getElementGeometry( locator ) {
	return locator.evaluate( ( element ) => {
		const rect = element.getBoundingClientRect();
		const style = window.getComputedStyle( element );
		const pixels = ( value ) => Number.parseFloat( value ) || 0;
		const borderLeft = pixels( style.borderLeftWidth );
		const borderRight = pixels( style.borderRightWidth );
		const paddingLeft = pixels( style.paddingLeft );
		const paddingRight = pixels( style.paddingRight );

		return {
			borderLeft: rect.left,
			borderRight: rect.right,
			borderWidth: rect.width,
			contentLeft: rect.left + borderLeft + paddingLeft,
			contentRight: rect.right - borderRight - paddingRight,
			contentWidth:
				rect.width -
				borderLeft -
				borderRight -
				paddingLeft -
				paddingRight,
			tolerance: 1 / window.devicePixelRatio,
		};
	} );
}

async function expectControlledBoundary( boundary ) {
	const geometry = await getElementGeometry( boundary );

	expect( Math.abs( geometry.borderWidth - 640 ) ).toBeLessThanOrEqual(
		geometry.tolerance
	);
	expect( Math.abs( geometry.contentWidth - 536 ) ).toBeLessThanOrEqual(
		geometry.tolerance
	);
}

async function expectWrapperFillsBoundary( block, boundary ) {
	const [ blockGeometry, boundaryGeometry ] = await Promise.all( [
		getElementGeometry( block ),
		getElementGeometry( boundary ),
	] );
	const tolerance = Math.max(
		blockGeometry.tolerance,
		boundaryGeometry.tolerance
	);

	expect(
		Math.abs( blockGeometry.borderLeft - boundaryGeometry.contentLeft ),
		'wrapper left edge should match the controlled boundary content edge'
	).toBeLessThanOrEqual( tolerance );
	expect(
		Math.abs( blockGeometry.borderRight - boundaryGeometry.contentRight ),
		'wrapper right edge should match the controlled boundary content edge'
	).toBeLessThanOrEqual( tolerance );
	expect(
		Math.abs( blockGeometry.borderWidth - boundaryGeometry.contentWidth ),
		'wrapper should occupy the controlled boundary content width'
	).toBeLessThanOrEqual( tolerance );
}

async function expectIconPosition( block, icon, justification ) {
	const [ blockGeometry, iconGeometry ] = await Promise.all( [
		getElementGeometry( block ),
		getElementGeometry( icon ),
	] );
	const tolerance = Math.max(
		blockGeometry.tolerance,
		iconGeometry.tolerance
	);
	expect( iconGeometry.borderLeft ).toBeGreaterThanOrEqual(
		blockGeometry.contentLeft - tolerance
	);
	expect( iconGeometry.borderRight ).toBeLessThanOrEqual(
		blockGeometry.contentRight + tolerance
	);

	if ( 'left' === justification ) {
		expect(
			Math.abs( iconGeometry.borderLeft - blockGeometry.contentLeft )
		).toBeLessThanOrEqual( tolerance );
	} else if ( 'center' === justification ) {
		expect(
			Math.abs(
				blockGeometry.contentLeft + blockGeometry.contentWidth / 2 -
					( iconGeometry.borderLeft + iconGeometry.borderWidth / 2 )
			)
		).toBeLessThanOrEqual( tolerance );
	} else {
		expect(
			Math.abs( blockGeometry.contentRight - iconGeometry.borderRight )
		).toBeLessThanOrEqual( tolerance );
	}
}

async function expectBlockWithin( block, boundary ) {
	const [ blockGeometry, boundaryGeometry ] = await Promise.all( [
		getElementGeometry( block ),
		getElementGeometry( boundary ),
	] );
	const tolerance = Math.max(
		blockGeometry.tolerance,
		boundaryGeometry.tolerance
	);
	expect( blockGeometry.borderLeft ).toBeGreaterThanOrEqual(
		boundaryGeometry.contentLeft - tolerance
	);
	expect( blockGeometry.borderRight ).toBeLessThanOrEqual(
		boundaryGeometry.contentRight + tolerance
	);
}

async function loadFontAwesomeFaces( frame ) {
	return frame.evaluate( async () => {
		const faces = [
			[ '900 16px "Font Awesome 7 Free"', '\uf024' ],
			[ '400 16px "Font Awesome 7 Free"', '\uf004' ],
			[ '400 16px "Font Awesome 7 Brands"', '\uf09b' ],
		];

		return Promise.all(
			faces.map( async ( [ font, glyph ] ) => {
				try {
					const loaded = await document.fonts.load( font, glyph );
					return { font, loaded: loaded.length, status: 'fulfilled' };
				} catch ( error ) {
					return { font, message: error.message, status: 'rejected' };
				}
			} )
		);
	} );
}

async function expectIntegrityStyles( root ) {
	const styles = root.locator(
		'link[id^="bfa-font-awesome"][rel="stylesheet"]'
	);
	await expect( styles.first() ).toBeAttached();
	const count = await styles.count();
	expect( count ).toBeGreaterThanOrEqual( 2 );

	for ( let index = 0; index < count; index++ ) {
		const style = styles.nth( index );
		await expect( style ).toHaveAttribute( 'crossorigin', 'anonymous' );
		await expect( style ).toHaveAttribute(
			'integrity',
			/^sha(?:256|384|512)-/
		);
	}
}

async function expectPickerInsertsAquarius( page, editorId ) {
	await page.waitForFunction(
		( id ) => Boolean( window.tinymce?.get( id )?.initialized ),
		editorId
	);
	const picker = page.locator( '.bfa-iconpicker' ).last();
	await expect( picker ).toBeVisible();
	await picker.locator( 'button.iconpicker-component' ).click();
	const search = picker.locator( '.iconpicker-search' );
	await search.fill( 'aquarius' );
	const item = picker.locator( '.iconpicker-item[title*="Aquarius"]' ).first();
	await expect( item ).toBeVisible();
	const glyph = await item.evaluate( ( element ) => {
		const icon = element.querySelector( 'i' ) ?? element;
		const style = window.getComputedStyle( icon, '::before' );
		return { content: style.content, fontFamily: style.fontFamily };
	} );
	expect( glyph.content ).not.toBe( 'none' );
	expect( glyph.content ).not.toBe( 'normal' );
	expect( glyph.fontFamily ).toContain( 'Font Awesome' );
	await item.click();
	await page.waitForFunction(
		( id ) => window.tinymce.get( id ).getContent().includes( 'name="aquarius"' ),
		editorId
	);
}

async function expectTinyMceFontAwesome( page, editorId ) {
	const result = await page.evaluate(
		( id ) => {
			const editor = window.tinymce.get( id );
			const document = editor.getBody().ownerDocument;
			const links = Array.from( document.querySelectorAll( 'link[rel="stylesheet"]' ) ).map(
				( link ) => link.href
			);
			return { links };
		},
		editorId
	);

	expect( result.links ).toEqual(
		expect.arrayContaining( [
			expect.stringMatching( /\/css\/all\.min\.css(?:[?#].*)?$/ ),
			expect.stringMatching( /\/css\/v5-font-face\.min\.css(?:[?#].*)?$/ ),
		] )
	);
}

async function setIconJustification( page, clientId, justification ) {
	await page.evaluate( ( selectedClientId ) => {
		window.wp.data.dispatch( 'core/block-editor' ).selectBlock( selectedClientId );
	}, clientId );

	const toolbar = page.locator( '.block-editor-block-toolbar' );
	await toolbar
		.getByRole( 'button', { name: 'Change items justification' } )
		.click();
	await page
		.getByRole( 'menuitem', { name: `Justify items ${ justification }` } )
		.click();

	await expect
		.poll( () =>
			page.evaluate( ( selectedClientId ) => {
				return window.wp.data
					.select( 'core/block-editor' )
					.getBlock( selectedClientId ).attributes.iconJustification;
			}, clientId )
		)
		.toBe( justification );
}

async function dismissWelcomeModal( page ) {
	const welcomeModal = page
		.locator( '.components-modal__screen-overlay' )
		.filter( { hasText: /Welcome to the (?:block )?editor/ } );
	try {
		await welcomeModal.waitFor( { state: 'visible', timeout: 2000 } );
		await welcomeModal.getByRole( 'button', { name: 'Close' } ).click();
	} catch ( error ) {
		if ( ! /Timeout/.test( error.message ) ) {
			throw error;
		}
	}
}

test( 'loads FA7 assets inside the strict block editor iframe', async ( {
	page,
} ) => {
	const fontAwesomeErrors = [];
	const rootWebfontFailures = [];
	const isRootWebfontRequest = ( url ) =>
		/^\/webfonts\/[^/]+\.woff2$/.test( new URL( url ).pathname );

	page.on( 'requestfailed', ( request ) => {
		if ( isRootWebfontRequest( request.url() ) ) {
			rootWebfontFailures.push( {
				error: request.failure()?.errorText ?? 'request failed',
				url: request.url(),
			} );
		}
	} );
	page.on( 'response', ( response ) => {
		if ( isRootWebfontRequest( response.url() ) && response.status() >= 400 ) {
			rootWebfontFailures.push( {
				error: `HTTP ${ response.status() }`,
				url: response.url(),
			} );
		}
	} );
	page.on( 'console', ( message ) => {
		const text = message.text();
		if (
			/font awesome|fontawesome|better font awesome|\bbfa\b|\bbfal\b|cors/i.test(
				text
			) && 'error' === message.type()
		) {
			fontAwesomeErrors.push( text );
		}
	} );

	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( 'admin' );
	await page.locator( '#user_pass' ).fill( 'password' );
	await page.locator( '#wp-submit' ).click();
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();

	await page.goto( '/wp-admin/post-new.php?post_type=bfa_iframe_test' );
	await page.waitForFunction( () => {
		return Boolean( window.wp?.blocks?.getBlockType( 'better-font-awesome/icon' ) );
	} );
	await dismissWelcomeModal( page );

	const canvas = page.locator( 'iframe[name="editor-canvas"]' );
	await expect( canvas ).toBeAttached();
	const editorFrame = page.frame( { name: 'editor-canvas' } );
	expect( editorFrame ).not.toBeNull();
	expect(
		await editorFrame.evaluate( () => window.frameElement?.name )
	).toBe( 'editor-canvas' );

	const blocks = await page.evaluate( () => {
		const solid = window.wp.blocks.createBlock( 'better-font-awesome/icon', {
			iconName: 'flag',
			iconStyle: 'solid',
		} );
		const regular = window.wp.blocks.createBlock( 'better-font-awesome/icon', {
			iconName: 'heart',
			iconStyle: 'regular',
		} );
		const brand = window.wp.blocks.createBlock( 'better-font-awesome/icon', {
			iconName: 'github',
			iconStyle: 'brands',
		} );
		window.wp.data
			.dispatch( 'core/block-editor' )
			.insertBlocks( [ solid, regular, brand ] );

		return {
			brand: brand.clientId,
			regular: regular.clientId,
			solid: solid.clientId,
		};
	} );

	const editor = page.frameLocator( 'iframe[name="editor-canvas"]' );
	await expectIntegrityStyles( editor );
	const iconSelectors = {
		brand: '.fab.fa-github',
		regular: '.far.fa-heart',
		solid: '.fas.fa-flag',
	};
	for ( const [ style, selector ] of Object.entries( iconSelectors ) ) {
		const icon = editor.locator(
			`[data-block="${ blocks[ style ] }"] ${ selector }`
		);
		await expect( icon ).toBeVisible();
		const glyph = await icon.evaluate( ( element ) => {
			const computed = window.getComputedStyle( element, '::before' );
			return {
				content: computed.content,
				fontFamily: computed.fontFamily,
			};
		} );
		expect( glyph.content ).not.toBe( 'none' );
		expect( glyph.content ).not.toBe( 'normal' );
		expect( glyph.fontFamily ).toContain( 'Font Awesome' );
	}

	const loadedFaces = await loadFontAwesomeFaces( editorFrame );
	for ( const face of loadedFaces ) {
		expect( face.status ).toBe( 'fulfilled' );
		expect( face.loaded ).toBeGreaterThan( 0 );
	}

	const exactStyleMatches = await editorFrame.evaluate( async () => {
		const settings = window.parent.wp.data
			.select( 'core/block-editor' )
			.getSettings();
		const linkedStyles = Array.from(
			document.querySelectorAll(
				'link[id^="bfa-font-awesome"][rel="stylesheet"]'
			)
		).map( ( link ) => ( {
			href: link.href,
			integrity: link.integrity,
		} ) );
		const bytesToBase64 = ( bytes ) => {
			let binary = '';
			for ( const byte of bytes ) {
				binary += String.fromCharCode( byte );
			}
			return window.btoa( binary );
		};
		const matches = [];

		for ( const style of settings.styles ?? [] ) {
			if ( 'string' !== typeof style.css || 'string' !== typeof style.baseURL ) {
				continue;
			}

			for ( const linkedStyle of linkedStyles ) {
				const separator = linkedStyle.integrity.indexOf( '-' );
				if ( separator < 1 ) {
					continue;
				}
				const algorithm = linkedStyle.integrity.slice( 0, separator );
				const expectedDigest = linkedStyle.integrity.slice( separator + 1 );
				const digest = await window.crypto.subtle.digest(
					algorithm.replace( /^sha/, 'SHA-' ),
					new TextEncoder().encode( style.css )
				);
				if ( bytesToBase64( new Uint8Array( digest ) ) !== expectedDigest ) {
					continue;
				}

				const baseURL = new URL( style.baseURL, window.location.href );
				const href = new URL( linkedStyle.href );
				matches.push( {
					baseURL: `${ baseURL.origin }${ baseURL.pathname }`,
					href: `${ href.origin }${ href.pathname }`,
					integrity: linkedStyle.integrity,
				} );
			}
		}

		return matches;
	} );
	expect( exactStyleMatches.length ).toBeGreaterThanOrEqual( 2 );
	for ( const match of exactStyleMatches ) {
		expect( match.baseURL ).toBe( match.href );
		expect( match.integrity ).toMatch( /^sha(?:256|384|512)-/ );
	}
	expect( exactStyleMatches.map( ( match ) => match.href ) ).toEqual(
		expect.arrayContaining( [
			expect.stringMatching( /\/css\/all\.min\.css$/ ),
			expect.stringMatching( /\/css\/v5-font-face\.min\.css$/ ),
		] )
	);
	expect( rootWebfontFailures ).toEqual( [] );
	expect( fontAwesomeErrors ).toEqual( [] );
} );

test( 'inserts, persists, and renders a native icon block', async ( { page } ) => {
	const fontAwesomeErrors = [];
	const fontAwesomeStylesheetRequests = [];
	const fontAwesomeWebfontFailures = [];
	const metadataRequests = [];
	page.on( 'request', ( request ) => {
		if ( fontAwesomeStylesheetPattern.test( request.url() ) ) {
			fontAwesomeStylesheetRequests.push( request.url() );
		}
		if ( metadataRequestPattern.test( request.url() ) ) {
			metadataRequests.push( request.url() );
		}
	} );
	page.on( 'requestfailed', ( request ) => {
		if ( fontAwesomeWebfontPattern.test( request.url() ) ) {
			fontAwesomeWebfontFailures.push( {
				error: request.failure()?.errorText ?? 'request failed',
				url: request.url(),
			} );
		}
	} );
	page.on( 'response', ( response ) => {
		if (
			fontAwesomeWebfontPattern.test( response.url() ) &&
			response.status() >= 400
		) {
			fontAwesomeWebfontFailures.push( {
				error: `HTTP ${ response.status() }`,
				url: response.url(),
			} );
		}
	} );
	page.on( 'console', ( message ) => {
		const text = message.text();
		if (
			/font awesome|fontawesome|better font awesome|\bbfa\b|\bbfal\b|cors/i.test(
				text
			) && 'error' === message.type()
		) {
			fontAwesomeErrors.push( text );
		}
	} );

	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( 'admin' );
	await page.locator( '#user_pass' ).fill( 'password' );
	await page.locator( '#wp-submit' ).click();
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();

	await page.goto( '/wp-admin/post-new.php' );
	await page.waitForFunction( () => {
		return Boolean( window.wp?.blocks?.getBlockType( 'better-font-awesome/icon' ) );
	} );
	await dismissWelcomeModal( page );
	const parentFontAwesomeStylesheet = page.locator(
		'link#bfa-font-awesome-css[rel="stylesheet"]'
	);
	await expect( parentFontAwesomeStylesheet ).toHaveAttribute(
		'href',
		fontAwesomeStylesheetPattern
	);
	await expect( parentFontAwesomeStylesheet ).toHaveAttribute(
		'crossorigin',
		'anonymous'
	);
	const localizedMetadata = await page.evaluate( async () => {
		const blockName = 'better-font-awesome/icon';
		const registered = window.wp.blocks.getBlockType( blockName );
		const scriptElement = document.querySelector( 'script[src*="/build/index.js"]' );
		const scriptSource = await fetch( scriptElement.src ).then( ( response ) =>
			response.text()
		);
		const expected = {
			description: 'Localized icon block description.',
			keywords: [ 'localized-icon-keyword' ],
			title: 'Localized Font Awesome Icon',
		};

		window.wp.blocks.unregisterBlockType( blockName );
		window.wp.blocks.unstable__bootstrapServerSideBlockDefinitions( {
			[ blockName ]: {
				...registered,
				...expected,
			},
		} );

		const replacementScript = document.createElement( 'script' );
		replacementScript.textContent = scriptSource;
		document.head.appendChild( replacementScript );
		replacementScript.remove();

		const reRegistered = window.wp.blocks.getBlockType( blockName );
		return {
			description: reRegistered.description,
			keywords: reRegistered.keywords,
			title: reRegistered.title,
		};
	} );
	expect( localizedMetadata ).toEqual( {
		description: 'Localized icon block description.',
		keywords: [ 'localized-icon-keyword' ],
		title: 'Localized Font Awesome Icon',
	} );

	const post = await page.evaluate( async ( boundaryClass ) => {
		const leftBlock = window.wp.blocks.createBlock( 'better-font-awesome/icon', {
			iconName: 'flag',
			iconStyle: 'solid',
			label: 'Favorite',
		} );
		const centerBlock = window.wp.blocks.createBlock(
			'better-font-awesome/icon',
			{
				iconName: 'star',
				iconStyle: 'solid',
			}
		);
		const rightBlock = window.wp.blocks.createBlock(
			'better-font-awesome/icon',
			{
				iconName: 'arrow-right',
				iconStyle: 'solid',
			}
		);
		const justificationBoundary = window.wp.blocks.createBlock(
			'core/group',
			{
				className: boundaryClass,
				layout: {
					type: 'default',
				},
			},
			[ leftBlock, centerBlock, rightBlock ]
		);
		const row = window.wp.blocks.createBlock(
			'core/group',
			{
				layout: {
					flexWrap: 'nowrap',
					type: 'flex',
				},
			},
			[
				window.wp.blocks.createBlock( 'better-font-awesome/icon', {
					iconName: 'coffee',
					iconStyle: 'solid',
				} ),
				window.wp.blocks.createBlock( 'core/paragraph', {
					content: 'Row icon text',
				} ),
			]
		);
		const columnIcon = window.wp.blocks.createBlock(
			'better-font-awesome/icon',
			{
				iconJustification: 'right',
				iconName: 'heart',
				iconStyle: 'regular',
			}
		);
		const columns = window.wp.blocks.createBlock( 'core/columns', {}, [
			window.wp.blocks.createBlock( 'core/column', {}, [
				window.wp.blocks.createBlock( 'core/paragraph', {
					content: 'Column icon reference',
				} ),
				columnIcon,
			] ),
			window.wp.blocks.createBlock( 'core/column', {}, [
				window.wp.blocks.createBlock( 'core/paragraph', {
					content: 'Second column',
				} ),
			] ),
		] );
		const brandBlock = window.wp.blocks.createBlock(
			'better-font-awesome/icon',
			{
				iconName: 'github',
				iconStyle: 'brands',
			}
		);
		const classicBlock = window.wp.blocks.createBlock( 'core/freeform', {
			content: '<p><i class="fas fa-flag"></i> Classic block icon</p>',
		} );
		window.wp.data
			.dispatch( 'core/block-editor' )
			.insertBlocks( [
				justificationBoundary,
				row,
				columns,
				brandBlock,
				classicBlock,
			] );
		window.wp.data.dispatch( 'core/editor' ).editPost( {
			status: 'publish',
			title: 'Better Font Awesome block acceptance',
		} );
		await window.wp.data.dispatch( 'core/editor' ).savePost();

		return {
			centerClientId: centerBlock.clientId,
			classicClientId: classicBlock.clientId,
			id: window.wp.data.select( 'core/editor' ).getCurrentPostId(),
			leftClientId: leftBlock.clientId,
			link: window.wp.data.select( 'core/editor' ).getPermalink(),
			rightClientId: rightBlock.clientId,
		};
	}, controlledBoundaryClass );
	await page.evaluate( ( clientId ) => {
		window.wp.data.dispatch( 'core/block-editor' ).selectBlock( clientId );
	}, post.leftClientId );
	await dismissWelcomeModal( page );
	const registeredAlignSupport = await page.evaluate( () => {
		return window.wp.blocks.getBlockType( 'better-font-awesome/icon' ).supports.align;
	} );
	expect( registeredAlignSupport ).toBeUndefined();
	await expect(
		page
			.locator( '.block-editor-block-toolbar' )
			.getByRole( 'button', { name: /^Align(?: |$)/ } )
	).toHaveCount( 0 );

	await setIconJustification( page, post.leftClientId, 'left' );
	await setIconJustification( page, post.centerClientId, 'center' );
	await setIconJustification( page, post.rightClientId, 'right' );
	await page.evaluate( async () => {
		await window.wp.data.dispatch( 'core/editor' ).savePost();
	} );
	await page.evaluate( ( clientId ) => {
		window.wp.data.dispatch( 'core/block-editor' ).selectBlock( clientId );
	}, post.leftClientId );

	const editorFrame = page.frame( { name: 'editor-canvas' } );
	const editorContext = editorFrame ?? page;
	await editorContext.addStyleTag( { content: controlledBoundaryCss } );
	const loadedEditorFaces = await loadFontAwesomeFaces( editorContext );
	for ( const face of loadedEditorFaces ) {
		expect( face.status ).toBe( 'fulfilled' );
		expect( face.loaded ).toBeGreaterThan( 0 );
	}
	const editor = editorFrame
		? page.frameLocator( 'iframe[name="editor-canvas"]' )
		: page;
	const canvasFontAwesomeStylesheet = editor.locator(
		'link#bfa-font-awesome-css[rel="stylesheet"]'
	);
	await expect( canvasFontAwesomeStylesheet ).toHaveAttribute(
		'href',
		fontAwesomeStylesheetPattern
	);
	await expect( canvasFontAwesomeStylesheet ).toHaveAttribute(
		'crossorigin',
		'anonymous'
	);
	await expectIntegrityStyles( editor );
	const editorIcon = editor.locator(
		'.wp-block-better-font-awesome-icon .fas.fa-flag'
	);
	await expect( editorIcon ).toBeVisible();
	const iconControl = page.getByLabel( 'Icon', { exact: true } );
	const selectedLabel = await page.evaluate( () => {
		return window.bfaBlockEditor.icons.find(
			( icon ) => 'flag' === icon.name && 'solid' === icon.style
		).label;
	} );
	await expect( iconControl ).toHaveValue( selectedLabel );
	const catalogHelp = page.getByText(
		/Search all [\d,]+ available Font Awesome Free/
	);
	const accessibleLabel = page.getByText( 'Accessible label', { exact: true } );
	await expect( catalogHelp ).toBeVisible();
	await expect( accessibleLabel ).toBeVisible();
	const [ catalogHelpBox, accessibleLabelBox ] = await Promise.all( [
		catalogHelp.boundingBox(),
		accessibleLabel.boundingBox(),
	] );
	expect( catalogHelpBox ).not.toBeNull();
	expect( accessibleLabelBox ).not.toBeNull();
	expect(
		accessibleLabelBox.y - ( catalogHelpBox.y + catalogHelpBox.height )
	).toBeGreaterThanOrEqual( 12 );
	await iconControl.click();
	const selectedOption = page.getByRole( 'listbox' ).getByRole( 'option' ).first();
	await expect( selectedOption ).toContainText( selectedLabel );
	const selectedOptionIcon = selectedOption.locator( '.fas.fa-flag' );
	await expect( selectedOptionIcon ).toBeVisible();
	const selectedOptionGlyph = await selectedOptionIcon.evaluate( ( element ) => {
		const style = window.getComputedStyle( element, '::before' );
		return {
			content: style.content,
			fontFamily: style.fontFamily,
		};
	} );
	expect( selectedOptionGlyph.content ).not.toBe( 'none' );
	expect( selectedOptionGlyph.content ).not.toBe( 'normal' );
	expect( selectedOptionGlyph.fontFamily ).toContain( 'Font Awesome' );
	await iconControl.press( 'Escape' );

	const justificationBoundary = editor.locator(
		`.wp-block-group.${ controlledBoundaryClass }`
	);
	const leftBlock = justificationBoundary.locator(
		'.wp-block-better-font-awesome-icon.items-justified-left:has(.fas.fa-flag)'
	);
	const centerBlock = justificationBoundary.locator(
		'.wp-block-better-font-awesome-icon.items-justified-center:has(.fas.fa-star)'
	);
	const rightBlock = justificationBoundary.locator(
		'.wp-block-better-font-awesome-icon.items-justified-right:has(.fas.fa-arrow-right)'
	);
	const centerIcon = centerBlock.locator( '.fas.fa-star' );
	const rightIcon = rightBlock.locator( '.fas.fa-arrow-right' );
	const row = editor.locator( '.wp-block-group:has-text("Row icon text")' );
	const columns = editor.locator(
		'.wp-block-columns:has-text("Column icon reference")'
	);
	const firstColumn = columns.locator( '.wp-block-column' ).first();
	const columnBlock = firstColumn.locator(
		'.wp-block-better-font-awesome-icon.items-justified-right:has(.far.fa-heart)'
	);
	await expect( justificationBoundary ).toBeVisible();
	await expect( centerIcon ).toBeVisible();
	await expect( rightIcon ).toBeVisible();
	await expect( row.locator( '.fas.fa-coffee' ) ).toBeVisible();
	await expect( columnBlock.locator( '.far.fa-heart' ) ).toBeVisible();
	await expect(
		editor.locator( '.wp-block-better-font-awesome-icon .fab.fa-github' )
	).toBeVisible();
	await page.evaluate( ( clientId ) => {
		window.wp.data.dispatch( 'core/block-editor' ).selectBlock( clientId );
	}, post.classicClientId );
	const classicBlock = editor.locator(
		'[data-type="core/freeform"]:has-text("Classic block icon")'
	);
	await expect( classicBlock ).toBeVisible();
	const classicBlockGlyph = await classicBlock.locator( '.fas.fa-flag' ).evaluate(
		( element ) => {
			const style = window.getComputedStyle( element, '::before' );
			return { content: style.content, fontFamily: style.fontFamily };
		}
	);
	expect( classicBlockGlyph.content ).not.toBe( 'none' );
	expect( classicBlockGlyph.content ).not.toBe( 'normal' );
	expect( classicBlockGlyph.fontFamily ).toContain( 'Font Awesome' );
	await expect( leftBlock ).toHaveCSS( 'justify-content', 'flex-start' );
	await expect( centerBlock ).toHaveCSS( 'justify-content', 'center' );
	await expect( rightBlock ).toHaveCSS( 'justify-content', 'flex-end' );
	await expectControlledBoundary( justificationBoundary );
	await expectWrapperFillsBoundary( leftBlock, justificationBoundary );
	await expectWrapperFillsBoundary( centerBlock, justificationBoundary );
	await expectWrapperFillsBoundary( rightBlock, justificationBoundary );
	await expectIconPosition( leftBlock, editorIcon, 'left' );
	await expectIconPosition( centerBlock, centerIcon, 'center' );
	await expectIconPosition( rightBlock, rightIcon, 'right' );
	await expectBlockWithin( columnBlock, firstColumn );
	await expectIconPosition( columnBlock, columnBlock.locator( '.far.fa-heart' ), 'right' );
	expect( await row.evaluate( ( element ) => getComputedStyle( element ).display ) ).toBe(
		'flex'
	);

	const editorGlyph = await editorIcon.evaluate( ( element ) => {
		const style = window.getComputedStyle( element, '::before' );
		return {
			content: style.content,
			fontFamily: style.fontFamily,
		};
	} );
	expect( editorGlyph.content ).not.toBe( 'none' );
	expect( editorGlyph.content ).not.toBe( 'normal' );
	expect( editorGlyph.fontFamily ).toContain( 'Font Awesome' );

	await page.reload();
	await page.waitForFunction( () => {
		return Boolean( window.wp?.data?.select( 'core/block-editor' ).getBlocks().length );
	} );
	const attributes = await page.evaluate( ( boundaryClass ) => {
		const blocks = window.wp.data.select( 'core/block-editor' ).getBlocks();
		const justificationBoundaryBlock = blocks.find(
			( item ) =>
				'core/group' === item.name && item.attributes.className === boundaryClass
		);
		const iconBlocks = justificationBoundaryBlock.innerBlocks;
		const rowBlock = blocks.find(
			( item ) =>
				'core/group' === item.name && 'flex' === item.attributes.layout?.type
		);
		const columnsBlock = blocks.find( ( item ) => 'core/columns' === item.name );
		const firstColumnBlock = columnsBlock.innerBlocks[ 0 ];

		return {
			center: iconBlocks[ 1 ].attributes,
			columnChildren: firstColumnBlock.innerBlocks.map( ( item ) => item.name ),
			columnIcon: firstColumnBlock.innerBlocks[ 1 ].attributes,
			columnsChildren: columnsBlock.innerBlocks.map( ( item ) => item.name ),
			justificationBoundaryChildren: iconBlocks.map( ( item ) => item.name ),
			left: iconBlocks[ 0 ].attributes,
			right: iconBlocks[ 2 ].attributes,
			rowChildren: rowBlock.innerBlocks.map( ( item ) => item.name ),
		};
	}, controlledBoundaryClass );
	expect( attributes.left ).toMatchObject( {
		iconJustification: 'left',
		iconName: 'flag',
		iconStyle: 'solid',
		label: 'Favorite',
	} );
	expect( attributes.center ).toMatchObject( {
		iconJustification: 'center',
		iconName: 'star',
		iconStyle: 'solid',
	} );
	expect( attributes.right ).toMatchObject( {
		iconJustification: 'right',
		iconName: 'arrow-right',
		iconStyle: 'solid',
	} );
	expect( attributes.left ).not.toHaveProperty( 'align' );
	expect( attributes.center ).not.toHaveProperty( 'align' );
	expect( attributes.right ).not.toHaveProperty( 'align' );
	expect( attributes.justificationBoundaryChildren ).toEqual( [
		'better-font-awesome/icon',
		'better-font-awesome/icon',
		'better-font-awesome/icon',
	] );
	expect( attributes.rowChildren ).toEqual( [
		'better-font-awesome/icon',
		'core/paragraph',
	] );
	expect( attributes.columnsChildren ).toEqual( [ 'core/column', 'core/column' ] );
	expect( attributes.columnChildren ).toEqual( [
		'core/paragraph',
		'better-font-awesome/icon',
	] );
	expect( attributes.columnIcon ).toMatchObject( {
		iconJustification: 'right',
		iconName: 'heart',
		iconStyle: 'regular',
	} );

	const metaBoxesToggle = page.getByRole( 'button', { name: 'Meta Boxes' } );
	if (
		( await metaBoxesToggle.count() ) > 0 &&
		'false' === ( await metaBoxesToggle.getAttribute( 'aria-expanded' ) )
	) {
		await metaBoxesToggle.evaluate( ( element ) => element.click() );
	}
	await expectPickerInsertsAquarius( page, 'bfa_hybrid_editor' );
	await expectTinyMceFontAwesome( page, 'bfa_hybrid_editor' );

	await page.goto( post.link );
	await page.addStyleTag( { content: controlledBoundaryCss } );
	await expect(
		page.locator( 'link#bfa-icon-block-style-css[rel="stylesheet"]' )
	).toHaveAttribute( 'href', /\/build\/style-index\.css(?:\?.*)?$/ );
	const frontendBoundary = page.locator(
		`.wp-block-group.${ controlledBoundaryClass }`
	);
	const frontendLeftBlock = frontendBoundary.locator(
		'.wp-block-better-font-awesome-icon.items-justified-left[role="img"]:has(.fas.fa-flag)'
	);
	const frontendCenterBlock = frontendBoundary.locator(
		'.wp-block-better-font-awesome-icon.items-justified-center:has(.fas.fa-star)'
	);
	const frontendRightBlock = frontendBoundary.locator(
		'.wp-block-better-font-awesome-icon.items-justified-right:has(.fas.fa-arrow-right)'
	);
	await expect( frontendBoundary ).toBeVisible();
	await expect( frontendLeftBlock ).toHaveAttribute( 'aria-label', 'Favorite' );
	await expect( frontendLeftBlock.locator( '.fas.fa-flag' ) ).toBeVisible();
	await expect( frontendCenterBlock ).toHaveCSS( 'justify-content', 'center' );
	await expect( frontendRightBlock ).toHaveCSS( 'justify-content', 'flex-end' );
	await expectControlledBoundary( frontendBoundary );
	await expectWrapperFillsBoundary( frontendLeftBlock, frontendBoundary );
	await expectWrapperFillsBoundary( frontendCenterBlock, frontendBoundary );
	await expectWrapperFillsBoundary( frontendRightBlock, frontendBoundary );
	await expectIconPosition(
		frontendLeftBlock,
		frontendLeftBlock.locator( '.fas.fa-flag' ),
		'left'
	);
	await expectIconPosition(
		frontendCenterBlock,
		frontendCenterBlock.locator( '.fas.fa-star' ),
		'center'
	);
	await expectIconPosition(
		frontendRightBlock,
		frontendRightBlock.locator( '.fas.fa-arrow-right' ),
		'right'
	);
	await expect(
		page.locator( '.wp-block-group.is-layout-flex:has-text("Row icon text") .fas.fa-mug-saucer' )
	).toBeVisible();
	const frontendColumn = page
		.locator( '.wp-block-columns:has-text("Column icon reference") .wp-block-column' )
		.first();
	const frontendColumnBlock = frontendColumn.locator(
		'.wp-block-better-font-awesome-icon.items-justified-right:has(.far.fa-heart)'
	);
	await expect( frontendColumnBlock.locator( '.far.fa-heart' ) ).toBeVisible();
	await expect(
		page.locator( '.wp-block-better-font-awesome-icon .fab.fa-github' )
	).toBeVisible();
	await expectBlockWithin( frontendColumnBlock, frontendColumn );
	await expectIconPosition(
		frontendColumnBlock,
		frontendColumnBlock.locator( '.far.fa-heart' ),
		'right'
	);
	for ( const block of [
		frontendLeftBlock,
		frontendCenterBlock,
		frontendRightBlock,
	] ) {
		expect( await block.getAttribute( 'class' ) ).not.toMatch(
			/\balign(?:left|center|right)\b/
		);
	}
	expect( fontAwesomeStylesheetRequests ).not.toHaveLength( 0 );
	expect( fontAwesomeStylesheetRequests ).toEqual(
		expect.arrayContaining( [
			expect.stringMatching( fontAwesomeStylesheetPattern ),
		] )
	);

	await page.goto( '/wp-admin/post-new.php?post_type=bfa_classic_test' );
	await expectPickerInsertsAquarius( page, 'content' );
	await expectIntegrityStyles( page );
	await expectTinyMceFontAwesome( page, 'content' );
	expect( fontAwesomeErrors ).toEqual( [] );
	expect( fontAwesomeWebfontFailures ).toEqual( [] );
	expect( metadataRequests ).toEqual( [] );
} );

async function openStyleEditor( page, attributes ) {
	page.setDefaultTimeout( 15000 );
	await page.goto( '/wp-login.php' );
	// Wait for WordPress's delayed autofocus before filling the password.
	await expect( page.locator( '#user_login' ) ).toBeFocused();
	await page.locator( '#user_login' ).fill( 'admin' );
	await page.locator( '#user_pass' ).fill( 'password' );
	await page.locator( '#wp-submit' ).click();
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();
	await page.goto( '/wp-admin/post-new.php' );
	await page.waitForFunction( () => Boolean( window.wp?.blocks?.getBlockType( 'better-font-awesome/icon' ) ) );
	await dismissWelcomeModal( page );
	return page.evaluate( ( attributes ) => {
		const block = window.wp.blocks.createBlock( 'better-font-awesome/icon', attributes );
		window.wp.data.dispatch( 'core/block-editor' ).insertBlocks( [ block ] );
		window.wp.data.dispatch( 'core/block-editor' ).selectBlock( block.clientId );
		return block.clientId;
	}, attributes );
}

async function readIconAttributes( page, clientId ) {
	return page.evaluate( ( id ) => window.wp.data.select( 'core/block-editor' ).getBlock( id ).attributes, clientId );
}

test( 'Free Style control switches with the keyboard, synchronizes, and preserves saved attributes', async ( { page } ) => {
	const clientId = await openStyleEditor( page, {
		iconName: 'heart', iconStyle: 'solid', label: 'Favorite',
		iconJustification: 'right', className: 'retained-class',
		style: { color: { text: '#123456' }, typography: { fontSize: '48px' }, spacing: { padding: { top: '12px' } } },
	} );
	const original = await readIconAttributes( page, clientId );
	const iconControl = page.getByLabel( 'Icon', { exact: true } );
	const styleControl = page.getByRole( 'combobox', { name: 'Style', exact: true } );
	const labelControl = page.getByLabel( 'Accessible label', { exact: true } );
	await expect( styleControl.locator( 'option' ) ).toHaveText( [ 'Solid', 'Regular' ] );
	await expect( styleControl ).toHaveValue( 'solid' );
	const catalogLabel = ( name, style ) => page.evaluate( ( { name, style } ) =>
		window.bfaBlockEditor.icons.find( ( icon ) => icon.name === name && icon.style === style ).label,
	{ name, style } );

	// The full catalog still supplies Solid while the combined search shows Regular only.
	await iconControl.fill( 'regular' );
	await iconControl.press( 'Escape' );
	await expect( styleControl.locator( 'option' ) ).toHaveText( [ 'Solid', 'Regular' ] );
	await labelControl.focus();
	await labelControl.press( 'Shift+Tab' );
	await expect( styleControl ).toBeFocused();
	// Native select type-ahead works in Chromium on both macOS and Linux.
	await styleControl.press( 'r' );
	await styleControl.press( 'Tab' );
	await expect( labelControl ).toBeFocused();
	await expect( styleControl ).toHaveValue( 'regular' );
	await expect( iconControl ).toHaveValue( await catalogLabel( 'heart', 'regular' ) );
	expect( await readIconAttributes( page, clientId ) ).toEqual( { ...original, iconStyle: 'regular' } );

	const chooseIcon = async ( name, style ) => {
		const label = await catalogLabel( name, style );
		await iconControl.click();
		await iconControl.fill( label );
		await page.getByRole( 'option', { name: label, exact: true } ).click();
		await expect( styleControl ).toHaveValue( style );
		expect( await readIconAttributes( page, clientId ) ).toEqual( { ...original, iconName: name, iconStyle: style } );
	};
	await chooseIcon( 'heart', 'solid' );
	await expect( styleControl ).toBeEnabled();
	await chooseIcon( 'github', 'brands' );
	await expect( styleControl ).toBeDisabled();
	await expect( styleControl.locator( 'option' ) ).toHaveText( [ 'Brands' ] );
	await chooseIcon( 'arrow-right', 'solid' );
	await expect( styleControl ).toBeDisabled();
	await expect( styleControl.locator( 'option' ) ).toHaveText( [ 'Solid' ] );
	await chooseIcon( 'heart', 'regular' );
	await expect( styleControl ).toBeEnabled();

	const post = await page.evaluate( async () => {
		window.wp.data.dispatch( 'core/editor' ).editPost( { title: 'Free Style acceptance', status: 'publish' } );
		await window.wp.data.dispatch( 'core/editor' ).savePost();
		return { id: window.wp.data.select( 'core/editor' ).getCurrentPostId(), link: window.wp.data.select( 'core/editor' ).getPermalink() };
	} );
	await page.reload();
	await page.waitForFunction( () => window.wp?.data?.select( 'core/block-editor' ).getBlocks().length );
	const saved = await page.evaluate( () => {
		const block = window.wp.data.select( 'core/block-editor' ).getBlocks()[ 0 ];
		window.wp.data.dispatch( 'core/block-editor' ).selectBlock( block.clientId );
		return { attributes: block.attributes, valid: block.isValid };
	} );
	expect( saved ).toEqual( { attributes: { ...original, iconStyle: 'regular' }, valid: true } );
	await expect( styleControl ).toHaveValue( 'regular' );
	await expect( iconControl ).toHaveValue( await catalogLabel( 'heart', 'regular' ) );
	await test.info().attach( 'free-style-selector', { body: await page.screenshot(), contentType: 'image/png' } );
	await page.goto( post.link );
	const block = page.locator( '.wp-block-better-font-awesome-icon.retained-class' );
	await expect( block ).toHaveClass( /retained-class/ );
	await expect( block ).toHaveClass( /items-justified-right/ );
	await expect( block ).toHaveCSS( 'color', 'rgb(18, 52, 86)' );
	await expect( block ).toHaveCSS( 'padding-top', '12px' );
	const icon = block.locator( '.far.fa-heart' );
	await expect( icon ).toBeVisible();
	await expect( block ).toHaveAttribute( 'aria-label', 'Favorite' );
	await expect( block ).toHaveAttribute( 'role', 'img' );
	await expect( icon ).toHaveCSS( 'font-weight', '400' );
	const faces = await loadFontAwesomeFaces( page );
	expect( faces.every( ( face ) => face.status === 'fulfilled' && face.loaded > 0 ) ).toBe( true );
} );

test( 'Free Style control preserves unavailable selections until an explicit choice', async ( { page } ) => {
	const clientId = await openStyleEditor( page, { iconName: 'heart', iconStyle: 'solid', label: 'Keep me', iconJustification: 'center' } );
	const original = await readIconAttributes( page, clientId );
	const styleControl = page.getByRole( 'combobox', { name: 'Style', exact: true } );
	const unavailable = page.getByText( 'This icon or style is unavailable in the current catalog.', { exact: true } );
	for ( const selection of [
		{ iconName: 'not-in-the-catalog', iconStyle: 'solid' },
		{ iconName: 'github', iconStyle: 'regular' },
		{ iconName: 'heart', iconStyle: 'brands' },
		{ iconName: 'heart', iconStyle: 'legacy-style' },
		{ iconName: 'heart', iconStyle: '' },
	] ) {
		await page.evaluate( ( { clientId, selection } ) => window.wp.data.dispatch( 'core/block-editor' ).updateBlockAttributes( clientId, selection ), { clientId, selection } );
		await expect( unavailable ).toBeVisible();
		await expect( styleControl ).toHaveValue( selection.iconStyle );
		await expect( styleControl.locator( 'option:checked' ) ).toHaveText( /^Unavailable \(/ );
		await expect( styleControl.locator( 'option:checked' ) ).toBeDisabled();
		if ( [ 'not-in-the-catalog', 'github' ].includes( selection.iconName ) ) {
			await expect( styleControl ).toBeDisabled();
		} else {
			await expect( styleControl ).toBeEnabled();
		}
		expect( await readIconAttributes( page, clientId ) ).toEqual( { ...original, ...selection } );
	}
	// Save and reload an unavailable style, including all unrelated attributes.
	await page.evaluate( async () => {
		window.wp.data.dispatch( 'core/editor' ).editPost( { title: 'Unavailable Free Style acceptance' } );
		await window.wp.data.dispatch( 'core/editor' ).savePost();
	} );
	await page.reload();
	await page.waitForFunction( () => window.wp?.data?.select( 'core/block-editor' ).getBlocks().length );
	const savedId = await page.evaluate( () => {
		const block = window.wp.data.select( 'core/block-editor' ).getBlocks()[ 0 ];
		window.wp.data.dispatch( 'core/block-editor' ).selectBlock( block.clientId );
		return block.clientId;
	} );
	await expect( unavailable ).toBeVisible();
	await expect( styleControl ).toHaveValue( '' );
	expect( await readIconAttributes( page, savedId ) ).toEqual( { ...original, iconStyle: '' } );
	await styleControl.selectOption( 'regular' );
	await expect( unavailable ).toHaveCount( 0 );
	expect( await readIconAttributes( page, savedId ) ).toEqual( { ...original, iconStyle: 'regular' } );
} );

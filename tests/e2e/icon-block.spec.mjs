import { expect, test } from '@playwright/test';

const fontAwesomeStylesheetPattern =
	/^https:\/\/use\.fontawesome\.com\/releases\/[^/]+\/css\/[^?]+\.css(?:\?.*)?$/;
const syntheticFontAwesomeCss = `
.fab, .far, .fas {
	display: inline-block;
	font-family: "Font Awesome 5 Free";
	font-style: normal;
}
.fa-coffee::before, .fa-flag::before, .fa-star::before {
	content: "*";
}
`;

test( 'inserts, persists, and renders a native icon block', async ( { page } ) => {
	const fontAwesomeErrors = [];
	const fontAwesomeStylesheetRequests = [];
	await page.route( fontAwesomeStylesheetPattern, async ( route ) => {
		fontAwesomeStylesheetRequests.push( route.request().url() );
		await route.fulfill( {
			body: syntheticFontAwesomeCss,
			contentType: 'text/css',
			headers: {
				'access-control-allow-origin': '*',
				'cache-control': 'no-store',
			},
			status: 200,
		} );
	} );
	page.on( 'console', ( message ) => {
		const text = message.text();
		if ( /font awesome|fontawesome|cors/i.test( text ) && 'error' === message.type() ) {
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
	const parentFontAwesomeStylesheet = page.locator(
		'link#bfa-font-awesome-css[rel="stylesheet"]'
	);
	await expect( parentFontAwesomeStylesheet ).toHaveAttribute(
		'href',
		/^https:\/\/use\.fontawesome\.com\/releases\/[^/]+\/css\/all\.css\?ver=/
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

	const post = await page.evaluate( async () => {
		const paragraph = window.wp.blocks.createBlock( 'core/paragraph', {
			content: 'Reference paragraph',
		} );
		const block = window.wp.blocks.createBlock( 'better-font-awesome/icon', {
			iconName: 'flag',
			iconStyle: 'solid',
			label: 'Favorite',
		} );
		const centeredBlock = window.wp.blocks.createBlock(
			'better-font-awesome/icon',
			{
				align: 'center',
				iconName: 'star',
				iconStyle: 'solid',
			}
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
		window.wp.data
			.dispatch( 'core/block-editor' )
			.insertBlocks( [ paragraph, block, centeredBlock, row ] );
		window.wp.data.dispatch( 'core/editor' ).editPost( {
			status: 'publish',
			title: 'Better Font Awesome block acceptance',
		} );
		await window.wp.data.dispatch( 'core/editor' ).savePost();

		return {
			id: window.wp.data.select( 'core/editor' ).getCurrentPostId(),
			iconClientId: block.clientId,
			link: window.wp.data.select( 'core/editor' ).getPermalink(),
		};
	} );
	await page.evaluate( ( clientId ) => {
		window.wp.data.dispatch( 'core/block-editor' ).selectBlock( clientId );
	}, post.iconClientId );
	const welcomeModal = page
		.locator( '.components-modal__screen-overlay' )
		.filter( { hasText: 'Welcome to the editor' } );
	if ( await welcomeModal.isVisible() ) {
		await welcomeModal.getByRole( 'button', { name: 'Close' } ).click();
	}

	const editor = page.frameLocator( 'iframe[name="editor-canvas"]' );
	const canvasFontAwesomeStylesheet = editor.locator(
		'link#bfa-font-awesome-css[rel="stylesheet"]'
	);
	await expect( canvasFontAwesomeStylesheet ).toHaveAttribute(
		'href',
		/^https:\/\/use\.fontawesome\.com\/releases\/[^/]+\/css\/all\.css\?ver=/
	);
	await expect( canvasFontAwesomeStylesheet ).toHaveAttribute(
		'crossorigin',
		'anonymous'
	);
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

	const referenceParagraph = editor.getByText( 'Reference paragraph', {
		exact: true,
	} );
	const defaultBlock = editor.locator(
		'.wp-block-better-font-awesome-icon:has(.fas.fa-flag)'
	);
	const centeredBlock = editor.locator(
		'.wp-block-better-font-awesome-icon:has(.fas.fa-star)'
	);
	const centeredIcon = centeredBlock.locator( '.fas.fa-star' );
	const row = editor.locator( '.wp-block-group:has-text("Row icon text")' );
	await expect( referenceParagraph ).toBeVisible();
	await expect( centeredIcon ).toBeVisible();
	await expect( row.locator( '.fas.fa-coffee' ) ).toBeVisible();

	const [ paragraphBox, defaultBlockBox, defaultIconBox ] = await Promise.all( [
		referenceParagraph.boundingBox(),
		defaultBlock.boundingBox(),
		editorIcon.boundingBox(),
	] );
	expect( paragraphBox ).not.toBeNull();
	expect( defaultBlockBox ).not.toBeNull();
	expect( defaultIconBox ).not.toBeNull();
	expect( Math.abs( paragraphBox.x - defaultBlockBox.x ) ).toBeLessThan( 2 );
	expect( defaultBlockBox.width ).toBeGreaterThan( defaultIconBox.width * 2 );

	const [ centeredBlockBox, centeredIconBox ] = await Promise.all( [
		centeredBlock.boundingBox(),
		centeredIcon.boundingBox(),
	] );
	expect( centeredBlockBox ).not.toBeNull();
	expect( centeredIconBox ).not.toBeNull();
	expect(
		Math.abs(
			centeredBlockBox.x + centeredBlockBox.width / 2 -
				( centeredIconBox.x + centeredIconBox.width / 2 )
		)
	).toBeLessThan( 2 );
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
	const attributes = await page.evaluate( () => {
		const blocks = window.wp.data.select( 'core/block-editor' ).getBlocks();
		const iconBlocks = blocks.filter(
			( item ) => 'better-font-awesome/icon' === item.name
		);
		const rowBlock = blocks.find( ( item ) => 'core/group' === item.name );

		return {
			centered: iconBlocks[ 1 ].attributes,
			default: iconBlocks[ 0 ].attributes,
			rowChildren: rowBlock.innerBlocks.map( ( item ) => item.name ),
		};
	} );
	expect( attributes.default ).toMatchObject( {
		iconName: 'flag',
		iconStyle: 'solid',
		label: 'Favorite',
	} );
	expect( attributes.centered ).toMatchObject( {
		align: 'center',
		iconName: 'star',
		iconStyle: 'solid',
	} );
	expect( attributes.rowChildren ).toEqual( [
		'better-font-awesome/icon',
		'core/paragraph',
	] );

	await page.goto( post.link );
	const frontendBlock = page.locator( '.wp-block-better-font-awesome-icon[role="img"]' );
	await expect( frontendBlock ).toHaveAttribute( 'aria-label', 'Favorite' );
	await expect( frontendBlock.locator( '.fas.fa-flag' ) ).toBeVisible();
	const frontendCenteredBlock = page.locator(
		'.wp-block-better-font-awesome-icon.aligncenter:has(.fas.fa-star)'
	);
	await expect( frontendCenteredBlock ).toHaveCSS( 'display', 'flex' );
	await expect(
		page.locator( '.wp-block-group.is-layout-flex:has-text("Row icon text") .fas.fa-coffee' )
	).toBeVisible();
	expect( fontAwesomeStylesheetRequests ).not.toHaveLength( 0 );
	expect( fontAwesomeStylesheetRequests ).toEqual(
		expect.arrayContaining( [
			expect.stringMatching(
				/^https:\/\/use\.fontawesome\.com\/releases\/[^/]+\/css\/all\.css\?ver=/
			),
		] )
	);
	expect( fontAwesomeErrors ).toEqual( [] );
} );

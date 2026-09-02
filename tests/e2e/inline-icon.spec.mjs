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

const primaryModifier = 'darwin' === process.platform ? 'Meta' : 'Control';

async function login( page ) {
	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( 'admin' );
	await page.locator( '#user_pass' ).fill( 'password' );
	await page.locator( '#wp-submit' ).click();
	await expect( page.locator( '#wpadminbar' ) ).toBeVisible();
}

async function closeWelcomeModal( page ) {
	const welcomeModal = page
		.locator( '.components-modal__screen-overlay' )
		.filter( { hasText: 'Welcome to the editor' } );
	if ( await welcomeModal.isVisible() ) {
		await welcomeModal.getByRole( 'button', { name: 'Close' } ).click();
	}
}

async function moveCaret( page, editable, edge, offset = 0 ) {
	const clientId = await editable.getAttribute( 'data-block' );
	await editable.focus();
	await page.evaluate(
		( { attributeOffset, caretEdge, selectedClientId } ) => {
			const block = window.wp.data
				.select( 'core/block-editor' )
				.getBlock( selectedClientId );
			const value = window.wp.richText.create( {
				html: block.attributes.content,
			} );
			const caretOffset = 'End' === caretEdge ? value.text.length : attributeOffset;
			window.wp.data.dispatch( 'core/block-editor' ).selectBlock( selectedClientId );
			window.wp.data
				.dispatch( 'core/block-editor' )
				.selectionChange( selectedClientId, 'content', caretOffset, caretOffset );
		},
		{
			attributeOffset: offset,
			caretEdge: edge,
			selectedClientId: clientId,
		}
	);
	await expect( editable ).toBeFocused();
}

async function selectRange( page, editable, start, end ) {
	const clientId = await editable.getAttribute( 'data-block' );
	await editable.focus();
	await page.evaluate(
		( { rangeEnd, rangeStart, selectedClientId } ) => {
			window.wp.data.dispatch( 'core/block-editor' ).selectBlock( selectedClientId );
			window.wp.data
				.dispatch( 'core/block-editor' )
				.selectionChange( selectedClientId, 'content', rangeStart, rangeEnd );
		},
		{
			rangeEnd: end,
			rangeStart: start,
			selectedClientId: clientId,
		}
	);
	await expect( editable ).toBeFocused();
}

async function openInlineIconPicker( page ) {
	await page.getByRole( 'button', { name: 'More', exact: true } ).click();
	await page.getByText( 'Font Awesome icon', { exact: true } ).click();
	await expect( page.locator( '.bfa-inline-icon-popover' ) ).toBeVisible();
}

async function chooseInlineIcon( page, label ) {
	const popover = page.locator( '.bfa-inline-icon-popover' );
	const control = popover.getByLabel( 'Icon', { exact: true } );
	await control.fill( label );
	await page.getByRole( 'option', { name: label, exact: true } ).click();
	await expect( popover ).toBeHidden();
}

async function insertInlineIcon( page, editable, label, edge, offset = 0 ) {
	await moveCaret( page, editable, edge, offset );
	await openInlineIconPicker( page );
	await chooseInlineIcon( page, label );
}

test( 'edits and preserves atomic inline icons in Paragraph and Heading', async ( {
	page,
} ) => {
	test.setTimeout( 120000 );
	page.setDefaultTimeout( 10000 );
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

	await login( page );
	await page.goto( '/wp-admin/post-new.php' );
	await page.waitForFunction( () => {
		return Boolean(
			window.wp?.data
				?.select( 'core/rich-text' )
				.getFormatType( 'better-font-awesome/inline-icon' )
		);
	} );
	await closeWelcomeModal( page );
	const exitCodeEditor = page.getByText( 'Exit code editor', { exact: true } );
	if ( await exitCodeEditor.isVisible() ) {
		await exitCodeEditor.click();
	}
	await expect( page.frameLocator( 'iframe[name="editor-canvas"]' ).locator( 'body' ) ).toBeVisible();

	const fixture = await page.evaluate( () => {
		const blocks = [
			window.wp.blocks.createBlock( 'core/paragraph', {
				content: 'Leading icon text',
			} ),
			window.wp.blocks.createBlock( 'core/paragraph', {
				content: 'Middle icon text',
			} ),
			window.wp.blocks.createBlock( 'core/paragraph', {
				content: 'Trailing icon text',
			} ),
			window.wp.blocks.createBlock( 'core/heading', {
				content: 'Heading icon text',
			} ),
			window.wp.blocks.createBlock( 'core/paragraph', {
				content: 'Legacy [icon name="star" style="solid"] shortcode',
			} ),
			window.wp.blocks.createBlock( 'better-font-awesome/icon', {
				iconName: 'flag',
				iconStyle: 'solid',
			} ),
		];
		window.wp.data.dispatch( 'core/block-editor' ).insertBlocks( blocks );
		return {
			heading: blocks[ 3 ].clientId,
			labels: Object.fromEntries(
				[ 'coffee', 'flag', 'star' ].map( ( name ) => [
					name,
					window.bfaBlockEditor.icons.find(
						( icon ) => icon.name === name && icon.style === 'solid'
					).label,
				] )
			),
			leading: blocks[ 0 ].clientId,
			middle: blocks[ 1 ].clientId,
			standalone: blocks[ 5 ].clientId,
			trailing: blocks[ 2 ].clientId,
		};
	} );

	const editor = page.frameLocator( 'iframe[name="editor-canvas"]' );
	const editableFor = ( clientId ) =>
		editor.locator( `[data-block="${ clientId }"][contenteditable="true"]` );
	const leading = editableFor( fixture.leading );
	const middle = editableFor( fixture.middle );
	const trailing = editableFor( fixture.trailing );
	const heading = editableFor( fixture.heading );

	await insertInlineIcon( page, leading, fixture.labels.flag, 'Home' );
	await expect( leading.locator( 'i.bfa-inline-icon.fas.fa-flag' ) ).toHaveCount( 1 );

	await insertInlineIcon( page, middle, fixture.labels.star, 'Home', 7 );
	await moveCaret( page, middle, 'Home', 7 );
	await middle.type( 'before ' );
	await expect( middle ).toHaveText( 'Middle before icon text' );
	await selectRange( page, middle, 14, 15 );
	await openInlineIconPicker( page );
	await expect(
		page.locator( '.bfa-inline-icon-popover .bfa-icon-selector__selected .fa-star' )
	).toBeVisible();
	await chooseInlineIcon( page, fixture.labels.coffee );
	await middle.type( 'after ' );
	await expect( middle ).toHaveText( 'Middle before after icon text' );
	await expect( middle.locator( 'i.bfa-inline-icon.fas.fa-coffee' ) ).toHaveCount(
		1
	);

	await selectRange( page, middle, 14, 15 );
	await middle.press( `${ primaryModifier }+c` );
	await middle.press( 'End' );
	await middle.press( `${ primaryModifier }+v` );
	await expect( middle.locator( 'i.bfa-inline-icon.fas.fa-coffee' ) ).toHaveCount(
		2
	);

	await insertInlineIcon( page, heading, fixture.labels.flag, 'Home', 8 );
	await expect( heading.locator( 'i.bfa-inline-icon.fas.fa-flag' ) ).toHaveCount( 1 );

	await insertInlineIcon( page, trailing, fixture.labels.star, 'End' );
	await expect( trailing.locator( 'i.bfa-inline-icon.fas.fa-star' ) ).toHaveCount( 1 );
	await page.evaluate( () => window.wp.data.dispatch( 'core/editor' ).undo() );
	await expect( trailing.locator( 'i.bfa-inline-icon' ) ).toHaveCount( 0 );
	await page.evaluate( () => window.wp.data.dispatch( 'core/editor' ).redo() );
	await expect( trailing.locator( 'i.bfa-inline-icon.fas.fa-star' ) ).toHaveCount( 1 );
	await moveCaret( page, trailing, 'End' );
	await trailing.press( 'Backspace' );
	await expect( trailing.locator( 'i.bfa-inline-icon' ) ).toHaveCount( 0 );
	await page.evaluate( ( clientId ) => {
		const block = window.wp.data.select( 'core/block-editor' ).getBlock( clientId );
		const value = window.wp.richText.create( { html: block.attributes.content } );
		value.start = value.text.length;
		value.end = value.text.length;
		const nextValue = window.wp.richText.insertObject( value, {
			type: 'better-font-awesome/inline-icon',
			attributes: {
				className: 'fas fa-star',
				iconName: 'star',
				iconStyle: 'solid',
				ariaHidden: 'true',
			},
		} );
		window.wp.data.dispatch( 'core/block-editor' ).updateBlockAttributes( clientId, {
			content: window.wp.richText.toHTMLString( { value: nextValue } ),
		} );
	}, fixture.trailing );
	await expect( trailing.locator( 'i.bfa-inline-icon.fas.fa-star' ) ).toHaveCount( 1 );

	for ( const editable of [ leading, middle, trailing, heading ] ) {
		const text = await editable.textContent();
		expect( text ).not.toContain( '\uFFFC' );
		expect( text ).not.toMatch( /[\u200B\u200C\u200D\uFEFF]/u );
	}

	const editorGlyph = await leading.locator( '.fa-flag' ).evaluate( ( element ) => {
		const style = window.getComputedStyle( element, '::before' );
		return {
			content: style.content,
			fontFamily: style.fontFamily,
		};
	} );
	expect( editorGlyph.content ).not.toBe( 'none' );
	expect( editorGlyph.content ).not.toBe( 'normal' );
	expect( editorGlyph.fontFamily ).toContain( 'Font Awesome' );

	const post = await page.evaluate( async () => {
		window.wp.data.dispatch( 'core/editor' ).editPost( {
			status: 'publish',
			title: 'Better Font Awesome inline icon acceptance',
		} );
		await window.wp.data.dispatch( 'core/editor' ).savePost();
		return {
			content: window.wp.data.select( 'core/editor' ).getEditedPostContent(),
			id: window.wp.data.select( 'core/editor' ).getCurrentPostId(),
			link: window.wp.data.select( 'core/editor' ).getPermalink(),
		};
	} );

	const flagMarkup =
		'<i class="bfa-inline-icon fas fa-flag" data-bfa-icon-name="flag" data-bfa-icon-style="solid" aria-hidden="true"></i>';
	const coffeeMarkup =
		'<i class="bfa-inline-icon fas fa-coffee" data-bfa-icon-name="coffee" data-bfa-icon-style="solid" aria-hidden="true"></i>';
	const starMarkup =
		'<i class="bfa-inline-icon fas fa-star" data-bfa-icon-name="star" data-bfa-icon-style="solid" aria-hidden="true"></i>';
	expect( post.content ).toContain( `<p>${ flagMarkup }Leading icon text</p>` );
	expect( post.content ).toContain(
		`<p>Middle before ${ coffeeMarkup }after icon text${ coffeeMarkup }</p>`
	);
	expect( post.content ).toContain( `<p>Trailing icon text${ starMarkup }</p>` );
	expect( post.content ).toContain( `<h2 class="wp-block-heading">Heading ${ flagMarkup }icon text</h2>` );
	expect( post.content ).not.toContain( '\uFFFC' );
	expect( post.content ).not.toMatch( /[\u200B\u200C\u200D\uFEFF]/u );

	await page.getByRole( 'button', { name: 'Options', exact: true } ).first().click();
	await page.getByRole( 'menuitemradio', { name: 'Code editor' } ).click();
	const codeEditor = page.locator( '.editor-post-text-editor' );
	await expect( codeEditor ).toBeVisible();
	await expect( codeEditor ).toHaveValue( post.content );
	await page.getByText( 'Exit code editor', { exact: true } ).click();
	await expect( page.getByText( 'This block contains unexpected or invalid content.' ) ).toHaveCount( 0 );

	await page.reload();
	await page.waitForFunction( () => {
		return Boolean( window.wp?.data?.select( 'core/block-editor' ).getBlocks().length );
	} );
	await expect( page.getByText( 'This block contains unexpected or invalid content.' ) ).toHaveCount( 0 );
	const reloadedContent = await page.evaluate( () => {
		return window.wp.data.select( 'core/editor' ).getEditedPostContent();
	} );
	expect( reloadedContent ).toBe( post.content );

	await page.goto( '/wp-admin/plugins.php' );
	const pluginRow = page
		.locator( 'tr[data-plugin$="/better-font-awesome.php"]' )
		.filter( { hasText: 'Better Font Awesome' } );
	await pluginRow.getByRole( 'link', { name: 'Deactivate' } ).click();
	await expect( pluginRow.getByRole( 'link', { name: 'Activate' } ) ).toBeVisible();

	await page.goto( `/wp-admin/post.php?post=${ post.id }&action=edit` );
	await page.waitForFunction( () => {
		return Boolean( window.wp?.data?.select( 'core/block-editor' ).getBlocks().length );
	} );
	await expect( page.getByText( 'This block contains unexpected or invalid content.' ) ).toHaveCount( 0 );
	const inactiveContent = await page.evaluate( () => {
		return window.wp.data.select( 'core/editor' ).getEditedPostContent();
	} );
	expect( inactiveContent ).toBe( post.content );

	await page.goto( '/wp-admin/plugins.php' );
	await pluginRow.getByRole( 'link', { name: 'Activate' } ).click();
	await expect( pluginRow.getByRole( 'link', { name: 'Deactivate' } ) ).toBeVisible();

	await page.goto( post.link );
	await expect( page.locator( 'p:has-text("Leading icon text") .bfa-inline-icon.fa-flag' ) ).toBeVisible();
	await expect( page.locator( 'h2:has-text("Heading icon text") .bfa-inline-icon.fa-flag' ) ).toBeVisible();
	await expect( page.locator( 'p:has-text("Legacy") .fas.fa-star' ) ).toBeVisible();
	await expect( page.locator( '.wp-block-better-font-awesome-icon .fas.fa-flag' ) ).toBeVisible();
	expect( fontAwesomeStylesheetRequests ).toEqual(
		expect.arrayContaining( [
			expect.stringMatching(
				/^https:\/\/use\.fontawesome\.com\/releases\/[^/]+\/css\/all\.css\?ver=/
			),
		] )
	);
	expect( fontAwesomeErrors ).toEqual( [] );
} );

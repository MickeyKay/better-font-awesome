import { expect, test } from '@playwright/test';

const fontAwesomeStylesheetPattern =
	/\/vendor\/mickey-kay\/better-font-awesome-library\/inc\/font-awesome-7-fallback\/css\/all\.min\.css(?:\?.*)?$/;

const positionTolerance = 3;

async function expectIconPosition( block, icon, justification ) {
	const [ blockBox, iconBox ] = await Promise.all( [
		block.boundingBox(),
		icon.boundingBox(),
	] );
	expect( blockBox ).not.toBeNull();
	expect( iconBox ).not.toBeNull();
	expect( iconBox.x ).toBeGreaterThanOrEqual( blockBox.x - positionTolerance );
	expect( iconBox.x + iconBox.width ).toBeLessThanOrEqual(
		blockBox.x + blockBox.width + positionTolerance
	);

	if ( 'left' === justification ) {
		expect( Math.abs( iconBox.x - blockBox.x ) ).toBeLessThan( positionTolerance );
	} else if ( 'center' === justification ) {
		expect(
			Math.abs(
				blockBox.x + blockBox.width / 2 - ( iconBox.x + iconBox.width / 2 )
			)
		).toBeLessThan( positionTolerance );
	} else {
		expect(
			Math.abs( blockBox.x + blockBox.width - ( iconBox.x + iconBox.width ) )
		).toBeLessThan( positionTolerance );
	}
}

async function expectBlockWithin( block, boundary ) {
	const [ blockBox, boundaryBox ] = await Promise.all( [
		block.boundingBox(),
		boundary.boundingBox(),
	] );
	expect( blockBox ).not.toBeNull();
	expect( boundaryBox ).not.toBeNull();
	expect( blockBox.x ).toBeGreaterThanOrEqual( boundaryBox.x - positionTolerance );
	expect( blockBox.x + blockBox.width ).toBeLessThanOrEqual(
		boundaryBox.x + boundaryBox.width + positionTolerance
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

test( 'inserts, persists, and renders a native icon block', async ( { page } ) => {
	const fontAwesomeErrors = [];
	const fontAwesomeStylesheetRequests = [];
	page.on( 'request', ( request ) => {
		if ( fontAwesomeStylesheetPattern.test( request.url() ) ) {
			fontAwesomeStylesheetRequests.push( request.url() );
		}
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

	const post = await page.evaluate( async () => {
		const paragraph = window.wp.blocks.createBlock( 'core/paragraph', {
			content: 'Reference paragraph',
		} );
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
		window.wp.data
			.dispatch( 'core/block-editor' )
			.insertBlocks( [
				paragraph,
				leftBlock,
				centerBlock,
				rightBlock,
				row,
				columns,
			] );
		window.wp.data.dispatch( 'core/editor' ).editPost( {
			status: 'publish',
			title: 'Better Font Awesome block acceptance',
		} );
		await window.wp.data.dispatch( 'core/editor' ).savePost();

		return {
			centerClientId: centerBlock.clientId,
			id: window.wp.data.select( 'core/editor' ).getCurrentPostId(),
			leftClientId: leftBlock.clientId,
			link: window.wp.data.select( 'core/editor' ).getPermalink(),
			rightClientId: rightBlock.clientId,
		};
	} );
	await page.evaluate( ( clientId ) => {
		window.wp.data.dispatch( 'core/block-editor' ).selectBlock( clientId );
	}, post.leftClientId );
	const welcomeModal = page
		.locator( '.components-modal__screen-overlay' )
		.filter( { hasText: 'Welcome to the editor' } );
	if ( await welcomeModal.isVisible() ) {
		await welcomeModal.getByRole( 'button', { name: 'Close' } ).click();
	}
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

	const editor = page.frameLocator( 'iframe[name="editor-canvas"]' );
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
	const leftBlock = editor.locator(
		'.wp-block-better-font-awesome-icon.items-justified-left:has(.fas.fa-flag)'
	);
	const centerBlock = editor.locator(
		'.wp-block-better-font-awesome-icon.items-justified-center:has(.fas.fa-star)'
	);
	const rightBlock = editor.locator(
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
	await expect( referenceParagraph ).toBeVisible();
	await expect( centerIcon ).toBeVisible();
	await expect( rightIcon ).toBeVisible();
	await expect( row.locator( '.fas.fa-coffee' ) ).toBeVisible();
	await expect( columnBlock.locator( '.far.fa-heart' ) ).toBeVisible();
	await expect( leftBlock ).toHaveCSS( 'justify-content', 'flex-start' );
	await expect( centerBlock ).toHaveCSS( 'justify-content', 'center' );
	await expect( rightBlock ).toHaveCSS( 'justify-content', 'flex-end' );
	await expectBlockWithin( leftBlock, referenceParagraph );
	await expectBlockWithin( centerBlock, referenceParagraph );
	await expectBlockWithin( rightBlock, referenceParagraph );
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
	const attributes = await page.evaluate( () => {
		const blocks = window.wp.data.select( 'core/block-editor' ).getBlocks();
		const iconBlocks = blocks.filter(
			( item ) => 'better-font-awesome/icon' === item.name
		);
		const rowBlock = blocks.find( ( item ) => 'core/group' === item.name );
		const columnsBlock = blocks.find( ( item ) => 'core/columns' === item.name );
		const firstColumnBlock = columnsBlock.innerBlocks[ 0 ];

		return {
			center: iconBlocks[ 1 ].attributes,
			columnChildren: firstColumnBlock.innerBlocks.map( ( item ) => item.name ),
			columnIcon: firstColumnBlock.innerBlocks[ 1 ].attributes,
			columnsChildren: columnsBlock.innerBlocks.map( ( item ) => item.name ),
			left: iconBlocks[ 0 ].attributes,
			right: iconBlocks[ 2 ].attributes,
			rowChildren: rowBlock.innerBlocks.map( ( item ) => item.name ),
		};
	} );
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

	await page.goto( post.link );
	const frontendReference = page.getByText( 'Reference paragraph', { exact: true } );
	const frontendLeftBlock = page.locator(
		'.wp-block-better-font-awesome-icon.items-justified-left[role="img"]:has(.fas.fa-flag)'
	);
	const frontendCenterBlock = page.locator(
		'.wp-block-better-font-awesome-icon.items-justified-center:has(.fas.fa-star)'
	);
	const frontendRightBlock = page.locator(
		'.wp-block-better-font-awesome-icon.items-justified-right:has(.fas.fa-arrow-right)'
	);
	await expect( frontendLeftBlock ).toHaveAttribute( 'aria-label', 'Favorite' );
	await expect( frontendLeftBlock.locator( '.fas.fa-flag' ) ).toBeVisible();
	await expect( frontendCenterBlock ).toHaveCSS( 'justify-content', 'center' );
	await expect( frontendRightBlock ).toHaveCSS( 'justify-content', 'flex-end' );
	await expectBlockWithin( frontendLeftBlock, frontendReference );
	await expectBlockWithin( frontendCenterBlock, frontendReference );
	await expectBlockWithin( frontendRightBlock, frontendReference );
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
	expect( fontAwesomeErrors ).toEqual( [] );
} );

import { expect, test } from '@playwright/test';

const fontAwesomeStylesheetPattern =
	/\/vendor\/mickey-kay\/better-font-awesome-library\/inc\/font-awesome-7-fallback\/css\/all\.min\.css(?:\?.*)?$/;

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

async function setDocumentDirection( context, direction ) {
	await context.evaluate( async ( nextDirection ) => {
		document.documentElement.dir = nextDirection;
		await new Promise( ( resolve ) => requestAnimationFrame( resolve ) );
	}, direction );
}

async function expectJustificationGeometry( boundary, positions ) {
	await expectControlledBoundary( boundary );

	for ( const { block, icon, justification } of positions ) {
		await expectWrapperFillsBoundary( block, boundary );
		await expectIconPosition( block, icon, justification );
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
		window.wp.data
			.dispatch( 'core/block-editor' )
			.insertBlocks( [
				justificationBoundary,
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
	}, controlledBoundaryClass );
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

	const editorFrame = page.frame( { name: 'editor-canvas' } );
	expect( editorFrame ).not.toBeNull();
	await editorFrame.addStyleTag( { content: controlledBoundaryCss } );
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
	const editorJustifications = [
		{ block: leftBlock, icon: editorIcon, justification: 'left' },
		{ block: centerBlock, icon: centerIcon, justification: 'center' },
		{ block: rightBlock, icon: rightIcon, justification: 'right' },
	];
	await setDocumentDirection( editorFrame, 'ltr' );
	await expectJustificationGeometry(
		justificationBoundary,
		editorJustifications
	);
	await setDocumentDirection( editorFrame, 'rtl' );
	await expectJustificationGeometry(
		justificationBoundary,
		editorJustifications
	);
	for ( const block of [ leftBlock, centerBlock, rightBlock ] ) {
		await expect( block ).toHaveCSS( 'direction', 'ltr' );
	}
	await expect( leftBlock ).toHaveCSS( 'justify-content', 'flex-start' );
	await expect( centerBlock ).toHaveCSS( 'justify-content', 'center' );
	await expect( rightBlock ).toHaveCSS( 'justify-content', 'flex-end' );
	await setDocumentDirection( editorFrame, 'ltr' );
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

	await page.goto( post.link );
	await page.addStyleTag( { content: controlledBoundaryCss } );
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
	const frontendJustifications = [
		{
			block: frontendLeftBlock,
			icon: frontendLeftBlock.locator( '.fas.fa-flag' ),
			justification: 'left',
		},
		{
			block: frontendCenterBlock,
			icon: frontendCenterBlock.locator( '.fas.fa-star' ),
			justification: 'center',
		},
		{
			block: frontendRightBlock,
			icon: frontendRightBlock.locator( '.fas.fa-arrow-right' ),
			justification: 'right',
		},
	];
	await setDocumentDirection( page, 'ltr' );
	await expectJustificationGeometry( frontendBoundary, frontendJustifications );
	await setDocumentDirection( page, 'rtl' );
	await expectJustificationGeometry( frontendBoundary, frontendJustifications );
	for ( const block of [
		frontendLeftBlock,
		frontendCenterBlock,
		frontendRightBlock,
	] ) {
		await expect( block ).toHaveCSS( 'direction', 'ltr' );
	}
	await expect( frontendLeftBlock ).toHaveCSS(
		'justify-content',
		'flex-start'
	);
	await expect( frontendCenterBlock ).toHaveCSS( 'justify-content', 'center' );
	await expect( frontendRightBlock ).toHaveCSS(
		'justify-content',
		'flex-end'
	);
	await setDocumentDirection( page, 'ltr' );
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

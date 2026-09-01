import { expect, test } from '@playwright/test';

test( 'inserts, persists, and renders a native icon block', async ( { page } ) => {
	const fontAwesomeErrors = [];
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

	const post = await page.evaluate( async () => {
		const block = window.wp.blocks.createBlock( 'better-font-awesome/icon', {
			iconName: 'heart',
			iconStyle: 'regular',
			label: 'Favorite',
		} );
		window.wp.data.dispatch( 'core/block-editor' ).insertBlocks( block );
		window.wp.data.dispatch( 'core/editor' ).editPost( {
			status: 'publish',
			title: 'Better Font Awesome block acceptance',
		} );
		await window.wp.data.dispatch( 'core/editor' ).savePost();

		return {
			id: window.wp.data.select( 'core/editor' ).getCurrentPostId(),
			link: window.wp.data.select( 'core/editor' ).getPermalink(),
		};
	} );

	const editorIcon = page
		.frameLocator( 'iframe[name="editor-canvas"]' )
		.locator( '.wp-block-better-font-awesome-icon .far.fa-heart' );
	await expect( editorIcon ).toBeVisible();
	await expect( page.getByLabel( 'Icon', { exact: true } ) ).toBeVisible();

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
		return window.wp.data.select( 'core/block-editor' ).getBlocks()[ 0 ].attributes;
	} );
	expect( attributes ).toMatchObject( {
		iconName: 'heart',
		iconStyle: 'regular',
		label: 'Favorite',
	} );

	await page.goto( post.link );
	const frontendBlock = page.locator( '.wp-block-better-font-awesome-icon[role="img"]' );
	await expect( frontendBlock ).toHaveAttribute( 'aria-label', 'Favorite' );
	await expect( frontendBlock.locator( '.far.fa-heart' ) ).toBeVisible();
	expect( fontAwesomeErrors ).toEqual( [] );
} );

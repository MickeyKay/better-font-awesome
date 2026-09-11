import { expect } from '@playwright/test';

/** Exercise the legacy picker's keyup filter, not just its populated catalog. */
export async function checkPickerSearch( page, matchingSelectors ) {
	const opened = Date.now();
	await page.locator( '.bfa-iconpicker .iconpicker-component' ).first().click();
	const search = page.locator( '.iconpicker-search' ).filter( { visible: true } ).first();
	await expect( search ).toBeVisible();
	const picker = search.locator( 'xpath=ancestor::div[contains(@class,"iconpicker-popover")]' );
	const matches = matchingSelectors.map( selector => picker.locator( `.iconpicker-item ${ selector }` ).first() );
	const unrelated = picker.locator( '.iconpicker-item .fab.fa-github' ).first();
	// Negative assertions below are meaningful only if this result existed first.
	await expect( unrelated ).toBeVisible();
	for ( const match of matches ) { await expect( match ).toBeVisible(); }
	const timing = { openingMs: Date.now() - opened, queries: [] };

	async function query( value, matchingVisible, unrelatedVisible ) {
		const started = Date.now();
		await search.press( 'ControlOrMeta+A' );
		await search.press( 'Backspace' );
		// fill() does not dispatch the keyup events used by this picker.
		await search.pressSequentially( value );
		await expect( search ).toHaveValue( value );
		for ( const match of matches ) {
			if ( matchingVisible ) { await expect( match ).toBeVisible(); }
			else { await expect( match ).toBeHidden(); }
		}
		if ( unrelatedVisible ) { await expect( unrelated ).toBeVisible(); }
		else { await expect( unrelated ).toBeHidden(); }
		timing.queries.push( { query: value, ms: Date.now() - started } );
	}

	await query( 'pro fixture', true, false );
	await query( 'github', false, true );
	await query( '', true, true );
	await query( 'pro fixture', true, false );
	return timing;
}

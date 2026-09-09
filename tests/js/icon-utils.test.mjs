import assert from 'node:assert/strict';
import test from 'node:test';

import {
	buildCatalogOptions,
	filterCatalog,
	getAvailableStyles,
	groupCatalog,
	selectIcon,
	styleClass,
} from '../../src/icon-utils.mjs';

const catalog = [
	{ label: 'Address Book (regular)', name: 'address-book', style: 'regular' },
	{ label: 'Coffee (solid)', name: 'coffee', style: 'solid' },
];

test( 'derives unique Free styles from the active catalog in control order', () => {
	const icons = [ 'regular', 'light', 'solid', 'thin', 'regular', 'sharp', 'duotone' ]
		.map( ( style ) => ( { name: 'heart', style } ) );
	assert.deepEqual( getAvailableStyles( icons, 'heart' ), [ 'solid', 'regular' ] );
	assert.deepEqual( getAvailableStyles( icons.slice( 0, 2 ), 'heart' ), [ 'regular' ] );
} );

test( 'offers only the catalog style for single-style and brand icons', () => {
	const icons = [
		{ name: 'coffee', style: 'solid' },
		{ name: 'github', style: 'brands' },
		{ name: 'heart', style: 'regular' },
	];
	assert.deepEqual( getAvailableStyles( icons, 'coffee' ), [ 'solid' ] );
	assert.deepEqual( getAvailableStyles( icons, 'github' ), [ 'brands' ] );
	assert.deepEqual( getAvailableStyles( icons, 'heart' ), [ 'regular' ] );
} );

test( 'does not invent styles for missing icons, empty catalogs, or unsupported styles', () => {
	assert.deepEqual( getAvailableStyles( catalog, 'missing' ), [] );
	assert.deepEqual( getAvailableStyles( [], 'heart' ), [] );
	assert.deepEqual( getAvailableStyles( [ { name: 'heart', style: 'light' } ], 'heart' ), [] );
	assert.deepEqual( getAvailableStyles( catalog, '' ), [] );
} );

const fullCatalog = [
	{ label: 'Address Book (regular)', name: 'address-book', style: 'regular' },
	{ label: 'Address Book (solid)', name: 'address-book', style: 'solid' },
	{ label: 'Coffee (solid)', name: 'coffee', style: 'solid' },
	{ label: 'Github (brands)', name: 'github', style: 'brands' },
	{ label: 'Heart (regular)', name: 'heart', style: 'regular' },
	{ label: 'Heart (solid)', name: 'heart', style: 'solid' },
];
const icons = groupCatalog( fullCatalog );

test( 'groups supported catalog rows by name with base labels and unique counts', () => {
	const input = [ ...fullCatalog, fullCatalog[ 0 ], { label: 'Pro (light)', name: 'pro', style: 'light' } ];
	const before = structuredClone( input );
	const grouped = groupCatalog( input );
	assert.equal( grouped.length, 4 );
	assert.deepEqual( grouped.map( ( icon ) => icon.label ), [ 'Address Book', 'Coffee', 'Github', 'Heart' ] );
	assert.deepEqual( grouped[ 0 ].styles, [ 'solid', 'regular' ] );
	assert.deepEqual( input, before );
	assert.deepEqual( groupCatalog( [] ), [] );
	assert.equal( groupCatalog( fullCatalog.slice( 0, 2 ) ).length, 1 );
} );

test( 'removes only the trailing Free style suffix', () => {
	assert.equal( groupCatalog( [ { name: 'example', label: 'Example (alternate) (regular)', style: 'regular' } ] )[ 0 ].label, 'Example (alternate)' );
	assert.equal( groupCatalog( [ { name: 'example', label: 'Example', style: 'solid' } ] )[ 0 ].label, 'Example' );
} );

test( 'searches base labels, slugs, and original style-labelled queries without duplicates', () => {
	for ( const query of [ ' ADDRESS BOOK ', 'address-book', 'Address Book (regular)', 'address book (solid)' ] ) {
		assert.deepEqual( filterCatalog( icons, query ).map( ( icon ) => icon.name ), [ 'address-book' ] );
	}
	assert.deepEqual( filterCatalog( icons, 'REGULAR' ).map( ( icon ) => icon.name ), [ 'address-book', 'heart' ] );
	assert.deepEqual( filterCatalog( icons, 'missing' ), [] );
	assert.deepEqual( filterCatalog( icons, '' ), icons );
} );

test( 'keeps whitespace queries visible through the native combobox label filter', () => {
	for ( const query of [ 'Address Book ', ' Address Book', ' ADDRESS BOOK ', 'address-book ', 'Address Book (regular) ', '   ' ] ) {
		const options = buildCatalogOptions( icons, query, 'heart', 'solid' );
		// WordPress 6.5 filters the supplied labels against the untrimmed input.
		const visible = options.filter( ( option ) => option.label.toLowerCase().includes( query.toLowerCase() ) );
		assert.deepEqual( visible, options );
		assert.equal( visible.find( ( option ) => option.value === 'address-book' ).iconLabel, 'Address Book' );
	}
} );

test( 'limits unique results after grouping and keeps the selected icon once', () => {
	const catalog = Array.from( { length: 102 }, ( _, index ) => [ 'regular', 'solid' ].map( ( style ) => ( {
		name: `icon-${ index }`, label: `Icon ${ index } (${ style })`, style,
	} ) ) ).flat();
	const grouped = groupCatalog( catalog );
	const options = buildCatalogOptions( grouped, '', 'icon-101', 'regular' );
	assert.equal( options.length, 101 );
	assert.equal( new Set( options.map( ( option ) => option.value ) ).size, 101 );
	assert.equal( options[ 0 ].value, 'icon-101' );
	assert.equal( options[ 100 ].value, 'icon-99' );
	assert.equal( buildCatalogOptions( grouped, '', 'icon-0', 'solid' ).length, 100 );
	assert.equal( buildCatalogOptions( grouped, '', 'missing', 'solid' ).length, 100 );
	assert.deepEqual( buildCatalogOptions( grouped, 'no matches', 'icon-101', 'regular' ).map( ( option ) => option.value ), [ 'icon-101' ] );
} );

test( 'uses all styles even when a query matches only one original catalog row', () => {
	const options = buildCatalogOptions( icons, 'Heart (regular)', 'coffee', 'solid' );
	const heart = options.find( ( option ) => option.value === 'heart' );
	assert.equal( heart.style, 'solid' );
	assert.equal( heart.iconLabel, 'Heart' );
	// WordPress's own label filter must also allow the result through.
	assert.ok( heart.label.includes( 'Heart (regular)' ) );
	assert.equal( buildCatalogOptions( icons, '', 'heart', 'solid' ).find( ( option ) => option.value === 'heart' ).label, 'Heart' );
} );

test( 'preserves supported styles and falls back deterministically for explicit new icons', () => {
	assert.deepEqual( selectIcon( icons, 'address-book', 'heart', 'regular' ), { iconName: 'address-book', iconStyle: 'regular' } );
	assert.deepEqual( selectIcon( icons, 'address-book', 'github', 'brands' ), { iconName: 'address-book', iconStyle: 'solid' } );
	assert.deepEqual( selectIcon( icons, 'coffee', 'heart', 'regular' ), { iconName: 'coffee', iconStyle: 'solid' } );
	assert.deepEqual( selectIcon( icons, 'github', 'heart', 'regular' ), { iconName: 'github', iconStyle: 'brands' } );
	const withoutSolid = groupCatalog( [
		{ name: 'example', label: 'Example (brands)', style: 'brands' },
		{ name: 'example', label: 'Example (regular)', style: 'regular' },
	] );
	assert.equal( selectIcon( withoutSolid, 'example', 'missing', 'unknown' ).iconStyle, 'regular' );
} );

test( 'each new icon preview uses exactly the style that selecting it will choose', () => {
	for ( const style of [ 'regular', 'solid', 'brands', '', 'unknown' ] ) {
		for ( const option of buildCatalogOptions( icons, '', 'missing', style ) ) {
			assert.equal( option.style, selectIcon( icons, option.value, 'missing', style ).iconStyle );
		}
	}
} );

test( 'missing and already-selected icons never normalize saved styles', () => {
	for ( const style of [ 'regular', 'brands', '', 'unknown' ] ) {
		assert.equal( selectIcon( icons, 'heart', 'heart', style ), null );
		assert.equal( selectIcon( icons, 'missing', 'heart', style ), null );
		assert.equal( selectIcon( icons, null, 'heart', style ), null );
		assert.equal( buildCatalogOptions( icons, 'regular', 'heart', style ).find( ( icon ) => icon.value === 'heart' ).style, style );
	}
	assert.deepEqual( buildCatalogOptions( [], '', 'missing', 'unknown' ), [] );
	assert.deepEqual( selectIcon( icons, 'heart', 'missing', 'unknown' ), { iconName: 'heart', iconStyle: 'solid' } );
} );

test( 'maps supported styles and defaults safely', () => {
	assert.equal( styleClass( 'brands' ), 'fab' );
	assert.equal( styleClass( 'regular' ), 'far' );
	assert.equal( styleClass( 'solid' ), 'fas' );
	assert.equal( styleClass( 'unsupported' ), 'fas' );
} );

import assert from 'node:assert/strict';
import test from 'node:test';

import {
	buildCatalogOptions,
	filterCatalog,
	getAvailableStyles,
	parseSelection,
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

test( 'finds all styles beyond the combined picker result limit and search filter', () => {
	const icons = [
		...Array.from( { length: 102 }, ( _, index ) => ( {
			label: `Icon ${ index }`, name: `icon-${ index }`, style: 'solid',
		} ) ),
		{ label: 'Heart (solid)', name: 'heart', style: 'solid' },
		{ label: 'Heart (regular)', name: 'heart', style: 'regular' },
	];
	for ( const search of [ '', 'regular', 'no matches' ] ) {
		const results = buildCatalogOptions( icons, search, 'regular:heart' );
		assert.equal( results.some( ( icon ) => icon.value === 'solid:heart' ), false );
		assert.deepEqual( getAvailableStyles( icons, 'heart' ), [ 'solid', 'regular' ] );
	}
} );

test( 'filters the icon catalog by label or slug', () => {
	assert.deepEqual( filterCatalog( catalog, 'REGULAR' ), [ catalog[ 0 ] ] );
	assert.deepEqual( filterCatalog( catalog, 'coffee' ), [ catalog[ 1 ] ] );
	assert.deepEqual( filterCatalog( catalog, '' ), catalog );
} );

test( 'keeps the selected icon available beyond the result limit', () => {
	const largeCatalog = Array.from( { length: 102 }, ( value, index ) => ( {
		label: `Icon ${ index }`,
		name: `icon-${ index }`,
		style: 'solid',
	} ) );
	const options = buildCatalogOptions( largeCatalog, '', 'solid:icon-101' );

	assert.equal( options.length, 101 );
	assert.deepEqual( options[ 0 ], {
		label: 'Icon 101',
		name: 'icon-101',
		style: 'solid',
		value: 'solid:icon-101',
	} );
	assert.equal(
		options.filter( ( option ) => 'solid:icon-101' === option.value ).length,
		1
	);
} );

test( 'does not duplicate a selected icon already in the results', () => {
	const options = buildCatalogOptions( catalog, '', 'regular:address-book' );

	assert.deepEqual( options, [
		{
			label: catalog[ 0 ].label,
			name: 'address-book',
			style: 'regular',
			value: 'regular:address-book',
		},
		{
			label: catalog[ 1 ].label,
			name: 'coffee',
			style: 'solid',
			value: 'solid:coffee',
		},
	] );
} );

test( 'parses only supported complete selections', () => {
	assert.deepEqual( parseSelection( 'regular:address-book' ), {
		name: 'address-book',
		style: 'regular',
	} );
	assert.equal( parseSelection( 'unsupported:address-book' ), null );
	assert.equal( parseSelection( 'regular:' ), null );
	assert.equal( parseSelection( '' ), null );
} );

test( 'maps supported styles and defaults safely', () => {
	assert.equal( styleClass( 'brands' ), 'fab' );
	assert.equal( styleClass( 'regular' ), 'far' );
	assert.equal( styleClass( 'solid' ), 'fas' );
	assert.equal( styleClass( 'unsupported' ), 'fas' );
} );

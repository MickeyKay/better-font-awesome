import assert from 'node:assert/strict';
import test from 'node:test';

import {
	buildCatalogOptions,
	filterCatalog,
	parseSelection,
	styleClass,
} from '../../src/icon-utils.mjs';

const catalog = [
	{ label: 'Address Book (regular)', name: 'address-book', style: 'regular' },
	{ label: 'Coffee (solid)', name: 'coffee', style: 'solid' },
];

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

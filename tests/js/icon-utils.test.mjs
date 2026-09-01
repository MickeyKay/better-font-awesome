import assert from 'node:assert/strict';
import test from 'node:test';

import { filterCatalog, parseSelection, styleClass } from '../../src/icon-utils.mjs';

const catalog = [
	{ label: 'Address Book (regular)', name: 'address-book', style: 'regular' },
	{ label: 'Coffee (solid)', name: 'coffee', style: 'solid' },
];

test( 'filters the icon catalog by label or slug', () => {
	assert.deepEqual( filterCatalog( catalog, 'REGULAR' ), [ catalog[ 0 ] ] );
	assert.deepEqual( filterCatalog( catalog, 'coffee' ), [ catalog[ 1 ] ] );
	assert.deepEqual( filterCatalog( catalog, '' ), catalog );
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

import assert from 'node:assert/strict';
import test from 'node:test';

import {
	buildInlineIconAttributes,
	INLINE_ICON_FORMAT_NAME,
	selectionFromAttributes,
} from '../../src/inline-icon-utils.mjs';

const catalog = [
	{ label: 'Flag (solid)', name: 'flag', style: 'solid' },
	{ label: 'Star (regular)', name: 'star', style: 'regular' },
];

test( 'uses a stable namespaced inline format name', () => {
	assert.equal( INLINE_ICON_FORMAT_NAME, 'better-font-awesome/inline-icon' );
} );

test( 'builds decorative attributes only for an exact catalog entry', () => {
	assert.deepEqual( buildInlineIconAttributes( catalog, 'solid:flag' ), {
		ariaHidden: 'true',
		className: 'fas fa-flag',
		iconName: 'flag',
		iconStyle: 'solid',
	} );
	assert.equal( buildInlineIconAttributes( catalog, 'brands:flag' ), null );
	assert.equal( buildInlineIconAttributes( catalog, 'solid:script-alert' ), null );
} );

test( 'restores a selector value from stored format attributes', () => {
	assert.equal(
		selectionFromAttributes( { iconName: 'star', iconStyle: 'regular' } ),
		'regular:star'
	);
	assert.equal( selectionFromAttributes( {} ), '' );
	assert.equal( selectionFromAttributes(), '' );
} );

import { findCatalogIcon, styleClass } from './icon-utils.mjs';

export const INLINE_ICON_FORMAT_NAME = 'better-font-awesome/inline-icon';

export function buildInlineIconAttributes( catalog, value ) {
	const icon = findCatalogIcon( catalog, value );
	if ( ! icon ) {
		return null;
	}

	return {
		className: `${ styleClass( icon.style ) } fa-${ icon.name }`,
		iconName: icon.name,
		iconStyle: icon.style,
		ariaHidden: 'true',
	};
}

export function selectionFromAttributes( attributes ) {
	if ( ! attributes?.iconName || ! attributes?.iconStyle ) {
		return '';
	}

	return `${ attributes.iconStyle }:${ attributes.iconName }`;
}

const STYLE_CLASSES = {
	brands: 'fab',
	regular: 'far',
	solid: 'fas',
};

export function filterCatalog( catalog, filterValue ) {
	const needle = filterValue.trim().toLowerCase();

	return catalog.filter( ( icon ) => {
		return (
			! needle ||
			icon.label.toLowerCase().includes( needle ) ||
			icon.name.includes( needle )
		);
	} );
}

export function buildCatalogOptions( catalog, filterValue, selectedValue, limit = 100 ) {
	const icons = filterCatalog( catalog, filterValue ).slice( 0, limit );
	const selectedIcon = catalog.find( ( icon ) => {
		return `${ icon.style }:${ icon.name }` === selectedValue;
	} );
	const includesSelectedIcon = icons.some( ( icon ) => {
		return `${ icon.style }:${ icon.name }` === selectedValue;
	} );

	if ( selectedIcon && ! includesSelectedIcon ) {
		icons.unshift( selectedIcon );
	}

	return icons.map( ( icon ) => ( {
		label: icon.label,
		name: icon.name,
		style: icon.style,
		value: `${ icon.style }:${ icon.name }`,
	} ) );
}

export function parseSelection( value ) {
	if ( ! value || ! value.includes( ':' ) ) {
		return null;
	}

	const [ style, ...nameParts ] = value.split( ':' );
	const name = nameParts.join( ':' );
	if ( ! STYLE_CLASSES[ style ] || ! name ) {
		return null;
	}

	return { name, style };
}

export function styleClass( style ) {
	return STYLE_CLASSES[ style ] ?? STYLE_CLASSES.solid;
}

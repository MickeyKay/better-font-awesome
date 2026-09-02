const STYLE_CLASSES = {
	brands: 'fab',
	regular: 'far',
	solid: 'fas',
};

export function filterCatalog( catalog, filterValue ) {
	const needle = filterValue.trim().toLowerCase();

	return catalog.filter( ( icon ) => {
		const searchTerms = Array.isArray( icon.searchTerms )
			? icon.searchTerms
			: [ icon.searchTerms ];

		return (
			! needle ||
			icon.label.toLowerCase().includes( needle ) ||
			icon.name.includes( needle ) ||
			searchTerms.some( ( term ) => {
				return 'string' === typeof term && term.toLowerCase().includes( needle );
			} )
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

export function findCatalogIcon( catalog, value ) {
	const selection = parseSelection( value );
	if ( ! selection ) {
		return null;
	}

	return (
		catalog.find( ( icon ) => {
			return icon.name === selection.name && icon.style === selection.style;
		} ) ?? null
	);
}

export function styleClass( style ) {
	return STYLE_CLASSES[ style ] ?? STYLE_CLASSES.solid;
}

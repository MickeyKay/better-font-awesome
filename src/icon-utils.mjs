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

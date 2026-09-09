const STYLE_CLASSES = {
	brands: 'fab',
	regular: 'far',
	solid: 'fas',
};
const SUPPORTED_STYLES = [ 'solid', 'regular', 'brands' ];

export function getAvailableStyles( catalog, iconName ) {
	return SUPPORTED_STYLES.filter( ( style ) =>
		catalog.some( ( icon ) => icon.name === iconName && icon.style === style )
	);
}

export function groupCatalog( catalog ) {
	const icons = new Map();
	for ( const icon of catalog ) {
		if ( ! SUPPORTED_STYLES.includes( icon.style ) ) {
			continue;
		}
		if ( ! icons.has( icon.name ) ) {
			icons.set( icon.name, {
				name: icon.name,
				label: icon.label.replace( / \((?:solid|regular|brands)\)$/, '' ),
				styles: [],
				searchLabels: [],
			} );
		}
		const entry = icons.get( icon.name );
		entry.styles = SUPPORTED_STYLES.filter( ( style ) => style === icon.style || entry.styles.includes( style ) );
		entry.searchLabels.push( icon.label );
	}
	return Array.from( icons.values() );
}

export function filterCatalog( icons, filterValue ) {
	const needle = filterValue.trim().toLowerCase();
	return icons.filter( ( icon ) => ! needle ||
		icon.label.toLowerCase().includes( needle ) ||
		icon.name.includes( needle ) ||
		icon.searchLabels.some( ( label ) => label.toLowerCase().includes( needle ) )
	);
}

function selectionStyle( icon, currentStyle ) {
	return icon.styles.includes( currentStyle ) ? currentStyle : icon.styles[ 0 ];
}

export function selectIcon( icons, name, selectedName, currentStyle ) {
	const icon = icons.find( ( item ) => item.name === name );
	if ( ! icon || name === selectedName ) {
		return null;
	}
	return { iconName: name, iconStyle: selectionStyle( icon, currentStyle ) };
}

export function buildCatalogOptions( catalog, filterValue, selectedName, currentStyle, limit = 100 ) {
	const icons = filterCatalog( catalog, filterValue ).slice( 0, limit );
	const selectedIcon = catalog.find( ( icon ) => icon.name === selectedName );
	if ( selectedIcon && ! icons.includes( selectedIcon ) ) {
		icons.unshift( selectedIcon );
	}
	return icons.map( ( icon ) => ( {
		// WP 6.5 also filters labels against the raw input, including whitespace.
		// Retain matches internally; renderIconOption displays only iconLabel. Clear the search
		// when leaving the picker so its closed value also uses the base label.
		label: filterValue && ! icon.label.toLowerCase().includes( filterValue.toLowerCase() )
			? `${ icon.label } ${ filterValue }` : icon.label,
		iconLabel: icon.label,
		name: icon.name,
		style: icon.name === selectedName ? currentStyle : selectionStyle( icon, currentStyle ),
		value: icon.name,
	} ) );
}

export function styleClass( style ) {
	return STYLE_CLASSES[ style ] ?? STYLE_CLASSES.solid;
}

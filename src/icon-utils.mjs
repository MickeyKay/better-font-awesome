const STYLE_CLASSES = {
	brands: 'fab',
	regular: 'far',
	solid: 'fas',
	light: 'fal',
	thin: 'fat',
};
const SUPPORTED_STYLES = [ 'solid', 'regular', 'brands', 'light', 'thin' ];
const supportedStyles = () => [ ...SUPPORTED_STYLES, ...Object.keys( globalThis.bfaBlockEditor?.appearanceClasses ?? {} ).filter( style => ! SUPPORTED_STYLES.includes( style ) ) ];

export function getAvailableStyles( catalog, iconName ) {
	return supportedStyles().filter( ( style ) =>
		catalog.some( ( icon ) => icon.name === iconName && icon.style === style )
	);
}

export function groupCatalog( catalog ) {
	const icons = new Map();
	const styles = supportedStyles();
	for ( const icon of catalog ) {
		if ( ! styles.includes( icon.style ) ) {
			continue;
		}
		if ( ! icons.has( icon.name ) ) {
			icons.set( icon.name, {
				name: icon.name,
				label: icon.label.replace( / \([a-z-]+\)$/, '' ),
				styles: [],
				searchLabels: [],
			} );
		}
		const entry = icons.get( icon.name );
		entry.styles = styles.filter( ( style ) => style === icon.style || entry.styles.includes( style ) );
		entry.searchLabels.push( icon.label );
		if ( typeof icon.searchTerms === 'string' ) { entry.searchLabels.push( icon.searchTerms ); }
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

export function resolveStyle( availableStyles, requestedStyle ) {
	const requested = requestedStyle !== 'brands' && supportedStyles().includes( requestedStyle ) ? requestedStyle : 'solid';
	return availableStyles.includes( requested ) ? requested : availableStyles[ 0 ] ?? requested;
}

export function selectIcon( icons, name, selectedName, currentStyle ) {
	const icon = icons.find( ( item ) => item.name === name );
	if ( ! icon || name === selectedName ) {
		return null;
	}
	return { iconName: name, iconStyle: currentStyle === 'site-default' ? currentStyle : selectionStyle( icon, currentStyle ) };
}

export function buildCatalogOptions( catalog, filterValue, selectedName, currentStyle, limit = 100, siteDefault = 'solid' ) {
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
		style: currentStyle === 'site-default'
			? resolveStyle( icon.styles, siteDefault )
			: icon.name === selectedName ? currentStyle : selectionStyle( icon, currentStyle ),
		value: icon.name,
	} ) );
}

export function styleClass( style ) {
	return globalThis.bfaBlockEditor?.appearanceClasses?.[ style ] ?? STYLE_CLASSES[ style ] ?? STYLE_CLASSES.solid;
}

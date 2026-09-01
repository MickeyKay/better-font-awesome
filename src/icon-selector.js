import { buildCatalogOptions, findCatalogIcon, styleClass } from './icon-utils.mjs';

const { __, sprintf } = wp.i18n;
const { ComboboxControl } = wp.components;
const { useMemo, useState } = wp.element;

export const getCatalog = () => window.bfaBlockEditor?.icons ?? [];

const renderIcon = ( icon ) => (
	<i
		className={ `${ styleClass( icon.style ) } fa-${ icon.name }` }
		aria-hidden="true"
	/>
);

const renderIconOption = ( { item } ) => (
	<span className="bfa-icon-selector__option">
		{ renderIcon( item ) }
		<span>{ item.label }</span>
	</span>
);

export default function IconSelector( { onChange, value } ) {
	const [ filterValue, setFilterValue ] = useState( '' );
	const catalog = getCatalog();
	const selectedIcon = findCatalogIcon( catalog, value );
	const options = useMemo( () => {
		return buildCatalogOptions( catalog, filterValue, value );
	}, [ catalog, filterValue, value ] );

	return (
		<div className="bfa-icon-selector">
			<ComboboxControl
				label={ __( 'Icon', 'better-font-awesome' ) }
				value={ value }
				options={ options }
				onChange={ onChange }
				onFilterValueChange={ setFilterValue }
				__experimentalRenderItem={ renderIconOption }
				help={ sprintf(
					/* translators: %d is the number of available icon and style options. */
					__(
						'Search all %d available Font Awesome Free icon and style options.',
						'better-font-awesome'
					),
					catalog.length
				) }
			/>
			{ selectedIcon && (
				<div className="bfa-icon-selector__selected">
					{ renderIcon( selectedIcon ) }
					<span>{ selectedIcon.label }</span>
				</div>
			) }
		</div>
	);
}

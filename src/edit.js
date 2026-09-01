import { filterCatalog, parseSelection, styleClass } from './icon-utils.mjs';

const { __, sprintf } = wp.i18n;
const { InspectorControls, useBlockProps } = wp.blockEditor;
const { ComboboxControl, PanelBody, TextControl } = wp.components;
const { useMemo, useState } = wp.element;

const getCatalog = () => window.bfaBlockEditor?.icons ?? [];

export default function Edit( { attributes, setAttributes } ) {
	const { iconName, iconStyle, label } = attributes;
	const [ filterValue, setFilterValue ] = useState( '' );
	const catalog = getCatalog();
	const options = useMemo( () => {
		const needle = filterValue.trim().toLowerCase();

		return filterCatalog( catalog, needle )
			.slice( 0, 100 )
			.map( ( icon ) => ( {
				label: icon.label,
				value: `${ icon.style }:${ icon.name }`,
			} ) );
	}, [ catalog, filterValue ] );
	const selectedValue = `${ iconStyle }:${ iconName }`;
	const selectedIcon = catalog.find(
		( icon ) => `${ icon.style }:${ icon.name }` === selectedValue
	);
	const iconLabel = selectedIcon?.label ?? iconName;
	const blockProps = useBlockProps( {
		className: 'bfa-icon-block-editor',
	} );

	const onSelectIcon = ( value ) => {
		if ( ! value || ! value.includes( ':' ) ) {
			return;
		}

		const selection = parseSelection( value );
		if ( ! selection ) {
			return;
		}

		setAttributes( {
			iconName: selection.name,
			iconStyle: selection.style,
		} );
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Icon settings', 'better-font-awesome' ) }>
					<ComboboxControl
						label={ __( 'Icon', 'better-font-awesome' ) }
						value={ selectedValue }
						options={ options }
						onChange={ onSelectIcon }
						onFilterValueChange={ setFilterValue }
						help={ __(
							'Search the current Font Awesome Free catalog.',
							'better-font-awesome'
						) }
					/>
					<TextControl
						label={ __( 'Accessible label', 'better-font-awesome' ) }
						value={ label }
						onChange={ ( value ) => setAttributes( { label: value } ) }
						help={ __(
							'Leave empty when the icon is decorative.',
							'better-font-awesome'
						) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<i
					className={ `${ styleClass( iconStyle ) } fa-${ iconName }` }
					aria-hidden="true"
				/>
				<span className="screen-reader-text">
					{ sprintf(
						/* translators: %s is the selected icon label. */
						__( 'Font Awesome icon: %s', 'better-font-awesome' ),
						iconLabel
					) }
				</span>
			</div>
		</>
	);
}

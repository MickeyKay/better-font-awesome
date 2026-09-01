import IconSelector, { getCatalog } from './icon-selector';
import { findCatalogIcon, styleClass } from './icon-utils.mjs';

const { __, sprintf } = wp.i18n;
const { InspectorControls, useBlockProps } = wp.blockEditor;
const {
	PanelBody,
	TextControl,
	__experimentalVStack: VStack,
} = wp.components;

export default function Edit( { attributes, setAttributes } ) {
	const { iconName, iconStyle, label } = attributes;
	const catalog = getCatalog();
	const selectedValue = `${ iconStyle }:${ iconName }`;
	const selectedIcon = findCatalogIcon( catalog, selectedValue );
	const iconLabel = selectedIcon?.label ?? iconName;
	const blockProps = useBlockProps( {
		className: 'bfa-icon-block-editor',
	} );

	const onSelectIcon = ( value ) => {
		if ( ! value || ! value.includes( ':' ) ) {
			return;
		}

		const selection = findCatalogIcon( catalog, value );
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
					<VStack spacing={ 4 }>
						<IconSelector value={ selectedValue } onChange={ onSelectIcon } />
						<TextControl
							label={ __( 'Accessible label', 'better-font-awesome' ) }
							value={ label }
							onChange={ ( value ) => setAttributes( { label: value } ) }
							help={ __(
								'Leave empty when the icon is decorative.',
								'better-font-awesome'
							) }
						/>
					</VStack>
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

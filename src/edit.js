import { buildCatalogOptions, getAvailableStyles, groupCatalog, selectIcon, styleClass } from './icon-utils.mjs';

const { __, sprintf } = wp.i18n;
const {
	BlockControls,
	InspectorControls,
	JustifyContentControl,
	useBlockProps,
} = wp.blockEditor;
const {
	ComboboxControl,
	PanelBody,
	SelectControl,
	TextControl,
	__experimentalVStack: VStack,
} = wp.components;
const { useMemo, useState } = wp.element;

const getCatalog = () => window.bfaBlockEditor?.icons ?? [];

const renderIconOption = ( { item } ) => (
	<span
		style={ {
			alignItems: 'center',
			display: 'inline-flex',
			gap: '8px',
		} }
	>
		<i
			className={ `${ styleClass( item.style ) } fa-${ item.name }` }
			aria-hidden="true"
			style={ {
				textAlign: 'center',
				width: '1.25em',
			} }
		/>
		<span>{ item.iconLabel }</span>
	</span>
);

export default function Edit( { attributes, setAttributes } ) {
	const { iconJustification, iconName, iconStyle, label } = attributes;
	const justification = [ 'center', 'right' ].includes( iconJustification )
		? iconJustification
		: 'left';
	const [ filterValue, setFilterValue ] = useState( '' );
	const catalog = getCatalog();
	const icons = useMemo( () => groupCatalog( catalog ), [ catalog ] );
	const options = useMemo( () => {
		return buildCatalogOptions( icons, filterValue, iconName, iconStyle );
	}, [ icons, filterValue, iconName, iconStyle ] );
	const selectedIcon = catalog.find(
		( icon ) => icon.name === iconName && icon.style === iconStyle
	);
	const availableStyles = useMemo(
		() => getAvailableStyles( catalog, iconName ),
		[ catalog, iconName ]
	);
	const styleLabels = {
		solid: __( 'Solid', 'better-font-awesome' ),
		regular: __( 'Regular', 'better-font-awesome' ),
		brands: __( 'Brands', 'better-font-awesome' ),
	};
	const styleAvailable = availableStyles.includes( iconStyle );
	const styleOptions = availableStyles.map( ( style ) => ( {
		label: styleLabels[ style ],
		value: style,
	} ) );
	if ( ! styleAvailable ) {
		styleOptions.unshift( {
			label: sprintf(
				/* translators: %s is the saved icon style. */
				__( 'Unavailable (%s)', 'better-font-awesome' ),
				styleLabels[ iconStyle ] ?? iconStyle
			),
			value: iconStyle,
			disabled: true,
		} );
	}
	const iconLabel = selectedIcon?.label ?? iconName;
	const blockProps = useBlockProps( {
		className: `bfa-icon-block-editor items-justified-${ justification }`,
	} );

	const onSelectIcon = ( value ) => {
		setFilterValue( '' );
		const selection = selectIcon( icons, value, iconName, iconStyle );
		if ( selection ) {
			setAttributes( selection );
		}
	};

	return (
		<>
			<BlockControls group="block">
				<JustifyContentControl
					allowedControls={ [ 'left', 'center', 'right' ] }
					value={ justification }
					onChange={ ( value ) =>
						setAttributes( {
							iconJustification: value ?? 'left',
						} )
					}
				/>
			</BlockControls>
			<InspectorControls>
				<PanelBody title={ __( 'Icon settings', 'better-font-awesome' ) }>
					<VStack spacing={ 4 }>
						<div
							onBlur={ ( event ) => {
								if ( ! event.currentTarget.contains( event.relatedTarget ) ) {
									setFilterValue( '' );
								}
							} }
							onKeyDown={ ( event ) => {
								if ( event.key === 'Escape' ) {
									setFilterValue( '' );
								}
							} }
						>
							<ComboboxControl
								label={ __( 'Icon', 'better-font-awesome' ) }
								value={ iconName }
								options={ options }
								onChange={ onSelectIcon }
								onFilterValueChange={ setFilterValue }
								__experimentalRenderItem={ renderIconOption }
								help={ sprintf(
									/* translators: %d is the number of unique selectable icons. */
									__(
										'Search all %d available Font Awesome Free icons.',
										'better-font-awesome'
									),
									icons.length
								) }
							/>
						</div>
						<SelectControl
							__nextHasNoMarginBottom
							label={ __( 'Style', 'better-font-awesome' ) }
							value={ iconStyle }
							options={ styleOptions }
							disabled={ availableStyles.length === 0 || ( styleAvailable && availableStyles.length === 1 ) }
							onChange={ ( value ) => {
								if ( availableStyles.includes( value ) ) {
									setAttributes( { iconStyle: value } );
								}
							} }
							help={ ! styleAvailable && __(
								'This icon or style is unavailable in the current catalog.',
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

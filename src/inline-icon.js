import IconSelector, { getCatalog } from './icon-selector';
import {
	buildInlineIconAttributes,
	INLINE_ICON_FORMAT_NAME,
	selectionFromAttributes,
} from './inline-icon-utils.mjs';

const { __ } = wp.i18n;
const { RichTextToolbarButton } = wp.blockEditor;
const { Popover } = wp.components;
const { useSelect } = wp.data;
const { useState } = wp.element;
const { insertObject, registerFormatType, useAnchor } = wp.richText;

const SUPPORTED_BLOCKS = [ 'core/heading', 'core/paragraph' ];

// insertObject() supplies the atomic replacement internally. Setting object: true
// makes the RichText serializer omit the closing tag for non-void elements.
const formatSettings = {
	name: INLINE_ICON_FORMAT_NAME,
	title: __( 'Font Awesome icon', 'better-font-awesome' ),
	tagName: 'i',
	className: 'bfa-inline-icon',
	attributes: {
		className: 'class',
		iconName: 'data-bfa-icon-name',
		iconStyle: 'data-bfa-icon-style',
		ariaHidden: 'aria-hidden',
	},
};

function InlineIconEdit( {
	activeObjectAttributes,
	contentRef,
	isObjectActive,
	onChange,
	onFocus,
	value,
} ) {
	const [ isOpen, setIsOpen ] = useState( false );
	const selectedBlockName = useSelect( ( select ) => {
		return select( 'core/block-editor' ).getSelectedBlock()?.name;
	}, [] );
	const popoverAnchor = useAnchor( {
		editableContentElement: contentRef.current,
		settings: formatSettings,
	} );

	if ( ! SUPPORTED_BLOCKS.includes( selectedBlockName ) ) {
		return null;
	}

	const selectedValue = isObjectActive
		? selectionFromAttributes( activeObjectAttributes )
		: '';

	const onSelect = ( nextValue ) => {
		const attributes = buildInlineIconAttributes( getCatalog(), nextValue );
		if ( ! attributes ) {
			return;
		}

		onChange(
			insertObject( value, {
				type: INLINE_ICON_FORMAT_NAME,
				attributes,
			} )
		);
		setIsOpen( false );
		onFocus();
	};

	return (
		<>
			<RichTextToolbarButton
				icon="flag"
				title={ __( 'Font Awesome icon', 'better-font-awesome' ) }
				onClick={ () => setIsOpen( true ) }
				isActive={ isObjectActive }
			/>
			{ isOpen && (
				<Popover
					anchor={ popoverAnchor }
					className="bfa-inline-icon-popover"
					focusOnMount={ false }
					onClose={ () => setIsOpen( false ) }
					placement="bottom"
				>
					<IconSelector value={ selectedValue } onChange={ onSelect } />
				</Popover>
			) }
		</>
	);
}

export function registerInlineIconFormat() {
	if ( wp.data.select( 'core/rich-text' ).getFormatType( INLINE_ICON_FORMAT_NAME ) ) {
		return;
	}

	registerFormatType( INLINE_ICON_FORMAT_NAME, {
		...formatSettings,
		edit: InlineIconEdit,
	} );
}

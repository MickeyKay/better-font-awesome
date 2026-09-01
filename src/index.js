import Edit from './edit';
import { registerInlineIconFormat } from './inline-icon';
import metadata from './block.json';

wp.blocks.registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );

registerInlineIconFormat();

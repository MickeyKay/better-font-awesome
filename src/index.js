import Edit from './edit';
import metadata from './block.json';

wp.blocks.registerBlockType( metadata.name, {
	...metadata,
	edit: Edit,
	save: () => null,
} );

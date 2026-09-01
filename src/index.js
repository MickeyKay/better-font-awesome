import Edit from './edit';
import metadata from './block.json';

wp.blocks.registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );

import { build } from 'esbuild';
import { cp, mkdir, readFile, rm, writeFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';

const outputDirectory = new URL( '../build/', import.meta.url );
const sourceDirectory = new URL( '../src/', import.meta.url );

await rm( outputDirectory, { force: true, recursive: true } );
await mkdir( outputDirectory, { recursive: true } );

await build( {
	entryPoints: [ new URL( 'index.js', sourceDirectory ).pathname ],
	bundle: true,
	format: 'iife',
	jsxFactory: 'wp.element.createElement',
	jsxFragment: 'wp.element.Fragment',
	legalComments: 'none',
	loader: {
		'.js': 'jsx',
	},
	minify: true,
	outfile: new URL( 'index.js', outputDirectory ).pathname,
	platform: 'browser',
	target: [ 'es2017' ],
} );

await Promise.all( [
	cp( new URL( 'block.json', sourceDirectory ), new URL( 'block.json', outputDirectory ) ),
	cp( new URL( 'editor.css', sourceDirectory ), new URL( 'index.css', outputDirectory ) ),
	cp( new URL( 'editor.css', sourceDirectory ), new URL( 'index-rtl.css', outputDirectory ) ),
	cp( new URL( 'style.css', sourceDirectory ), new URL( 'style-index.css', outputDirectory ) ),
	cp( new URL( 'style.css', sourceDirectory ), new URL( 'style-index-rtl.css', outputDirectory ) ),
] );

const script = await readFile( new URL( 'index.js', outputDirectory ) );
const version = createHash( 'sha256' ).update( script ).digest( 'hex' ).slice( 0, 20 );
const asset = `<?php return array('dependencies' => array('wp-block-editor', 'wp-blocks', 'wp-components', 'wp-element', 'wp-i18n'), 'version' => '${ version }');\n`;
await writeFile( new URL( 'index.asset.php', outputDirectory ), asset );

<?php
/**
 * Official CSS family/style identities, independent of catalog availability.
 *
 * @package Better_Font_Awesome
 */

/** Shared appearance vocabulary for settings, catalogs and rendering. */
class Better_Font_Awesome_Appearance {
	/** Official v7 CSS prefixes. Unknown families and uploads are not inferred. */
	const DEFINITIONS = array(
		'solid'                  => array( 'classic', 'solid', 'fas' ),
		'regular'                => array( 'classic', 'regular', 'far' ),
		'brands'                 => array( 'classic', 'brands', 'fab' ),
		'light'                  => array( 'classic', 'light', 'fal' ),
		'thin'                   => array( 'classic', 'thin', 'fat' ),
		'duotone-solid'          => array( 'duotone', 'solid', 'fad' ),
		'duotone-regular'        => array( 'duotone', 'regular', 'fadr' ),
		'duotone-light'          => array( 'duotone', 'light', 'fadl' ),
		'duotone-thin'           => array( 'duotone', 'thin', 'fadt' ),
		'sharp-solid'            => array( 'sharp', 'solid', 'fass' ),
		'sharp-regular'          => array( 'sharp', 'regular', 'fasr' ),
		'sharp-light'            => array( 'sharp', 'light', 'fasl' ),
		'sharp-thin'             => array( 'sharp', 'thin', 'fast' ),
		'sharp-duotone-solid'    => array( 'sharp-duotone', 'solid', 'fasds' ),
		'sharp-duotone-regular'  => array( 'sharp-duotone', 'regular', 'fasdr' ),
		'sharp-duotone-light'    => array( 'sharp-duotone', 'light', 'fasdl' ),
		'sharp-duotone-thin'     => array( 'sharp-duotone', 'thin', 'fasdt' ),
		'chisel-regular'         => array( 'chisel', 'regular', 'facr' ),
		'etch-solid'             => array( 'etch', 'solid', 'faes' ),
		'graphite-thin'          => array( 'graphite', 'thin', 'fagt' ),
		'jelly-regular'          => array( 'jelly', 'regular', 'fajr' ),
		'jelly-fill-regular'     => array( 'jelly-fill', 'regular', 'fajfr' ),
		'jelly-duo-regular'      => array( 'jelly-duo', 'regular', 'fajdr' ),
		'mosaic-solid'           => array( 'mosaic', 'solid', 'fams' ),
		'notdog-solid'           => array( 'notdog', 'solid', 'fans' ),
		'notdog-duo-solid'       => array( 'notdog-duo', 'solid', 'fands' ),
		'pixel-regular'          => array( 'pixel', 'regular', 'fapr' ),
		'slab-regular'           => array( 'slab', 'regular', 'faslr' ),
		'slab-press-regular'     => array( 'slab-press', 'regular', 'faslpr' ),
		'slab-duo-regular'       => array( 'slab-duo', 'regular', 'fasldr' ),
		'slab-press-duo-regular' => array( 'slab-press-duo', 'regular', 'faslpdr' ),
		'thumbprint-light'       => array( 'thumbprint', 'light', 'fatl' ),
		'utility-semibold'       => array( 'utility', 'semibold', 'fausb' ),
		'utility-fill-semibold'  => array( 'utility-fill', 'semibold', 'faufsb' ),
		'utility-duo-semibold'   => array( 'utility-duo', 'semibold', 'faudsb' ),
		'vellum-solid'           => array( 'vellum', 'solid', 'favs' ),
		'whiteboard-semibold'    => array( 'whiteboard', 'semibold', 'fawsb' ),
	);

	/**
	 * Resolve the API tuple without merging similarly named styles.
	 *
	 * @param mixed $family API family.
	 * @param mixed $style API style.
	 * @return string Empty when unsupported.
	 */
	public static function identity( $family, $style ) {
		if ( ! is_string( $family ) || ! is_string( $style ) ) {
			return '';
		}
		$key = 'classic' === $family ? $style : $family . '-' . $style;
		return isset( self::DEFINITIONS[ $key ] ) && self::DEFINITIONS[ $key ][0] === $family && self::DEFINITIONS[ $key ][1] === $style ? $key : '';
	}

	/**
	 * Recognize persisted appearance keys even when their assets are unavailable.
	 *
	 * @param mixed $value Persisted value.
	 * @return bool Known appearance.
	 */
	public static function valid( $value ) {
		return is_string( $value ) && isset( self::DEFINITIONS[ $value ] );
	}

	/**
	 * Share CSS classes with PHP and editor previews.
	 *
	 * @return array<string, string> CSS classes.
	 */
	public static function classes() {
		return array_map(
			static function ( $definition ) {
				return $definition[2];
			},
			self::DEFINITIONS
		);
	}

	/**
	 * Label appearances independently of catalog availability.
	 *
	 * @return array<string, string> Combined labels.
	 */
	public static function labels() {
		$weights = array(
			'solid'    => __( 'Solid', 'better-font-awesome' ),
			'regular'  => __( 'Regular', 'better-font-awesome' ),
			'brands'   => __( 'Brands', 'better-font-awesome' ),
			'light'    => __( 'Light', 'better-font-awesome' ),
			'thin'     => __( 'Thin', 'better-font-awesome' ),
			'semibold' => __( 'Semibold', 'better-font-awesome' ),
		);
		$labels  = array();
		foreach ( self::DEFINITIONS as $key => $definition ) {
			$labels[ $key ] = 'brands' === $key ? $weights['brands'] : ucwords( str_replace( '-', ' ', $definition[0] ) ) . ' / ' . $weights[ $definition[1] ];
		}
		return $labels;
	}

	/**
	 * Adapt BFAL's existing icon output for a known non-Classic appearance.
	 * BFAL still owns names, aliases, escaping, markup and its rendering filters.
	 *
	 * @param string $html BFAL output.
	 * @param mixed  $appearance Explicit appearance.
	 * @return string Adapted output; no fallback to a different family.
	 */
	public static function apply( $html, $appearance ) {
		if ( ! self::valid( $appearance ) || 'classic' === self::DEFINITIONS[ $appearance ][0] ) {
			return $html;
		}
		$processor = new WP_HTML_Tag_Processor( $html );
		while ( $processor->next_tag() ) {
			if ( $processor->has_class( 'fa' ) ) {
				$processor->remove_class( 'fa' );
				$processor->add_class( self::DEFINITIONS[ $appearance ][2] );
				break;
			}
		}
		return $processor->get_updated_html();
	}
}

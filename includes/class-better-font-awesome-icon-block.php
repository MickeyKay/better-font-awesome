<?php
/**
 * Native Better Font Awesome icon block.
 *
 * @package Better_Font_Awesome
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register and render the native icon block.
 */
class Better_Font_Awesome_Icon_Block {

	/**
	 * Stable block name.
	 *
	 * @var string
	 */
	const NAME = 'better-font-awesome/icon';

	/**
	 * Supported Font Awesome 5 Free styles.
	 *
	 * @var string[]
	 */
	private const STYLES = array( 'brands', 'regular', 'solid' );

	/**
	 * Better Font Awesome Library instance.
	 *
	 * @var Better_Font_Awesome_Library
	 */
	private $library;

	/**
	 * Constructor.
	 *
	 * @param Better_Font_Awesome_Library $library Better Font Awesome Library instance.
	 */
	public function __construct( $library ) {
		$this->library = $library;
	}

	/**
	 * Register block hooks.
	 */
	public function boot() {
		add_action( 'init', array( $this, 'register_on_init' ), 20 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_data' ) );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_editor_font_awesome' ) );
	}

	/**
	 * Register the block from the init action.
	 */
	public function register_on_init() {
		$this->register();
	}

	/**
	 * Register the block from its generated metadata.
	 *
	 * @return WP_Block_Type|false Registered block type, or false when unavailable.
	 */
	public function register() {
		$block_path = dirname( __DIR__ ) . '/build';

		if ( ! function_exists( 'register_block_type_from_metadata' ) || ! is_readable( $block_path . '/block.json' ) ) {
			return false;
		}

		$existing = WP_Block_Type_Registry::get_instance()->get_registered( self::NAME );
		if ( $existing ) {
			return $existing;
		}

		return register_block_type_from_metadata(
			$block_path,
			array(
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Render a dynamic icon block through the established BFAL renderer.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Rendered block markup.
	 */
	public function render( $attributes ) {
		$name  = isset( $attributes['iconName'] ) && is_string( $attributes['iconName'] ) ? sanitize_key( $attributes['iconName'] ) : 'flag';
		$style = isset( $attributes['iconStyle'] ) && is_string( $attributes['iconStyle'] ) ? sanitize_key( $attributes['iconStyle'] ) : 'solid';
		$label = isset( $attributes['label'] ) && is_string( $attributes['label'] ) ? sanitize_text_field( $attributes['label'] ) : '';

		if ( '' === $name ) {
			$name = 'flag';
		}
		if ( ! in_array( $style, self::STYLES, true ) ) {
			$style = 'solid';
		}

		$accessibility = '' === $label
			? array(
				'aria-hidden' => 'true',
			)
			: array(
				'aria-label' => $label,
				'role'       => 'img',
			);

		$icon = $this->library->render_shortcode(
			array(
				'name'  => $name,
				'style' => $style,
			)
		);

		return sprintf(
			'<div %1$s>%2$s</div>',
			get_block_wrapper_attributes( $accessibility ),
			$icon
		);
	}

	/**
	 * Return safe Font Awesome Free fields for the editor selector.
	 *
	 * @return array<int, array{label: string, name: string, style: string, searchTerms: string[]}> Editor catalog.
	 */
	public function get_editor_catalog() {
		$catalog = array();

		foreach ( $this->library->get_icons() as $icon ) {
			if ( ! is_array( $icon ) || ! isset( $icon['slug'], $icon['style'], $icon['title'] ) ) {
				continue;
			}

			$name  = is_string( $icon['slug'] ) ? sanitize_key( $icon['slug'] ) : '';
			$style = is_string( $icon['style'] ) ? sanitize_key( $icon['style'] ) : '';
			$label = is_string( $icon['title'] ) ? sanitize_text_field( $icon['title'] ) : '';
			$terms = isset( $icon['searchTerms'] ) ? $icon['searchTerms'] : array();
			$terms = is_array( $terms ) ? $terms : array( $terms );
			$terms = array_values(
				array_filter(
					array_map(
						static function ( $term ) {
							return is_string( $term ) ? sanitize_text_field( $term ) : '';
						},
						$terms
					)
				)
			);
			if ( '' === $name || '' === $label || ! in_array( $style, self::STYLES, true ) ) {
				continue;
			}

			$catalog[] = array(
				'label'       => $label,
				'name'        => $name,
				'style'       => $style,
				'searchTerms' => $terms,
			);
		}

		usort(
			$catalog,
			static function ( $first, $second ) {
				return strcasecmp( $first['label'], $second['label'] );
			}
		);

		return $catalog;
	}

	/**
	 * Attach the local icon catalog only when the Block Editor loads.
	 */
	public function enqueue_editor_data() {
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( self::NAME );
		if ( ! $block_type || empty( $block_type->editor_script_handles ) ) {
			return;
		}

		$handle = reset( $block_type->editor_script_handles );
		wp_localize_script(
			$handle,
			'bfaBlockEditor',
			array(
				'icons' => $this->get_editor_catalog(),
			)
		);
		wp_set_script_translations( $handle, 'better-font-awesome', dirname( __DIR__ ) . '/languages' );
	}

	/**
	 * Load Font Awesome in the isolated block canvas without changing BFAL.
	 */
	public function enqueue_editor_font_awesome() {
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! $screen->is_block_editor() ) {
			return;
		}

		$this->library->register_font_awesome_css();
	}
}

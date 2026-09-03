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
	 * Shared block layout stylesheet handle.
	 *
	 * @var string
	 */
	private const STYLE_HANDLE = 'bfa-icon-block-style';

	/**
	 * Supported Font Awesome 5 Free styles.
	 *
	 * @var string[]
	 */
	private const STYLES = array( 'brands', 'regular', 'solid' );

	/**
	 * Supported icon positions within the block wrapper.
	 *
	 * @var string[]
	 */
	private const JUSTIFICATIONS = array( 'left', 'center', 'right' );

	/**
	 * BFAL Font Awesome 7 stylesheet handles keyed by validated asset path.
	 *
	 * @var array<string, string>
	 */
	private const EDITOR_STYLE_HANDLES = array(
		'css/all.min.css'          => 'bfa-font-awesome',
		'css/v5-font-face.min.css' => 'bfa-font-awesome-v5-compat',
		'css/v4-font-face.min.css' => 'bfa-font-awesome-v4-font-face',
		'css/v4-shims.min.css'     => 'bfa-font-awesome-v4-shim',
	);

	/**
	 * Better Font Awesome Library instance.
	 *
	 * @var object
	 */
	private $library;

	/**
	 * Exact Font Awesome 7 stylesheet URLs registered for the block canvas.
	 *
	 * @var array<string, string>
	 */
	private $editor_asset_urls = array();

	/**
	 * Constructor.
	 *
	 * @param object $library Better Font Awesome Library-compatible instance.
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
		add_filter( 'block_editor_settings_all', array( $this, 'add_editor_style_base_urls' ) );
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
		$style_path = $block_path . '/style-index.css';

		if ( ! function_exists( 'register_block_type_from_metadata' ) || ! is_readable( $block_path . '/block.json' ) || ! is_readable( $style_path ) ) {
			return false;
		}

		$existing = WP_Block_Type_Registry::get_instance()->get_registered( self::NAME );
		if ( $existing ) {
			return $existing;
		}

		if ( ! wp_style_is( self::STYLE_HANDLE, 'registered' ) ) {
			$registered = wp_register_style(
				self::STYLE_HANDLE,
				plugins_url( 'build/style-index.css', dirname( __DIR__ ) . '/better-font-awesome.php' ),
				array(),
				Better_Font_Awesome_Plugin::VERSION
			);
			if ( ! $registered ) {
				return false;
			}
			wp_style_add_data( self::STYLE_HANDLE, 'rtl', 'replace' );
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
		$name          = isset( $attributes['iconName'] ) && is_string( $attributes['iconName'] ) ? sanitize_key( $attributes['iconName'] ) : 'flag';
		$style         = isset( $attributes['iconStyle'] ) && is_string( $attributes['iconStyle'] ) ? sanitize_key( $attributes['iconStyle'] ) : 'solid';
		$justification = isset( $attributes['iconJustification'] ) && is_string( $attributes['iconJustification'] ) ? sanitize_key( $attributes['iconJustification'] ) : 'left';
		$label         = isset( $attributes['label'] ) && is_string( $attributes['label'] ) ? sanitize_text_field( $attributes['label'] ) : '';

		if ( '' === $name ) {
			$name = 'flag';
		}
		if ( ! in_array( $style, self::STYLES, true ) ) {
			$style = 'solid';
		}
		if ( ! in_array( $justification, self::JUSTIFICATIONS, true ) ) {
			$justification = 'left';
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

		$wrapper_attributes          = $accessibility;
		$wrapper_attributes['class'] = 'items-justified-' . $justification;

		return sprintf(
			'<div %1$s>%2$s</div>',
			get_block_wrapper_attributes( $wrapper_attributes ),
			$icon
		);
	}

	/**
	 * Return safe Font Awesome Free fields for the editor selector.
	 *
	 * @return array<int, array{label: string, name: string, style: string}> Editor catalog.
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
			if ( '' === $name || '' === $label || ! in_array( $style, self::STYLES, true ) ) {
				continue;
			}

			$catalog[] = array(
				'label' => $label,
				'name'  => $name,
				'style' => $style,
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
		$this->capture_editor_asset_urls();
	}

	/**
	 * Restore base URLs omitted when WordPress fetches remote editor styles.
	 *
	 * This compatibility boundary changes only exact BFAL manifest-matched CSS
	 * already present in the public block editor settings.
	 *
	 * @param array $editor_settings Block Editor settings.
	 * @return array Filtered Block Editor settings.
	 */
	public function add_editor_style_base_urls( $editor_settings ) {
		if (
			! isset( $editor_settings['styles'] ) ||
			! is_array( $editor_settings['styles'] ) ||
			empty( $this->editor_asset_urls ) ||
			'7.x' !== $this->library_release_channel()
		) {
			return $editor_settings;
		}

		$method = 'get_release_assets';
		if ( ! is_callable( array( $this->library, $method ) ) ) {
			return $editor_settings;
		}

		$assets = call_user_func( array( $this->library, $method ) );
		if ( ! is_array( $assets ) ) {
			return $editor_settings;
		}

		$verified_assets = array();
		foreach ( $assets as $asset ) {
			if (
				! is_array( $asset ) ||
				! isset( $asset['path'], $asset['value'] ) ||
				! is_string( $asset['path'] ) ||
				! isset( self::EDITOR_STYLE_HANDLES[ $asset['path'] ], $this->editor_asset_urls[ $asset['path'] ] )
			) {
				continue;
			}

			$integrity = $this->parse_integrity( $asset['value'] );
			if ( empty( $integrity ) ) {
				continue;
			}

			$verified_assets[] = array(
				'algorithm' => $integrity['algorithm'],
				'digest'    => $integrity['digest'],
				'url'       => $this->editor_asset_urls[ $asset['path'] ],
			);
		}

		foreach ( $editor_settings['styles'] as $index => $style ) {
			if (
				! is_array( $style ) ||
				! isset( $style['css'] ) ||
				! is_string( $style['css'] ) ||
				array_key_exists( 'baseURL', $style )
			) {
				continue;
			}

			foreach ( $verified_assets as $asset ) {
				$digest = hash( $asset['algorithm'], $style['css'], true );
				if ( ! hash_equals( $asset['digest'], $digest ) ) {
					continue;
				}

				$editor_settings['styles'][ $index ]['baseURL'] = $asset['url'];
				break;
			}
		}

		return $editor_settings;
	}

	/**
	 * Capture the exact URLs registered by the existing block asset callback.
	 */
	private function capture_editor_asset_urls() {
		$this->editor_asset_urls = array();
		if ( '7.x' !== $this->library_release_channel() ) {
			return;
		}

		$styles = wp_styles();
		foreach ( self::EDITOR_STYLE_HANDLES as $path => $handle ) {
			if ( ! isset( $styles->registered[ $handle ] ) || ! is_string( $styles->registered[ $handle ]->src ) ) {
				continue;
			}

			$url      = $styles->registered[ $handle ]->src;
			$url_path = wp_parse_url( $url, PHP_URL_PATH );
			$suffix   = '/' . $path;
			if ( ! is_string( $url_path ) || substr( $url_path, -strlen( $suffix ) ) !== $suffix ) {
				continue;
			}

			$this->editor_asset_urls[ $path ] = $url;
		}
	}

	/**
	 * Read the immutable channel only when the installed BFAL exposes it.
	 *
	 * @return string Selected channel, or an empty string.
	 */
	private function library_release_channel() {
		$method = 'get_release_channel';
		if ( ! is_callable( array( $this->library, $method ) ) ) {
			return '';
		}

		$channel = call_user_func( array( $this->library, $method ) );
		return is_string( $channel ) ? $channel : '';
	}

	/**
	 * Parse one canonical supported SRI value.
	 *
	 * @param mixed $value Candidate SRI value.
	 * @return array{algorithm: string, digest: string}|array{} Parsed integrity data.
	 */
	private function parse_integrity( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^(sha256|sha384|sha512)-([A-Za-z0-9+\/]+={0,2})\z/', $value, $matches ) ) {
			return array();
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decode a validated Subresource Integrity digest for byte comparison.
		$digest  = base64_decode( $matches[2], true );
		$lengths = array(
			'sha256' => 32,
			'sha384' => 48,
			'sha512' => 64,
		);
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Confirm the Subresource Integrity digest uses canonical encoding.
		if ( false === $digest || base64_encode( $digest ) !== $matches[2] || strlen( $digest ) !== $lengths[ $matches[1] ] ) {
			return array();
		}

		return array(
			'algorithm' => $matches[1],
			'digest'    => $digest,
		);
	}
}

<?php
/**
 * Better Font Awesome
 *
 * @package   Better Font Awesome
 * @author    Mickey Kay <mickeykay.me>
 * @license   GPL-2.0+
 * @link      https://wordpress.org/plugins/better-font-awesome/
 * @copyright 2017 Mickey Kay
 *
 * @wordpress-plugin
 * Plugin Name:       Better Font Awesome
 * Plugin URI:        https://github.com/MickeyKay/better-font-awesome
 * Description:       Add Font Awesome 7 Free icons with a native WordPress block, shortcodes, a Classic Editor picker, automatic icon updates, and legacy support.
 * Version:           3.2.0
 * Author:            Mickey Kay
 * Author URI:        https://mickeykay.me/
 * License:           GPLv2+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Text Domain:       better-font-awesome
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/MickeyKay/better-font-awesome
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-better-font-awesome-metadata-manager.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-better-font-awesome-pro.php';

add_action( 'init', 'bfa_start', 5 );
/**
 * Initialize the Better Font Awesome plugin.
 *
 * Start up Better Font Awesome early on the init hook, priority 5, in
 * order to load it before any other plugins that might also use the Better Font
 * Awesome Library.
 *
 * @since  0.9.5
 */
function bfa_start() {
	global $better_font_awesome;
	$better_font_awesome = Better_Font_Awesome_Plugin::get_instance();
}

/**
 * Better Font Awesome plugin class
 *
 * @since  0.9.0
 */
class Better_Font_Awesome_Plugin {

	/**
	 * Plugin slug.
	 *
	 * @since  0.9.0
	 *
	 * @var    string
	 */
	const SLUG = 'better-font-awesome';

	/**
	 * Plugin version.
	 *
	 * @since  2.0.0
	 *
	 * @var    string
	 */
	const VERSION = '3.2.0';

	/**
	 * The Better Font Awesome Library object.
	 *
	 * @since  0.1.0
	 *
	 * @var    Better_Font_Awesome_Library
	 */
	private $bfa_lib;

	/**
	 * Path to the Better Font Awesome Library main file.
	 *
	 * @since  0.1.0
	 *
	 * @var    string
	 */
	private $bfa_lib_file_path;

	/**
	 * BFA-owned metadata manager.
	 *
	 * @since 2.0.4
	 *
	 * @var Better_Font_Awesome_Metadata_Manager|null
	 */
	private $metadata_manager;

	/**
	 * Native icon block controller.
	 *
	 * @since 2.2.0
	 *
	 * @var Better_Font_Awesome_Icon_Block|null
	 */
	private $icon_block;

	/**
	 * Pro connection controller.
	 *
	 * @var Better_Font_Awesome_Pro|null
	 */
	private $pro;

	/**
	 * Plugin display name.
	 *
	 * @since  0.9.0
	 *
	 * @var    string
	 */
	private $plugin_display_name;

	/**
	 * Plugin option name.
	 *
	 * @since  0.9.0
	 *
	 * @var    string
	 */
	protected $option_name;

	/**
	 * Plugin options.
	 *
	 * @since  0.9.0
	 *
	 * @var    array
	 */
	protected $options;

	/**
	 * Default options.
	 *
	 * Used for setting uninitialized plugin options.
	 *
	 * @since  0.9.0
	 *
	 * @var    array
	 */
	protected $option_defaults = array(
		'include_v4_shim'          => '',
		'remove_existing_fa'       => '',
		'hide_admin_notices'       => '',
		'asset_delivery'           => 'automatic',
		'default_block_icon_style' => 'solid',
	);

	/**
	 * Instance of this class.
	 *
	 * @since  0.9.0
	 *
	 * @var    Better_Font_Awesome_Plugin|null
	 */
	protected static $instance = null;


	/**
	 * Returns the instance of this class, and initializes the instance if it
	 * doesn't already exist.
	 *
	 * @param array $args Args to instantiate BFA object.
	 *
	 * @return  Better_Font_Awesome_Plugin  The BFA plugin object.
	 */
	public static function get_instance( $args = array() ) {

		// If the single instance hasn't been set, set it now.
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Better Font Awesome Plugin constructor.
	 *
	 * @since  0.9.0
	 */
	protected function __construct() {

		// Perform plugin initialization actions.
		$this->initialize();

		// Stop if the Better Font Awesome Library isn't included.
		if ( ! $this->bfal_exists() ) {
			add_action( 'admin_init', array( $this, 'deactivate' ) );
			return;
		}

		// Include required files.
		$this->includes();

		// Prepare durable local metadata before BFAL resolves release data.
		if ( $this->supports_async_metadata() ) {
			$this->metadata_manager = new Better_Font_Awesome_Metadata_Manager();
		}

		// Initialize the Better Font Awesome Library.
		$this->pro = new Better_Font_Awesome_Pro();
		$this->initialize_better_font_awesome_library( $this->options );
		if ( $this->metadata_manager ) {
			$this->metadata_manager->set_library( $this->bfa_lib );
			$this->metadata_manager->boot();
		}

		// Register the native dynamic icon block without changing shortcodes.
		$this->icon_block = new Better_Font_Awesome_Icon_Block( $this->bfa_lib, $this->pro->effective() );
		$this->icon_block->boot();

		// Load the plugin text domain.
		$this->load_text_domain();

		// Set up the admin settings page.
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'add_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		// Handle saving options via AJAX.
		add_action( 'wp_ajax_bfa_save_options', array( $this, 'save_options' ) );

		if ( is_multisite() && $this->metadata_manager && self::is_network_active() ) {
			add_action( 'wp_initialize_site', array( __CLASS__, 'initialize_site_metadata' ) );
		}
	}

	/**
	 * Schedule metadata work for a new site only while BFA is network active.
	 *
	 * @param WP_Site $site New site.
	 */
	public static function initialize_site_metadata( $site ) {
		if ( ! self::is_network_active() ) {
			return;
		}

		Better_Font_Awesome_Metadata_Manager::initialize_site( $site );
	}

	/**
	 * Check the current network's canonical plugin activation state.
	 *
	 * @return bool Whether BFA is active for the current network.
	 */
	private static function is_network_active() {
		if ( ! is_multisite() ) {
			return false;
		}

		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active_for_network( plugin_basename( __FILE__ ) );
	}

	/**
	 * Do necessary initialization actions.
	 *
	 * @since  0.10.0
	 */
	private function initialize() {

		// Set display name.
		$this->plugin_display_name = __( 'Better Font Awesome', 'better-font-awesome' );

		// Set options name.
		$this->option_name = self::SLUG . '_options';

		// Set up main Better Font Awesome Library file path.
		$this->bfa_lib_file_path = plugin_dir_path( __FILE__ ) . 'vendor/mickey-kay/better-font-awesome-library/better-font-awesome-library.php';

		// Get plugin options, and populate defaults as needed.
		$this->initialize_options( $this->option_name );
	}

	/**
	 * Get class prop.
	 *
	 * @since 1.7.0
	 *
	 * @param   string $prop  Prop to fetch.
	 *
	 * @return  mixed          Value of the prop.
	 */
	public function get( $prop ) {
		return $this->$prop;
	}

	/**
	 * Check if the Better Font Awesome Library is included.
	 *
	 * @since  0.10.0
	 */
	public function bfal_exists() {
		if ( ! is_readable( $this->bfa_lib_file_path ) ) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Get BFAL instance.
	 *
	 * @since   2.0.0
	 *
	 * @return  Object  BFAL instance.
	 */
	public function get_bfa_lib_instance() {
		return $this->bfa_lib;
	}

	/**
	 * Deactivate and display an error if the BFAL isn't included.
	 *
	 * @since  0.10.0
	 */
	public function deactivate() {
		deactivate_plugins( plugin_basename( __FILE__ ) );

		$message      = '<h2>' . __( 'Better Font Awesome', 'better-font-awesome' ) . '</h2>';
			$message .= '<p>' . __( 'It appears that Better Font Awesome is missing it\'s <a href="https://github.com/MickeyKay/better-font-awesome-library" target="_blank">core library</a>, which typically occurs when cloning the Git repository and failing to run <code>composer install</code>. Please refer to the plugin\'s <a href="https://github.com/MickeyKay/better-font-awesome" target="_blank">installation instructions</a> for details on how to properly install Better Font Awesome via Git. If you installed from within WordPress, or via the wordpress.org repo, then chances are the install failed and you can try again. If the issue persists, please create a new topic on the plugin\'s <a href="http://wordpress.org/support/plugin/better-font-awesome" target="_blank">support forum</a> or file an issue on the <a href="https://github.com/MickeyKay/better-font-awesome/issues" target="_blank">Github repo</a>.', 'better-font-awesome' ) . '</p>';
			$message .= '<p><a href="' . get_admin_url( null, 'plugins.php' ) . '">' . __( 'Back to the plugins page &rarr;', 'better-font-awesome' ) . '</a></p>';

			wp_die( wp_kses_post( $message ) );
	}

	/**
	 * Include required files.
	 *
	 * @since  0.10.0
	 */
	private function includes() {

		// Better Font Awesome Library.
		require_once $this->bfa_lib_file_path;

		// Better Font Awesome native icon block.
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-better-font-awesome-icon-block.php';
	}

	/**
	 * Check whether the reviewed BFAL asynchronous metadata API is available.
	 *
	 * Keeping this compatibility check allows an emergency lockfile rollback to
	 * BFAL 2.1.0 without making the plugin fail to load.
	 *
	 * @return bool Whether BFA can own metadata orchestration.
	 */
	private function supports_async_metadata() {
		return self::dependency_supports_async_metadata();
	}

	/**
	 * Check the installed BFAL package without constructing its singleton.
	 *
	 * Activation can run after the normal init hook has passed, so it cannot
	 * assume the plugin constructor already loaded BFAL. Loading only the class
	 * file allows lifecycle scheduling to fail closed in BFAL 2.1.0 rollback
	 * mode without attempting the unsupported early-hook singleton workaround.
	 *
	 * @return bool Whether BFA can own asynchronous metadata orchestration.
	 */
	private static function dependency_supports_async_metadata() {
		if ( ! class_exists( 'Better_Font_Awesome_Library', false ) ) {
			$library_file = plugin_dir_path( __FILE__ ) . 'vendor/mickey-kay/better-font-awesome-library/better-font-awesome-library.php';
			if ( ! is_readable( $library_file ) ) {
				return false;
			}
			require_once $library_file;
		}

		if ( ! class_exists( 'Better_Font_Awesome_Release_Data_Validator' ) ) {
			return false;
		}

		return method_exists( 'Better_Font_Awesome_Library', self::metadata_refresh_method_name() );
	}

	/**
	 * Get the reviewed BFAL refresh method name.
	 *
	 * @return string BFAL method name.
	 */
	private static function metadata_refresh_method_name(): string {
		return 'refresh_release_data';
	}

	/**
	 * Get plugin options, or initialize with default values.
	 *
	 * @since   0.10.0
	 *
	 * @param string $option_name Name/slug for the plugin options object.
	 */
	private function initialize_options( $option_name ) {
		/**
		 * Get plugin options.
		 *
		 * Run maybe_unserialize() in case we're updating from the old
		 * serialized Titan Framwork option to a new, array-based options.
		 */
		$options       = maybe_unserialize( get_option( $option_name ) );
		$this->options = is_array( $options ) ? $options : array();

		// Initialize the plugin options with defaults if they're not set.
		if ( empty( $this->options ) ) {
			$this->options = $this->option_defaults;
			update_option( $option_name, $this->option_defaults );
		}

		/**
		 * Set v4 shim option to true if this is the first time the
		 * option is present, indicating an update from legacy v4
		 * support and will need shim support.
		 */
		if ( ! empty( $this->options ) && ! isset( $this->options['include_v4_shim'] ) ) {
			$this->options['include_v4_shim'] = 1;
			update_option( $option_name, $this->options );
		}
	}

	/**
	 * Initialize the Better Font Awesome Library object.
	 *
	 * @since  0.9.0
	 *
	 * @param  array $options  Plugin options.
	 */
	private function initialize_better_font_awesome_library( $options ) {

		// Hide admin notices if setting is checked.
		if ( ! empty( $options['hide_admin_notices'] ) ) {
			add_filter( 'bfa_show_errors', '__return_false' );
		}

		// Initialize BFA library.
		$args = array(
			'include_v4_shim'     => isset( $options['include_v4_shim'] ) ? $options['include_v4_shim'] : '',
			'remove_existing_fa'  => isset( $options['remove_existing_fa'] ) ? $options['remove_existing_fa'] : '',
			'asset_delivery'      => self::sanitize_asset_delivery( $options['asset_delivery'] ?? 'automatic' ),
			'load_styles'         => true,
			'load_admin_styles'   => true,
			'load_shortcode'      => true,
			'load_tinymce_plugin' => true,
		);

		$args                = $this->pro->initialization_args( $args );
		$owns_initialization = false;
		$capture_owner       = static function ( $channel ) use ( &$owns_initialization ) {
			$owns_initialization = true;
			return $channel;
		};
		add_filter( 'bfa_font_awesome_release_channel', $capture_owner, PHP_INT_MAX );

		if ( $this->metadata_manager ) {
			$release_channel                       = '';
			$capture_channel                       = static function ( $channel ) use ( &$release_channel ) {
				$release_channel = is_string( $channel ) ? $channel : '';
				return $channel;
			};
			$args['release_data_provider']         = function () use ( &$release_channel ) {
				return $this->metadata_manager->provide_release_data( $release_channel );
			};
			$args['release_data_refresh_callback'] = array( $this->metadata_manager, 'request_release_data_refresh' );
			add_filter( 'bfa_font_awesome_release_channel', $capture_channel, PHP_INT_MAX );
		}

		try {
			$this->bfa_lib = Better_Font_Awesome_Library::get_instance( $args );
		} finally {
			remove_filter( 'bfa_font_awesome_release_channel', $capture_owner, PHP_INT_MAX );
			if ( isset( $capture_channel ) ) {
				remove_filter( 'bfa_font_awesome_release_channel', $capture_channel, PHP_INT_MAX );
			}
		}
		$this->pro->boot( $this->bfa_lib, $owns_initialization );
	}

	/**
	 * Load plugin text domain.
	 *
	 * @since  0.10.0
	 */
	public function load_text_domain() {
		load_plugin_textdomain( self::SLUG, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Create the plugin settings page.
	 */
	public function add_settings_page() {
		add_options_page(
			$this->plugin_display_name,
			$this->plugin_display_name,
			'manage_options',
			self::SLUG,
			array( $this, 'create_admin_page' )
		);
	}

	/**
	 * Output the plugin settings page contents.
	 *
	 * @since  0.10.0
	 */
	public function create_admin_page() {
		?>
		<div class="wrap bfa-settings">
			<h2><?php echo esc_html( $this->plugin_display_name ); ?></h2>
			<?php $this->provider_settings(); ?>
			<?php $this->pro_settings(); ?>
			<form method="post" action="options.php" id="bfa-settings-form">
			<?php
				printf( '<input type="hidden" id="bfa-provider-input" name="%s[provider_method]" value="">', esc_attr( $this->option_name ) );
				// This prints out all hidden setting fields.
				settings_fields( self::SLUG );
				do_settings_sections( self::SLUG );
			?>
				<p>
					<button type="button" class="button button-primary bfa-save-settings-button"><?php esc_html_e( 'Save Settings', 'better-font-awesome' ); ?></button> <img class="bfa-loading-gif" src="<?php echo esc_attr( includes_url() . 'images/spinner.gif' ); ?>" />
				</p>
				<div class="bfa-ajax-response-holder"></div>
			</form>
		</div>
		<?php
	}

	/**
	 * Populate the settings page with specific settings.
	 *
	 * @since  0.10.0
	 */
	public function add_settings() {
		register_setting(
			self::SLUG, // Option group.
			$this->option_name, // Option name.
			array( $this, 'sanitize' ) // Sanitize.
		);

		add_settings_section(
			'settings_section_primary', // ID.
			'', // Title.
			'__return_null', // Callback.
			self::SLUG // Page.
		);

		add_settings_field(
			'version', // ID.
			__( 'Font Awesome version', 'better-font-awesome' ), // Title.
			array( $this, 'version_callback' ), // Callback.
			self::SLUG, // Page.
			'settings_section_primary', // Section.
			array( 'class' => 'bfa-free-setting' )
		);

		add_settings_field(
			'default_block_icon_style',
			__( 'Default block icon style', 'better-font-awesome' ),
			array( $this, 'default_block_icon_style_callback' ),
			self::SLUG,
			'settings_section_primary',
			array( 'label_for' => 'default_block_icon_style' )
		);

		add_settings_field(
			'asset_delivery',
			__( 'Serve Font Awesome locally', 'better-font-awesome' ),
			array( $this, 'asset_delivery_callback' ),
			self::SLUG,
			'settings_section_primary',
			array(
				'label_for' => 'asset_delivery',
				'class'     => 'bfa-hidden-delivery',
			)
		);

		add_settings_field(
			'include_v4_shim',
			__( 'Include v4 CSS shim', 'better-font-awesome' ),
			array( $this, 'checkbox_callback' ),
			self::SLUG,
			'settings_section_primary',
			array(
				'id'          => 'include_v4_shim',
				'class'       => 'bfa-free-setting',
				'description' => __( 'Include the Font Awesome v4 CSS shim to support legacy icons (<a href="https://fontawesome.com/how-to-use/on-the-web/setup/upgrading-from-version-4#name-changes" target="_blank">more details</a>).', 'better-font-awesome' ),
			)
		);

		add_settings_field(
			'remove_existing_fa',
			__( 'Remove existing Font Awesome', 'better-font-awesome' ),
			array( $this, 'checkbox_callback' ),
			self::SLUG,
			'settings_section_primary',
			array(
				'id'          => 'remove_existing_fa',
				'description' => __( 'Attempt to remove Font Awesome CSS and shortcodes added by other plugins and themes.', 'better-font-awesome' ),
			)
		);

		add_settings_field(
			'hide_admin_notices',
			__( 'Hide admin notices', 'better-font-awesome' ),
			array( $this, 'checkbox_callback' ),
			self::SLUG,
			'settings_section_primary',
			array(
				'id'          => 'hide_admin_notices',
				'description' => __( 'Hide the default admin warnings that are shown when API and CDN errors occur.', 'better-font-awesome' ),
			)
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @since 1.0.10
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function admin_enqueue_scripts( $hook ) {
		if ( 'settings_page_better-font-awesome' === $hook ) {
			// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			wp_enqueue_style(
				self::SLUG . '-admin',
				plugin_dir_url( __FILE__ ) . 'css/admin.css',
				array(),
				self::VERSION . '-' . md5_file( __DIR__ . '/css/admin.css' )
			);

			// Invalidate cached settings handlers when packages share a plugin version.
			// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResourceParameters.NotInFooter
			wp_enqueue_script(
				self::SLUG . '-admin',
				plugin_dir_url( __FILE__ ) . 'js/admin.js',
				array( 'jquery' ),
				self::VERSION . '-' . md5_file( __DIR__ . '/js/admin.js' )
			);

			// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NotInFooter
			wp_localize_script(
				self::SLUG . '-admin',
				'bfa_ajax_object',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
				)
			);
		}
	}

	/**
	 * Save options via AJAX.
	 *
	 * @since  1.0.10
	 */
	public function save_options() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You are not allowed to change these settings.', 'better-font-awesome' ),
				'',
				array( 'response' => 403 )
			);
		}

		if ( false === check_ajax_referer( self::SLUG . '-options', 'bfa_nonce', false ) ) {
			wp_die(
				esc_html__( 'Settings were not saved due to a missing nonce. Refresh the page and try again.', 'better-font-awesome' ),
				'',
				array( 'response' => 403 )
			);
		}

		$options = array(
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The strict style sanitizer gates new defaults against the effective catalog, including for non-scalar input.
			'default_block_icon_style' => $this->sanitize_default_setting( isset( $_POST['default_block_icon_style'] ) ? wp_unslash( $_POST['default_block_icon_style'] ) : self::get_default_block_icon_style() ),
			'asset_delivery'           => self::sanitize_asset_delivery( isset( $_POST['asset_delivery'] ) ? sanitize_key( wp_unslash( $_POST['asset_delivery'] ) ) : 'automatic' ),
			'include_v4_shim'          => isset( $_POST['include_v4_shim'] ) && (bool) absint( wp_unslash( $_POST['include_v4_shim'] ) ),
			'remove_existing_fa'       => isset( $_POST['remove_existing_fa'] ) && (bool) absint( wp_unslash( $_POST['remove_existing_fa'] ) ),
			'hide_admin_notices'       => isset( $_POST['hide_admin_notices'] ) && (bool) absint( wp_unslash( $_POST['hide_admin_notices'] ) ),
		);

		$provider = isset( $_POST['provider_method'] ) && is_string( $_POST['provider_method'] ) ? sanitize_key( wp_unslash( $_POST['provider_method'] ) ) : '';
		if ( in_array( $provider, array( 'automatic', 'bundled-local' ), true ) && Better_Font_Awesome_Pro::state() && ! Better_Font_Awesome_Pro::cancel() ) {
			wp_die( esc_html( Better_Font_Awesome_Pro::message( 'changed' ) ), '', array( 'response' => 409 ) );
		}
		if ( in_array( $provider, array( 'automatic', 'bundled-local', 'kit-css' ), true ) ) {
			$options['asset_delivery'] = self::sanitize_asset_delivery( $provider );
		}

		// Sanitize and update the options.
		update_option( $this->option_name, $options );

		// Return a message.
		esc_html_e( 'Settings saved.', 'better-font-awesome' );

		wp_die();
	}

	/**
	 * Schedule metadata work when the plugin is activated.
	 *
	 * @param bool $network_wide Whether activation is network-wide.
	 */
	public static function activate( $network_wide = false ) {
		if ( ! self::dependency_supports_async_metadata() ) {
			return;
		}

		if ( is_multisite() && $network_wide ) {
			foreach ( get_sites(
				array(
					'fields'     => 'ids',
					'number'     => 0,
					'network_id' => get_current_network_id(),
				)
			) as $site ) {
				switch_to_blog( (int) $site );
				Better_Font_Awesome_Pro::reactivate();
				restore_current_blog();
			}
		} else {
			Better_Font_Awesome_Pro::reactivate();
		}
		Better_Font_Awesome_Metadata_Manager::activate( $network_wide, self::$instance ? self::$instance->bfa_lib : null );
	}

	/**
	 * Stop pending metadata work while preserving durable data.
	 *
	 * @param bool $network_wide Whether deactivation is network-wide.
	 */
	public static function deactivate_metadata( $network_wide = false ) {
		Better_Font_Awesome_Metadata_Manager::deactivate( $network_wide );
		if ( is_multisite() && $network_wide ) {
			foreach ( get_sites(
				array(
					'fields'     => 'ids',
					'number'     => 0,
					'network_id' => get_current_network_id(),
				)
			) as $site ) {
				switch_to_blog( (int) $site );
				Better_Font_Awesome_Pro::cancel( false, true );
				restore_current_blog();
			}
		} else {
			Better_Font_Awesome_Pro::cancel( false, true );
		}
	}

	/**
	 * Output version information.
	 *
	 * @since  0.10.0
	 */
	public function version_callback() {
		if ( 'kit-css' === $this->effective_asset_delivery() && ( ! $this->pro || ! $this->pro->effective() ) ) {
			esc_html_e( 'Kit version is managed by its initializer.', 'better-font-awesome' );
			return;
		}
		$version = $this->pro && $this->pro->effective() ? $this->pro->status()['version'] : $this->bfa_lib->get_version();
		echo '<code>' . esc_html( $version ) . '</code>';
	}

	/**
	 * Version update interval callback.
	 *
	 * @since  2.0.0
	 */
	public function version_check_frequency_callback() {
		if ( 'automatic' !== $this->effective_asset_delivery() ) {
			esc_html_e( 'Background updates are disabled for the effective delivery configuration.', 'better-font-awesome' );
			return;
		}

		$current_time              = time();
		$expiration_time           = time() + $this->bfa_lib->get_transient_expiration() - 1; // -1 to improve readability (e.g. "24 hours" instead of "1 days")
		$human_readable_expiration = human_time_diff( $current_time, $expiration_time );
		/* translators: placeholder is the numeric current version number. */
		echo wp_kses_post( sprintf( __( '%s (The plugin automatically uses the latest version of Font Awesome, and checks for updates at this frequency)', 'better-font-awesome' ), "<code>{$human_readable_expiration}</code>" ) );
	}

	/**
	 * Normalize the optional delivery setting without changing legacy options.
	 *
	 * @param mixed $value Submitted or stored delivery setting.
	 * @return string Supported requested mode.
	 */
	public static function sanitize_asset_delivery( $value ) {
		return 'bundled-local' === $value ? 'bundled-local' : 'automatic';
	}

	/**
	 * Validate the site default independently of available icon styles.
	 *
	 * @param mixed $value Submitted or stored style.
	 * @return string Supported default style.
	 */
	public static function sanitize_default_block_icon_style( $value ) {
		return in_array( $value, array( 'regular', 'light', 'thin' ), true ) ? $value : 'solid';
	}

	/**
	 * Read the current site's setting without retaining another site's options.
	 *
	 * @return string Site default style.
	 */
	public static function get_default_block_icon_style() {
		$options = get_option( self::SLUG . '_options', array() );
		return self::sanitize_default_block_icon_style( is_array( $options ) ? ( $options['default_block_icon_style'] ?? 'solid' ) : 'solid' );
	}

	/**
	 * Reject new unavailable defaults, preserving an already-saved selection.
	 *
	 * @param mixed $value Submitted style.
	 * @return string Supported or retained default.
	 */
	private function sanitize_default_setting( $value ) {
		$style = self::sanitize_default_block_icon_style( $value );
		if ( in_array( $style, array( 'solid', 'regular' ), true ) || self::get_default_block_icon_style() === $style ) {
			return $style;
		}
		return $this->pro && $this->pro->effective() && in_array( $style, $this->pro->status()['styles'], true ) ? $style : 'solid';
	}

	/** Output the site default selector. */
	public function default_block_icon_style_callback() {
		$selected = self::get_default_block_icon_style();
		printf( '<select id="default_block_icon_style" name="%s[default_block_icon_style]" aria-describedby="bfa-default-style-help">', esc_attr( $this->option_name ) );
		$styles = array(
			'solid'   => __( 'Solid', 'better-font-awesome' ),
			'regular' => __( 'Regular', 'better-font-awesome' ),
		);
		if ( $this->pro && $this->pro->effective() ) {
			foreach ( array(
				'light' => __( 'Light', 'better-font-awesome' ),
				'thin'  => __( 'Thin', 'better-font-awesome' ),
			) as $style => $label ) {
				if ( in_array( $style, $this->pro->status()['styles'], true ) ) {
					$styles[ $style ] = $label;
				}
			}
		}
		if ( ! isset( $styles[ $selected ] ) ) {
			/* translators: %s: saved icon style. */
			$styles[ $selected ] = sprintf( __( '%s (saved, currently unavailable)', 'better-font-awesome' ), ucfirst( $selected ) );
		}
		foreach ( $styles as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $selected, $value, false ), esc_html( $label ) );
		}
		echo '</select><p class="description" id="bfa-default-style-help">';
		esc_html_e( 'Sets the style for icon blocks that use the site default. You can override it in each block’s settings.', 'better-font-awesome' );
		echo '</p>';
	}

	/**
	 * Read first-caller delivery ownership, including rollback dependencies.
	 *
	 * @return string Effective mode or empty for unsupported configuration.
	 */
	private function effective_asset_delivery() {
		return Better_Font_Awesome_Metadata_Manager::effective_asset_delivery( $this->bfa_lib );
	}

	/**
	 * Display requested delivery and the immutable effective configuration.
	 */
	public function asset_delivery_callback() {
		$requested = self::sanitize_asset_delivery( $this->options['asset_delivery'] ?? 'automatic' );
		$effective = $this->effective_asset_delivery();
		$messages  = array();

		if ( 'kit-css' === $effective ) {
			$messages[] = __( 'Hosted Pro is active. Selecting local delivery pauses Pro and preserves the saved connection and content. Unchecking local restores automatic Free; Refresh Kit reactivates Pro.', 'better-font-awesome' );
		} elseif ( ! in_array( $effective, array( 'automatic', 'bundled-local' ), true ) ) {
			$messages[] = __( 'This Font Awesome configuration is unsupported. Local files require Font Awesome 7 Free.', 'better-font-awesome' );
		} elseif ( $requested !== $effective ) {
			$messages[] = 'bundled-local' === $requested
				? __( 'Local delivery is not active because another plugin, theme, or filter controls Font Awesome.', 'better-font-awesome' )
				: __( 'Local delivery is active because another plugin, theme, or filter controls Font Awesome.', 'better-font-awesome' );
		}

		if ( 'bundled-local' === $effective ) {
			if ( is_wp_error( $this->bfa_lib->get_error( 'fallback' ) ) ) {
				$messages[] = __( 'Local icon files are unavailable. Reinstall Better Font Awesome.', 'better-font-awesome' );
			} else {
				$store   = new Better_Font_Awesome_Metadata_Store();
				$record  = $store->get_valid_record( '7.x' );
				$version = $this->bfa_lib->get_version();
				if ( ! empty( $record ) && version_compare( $record['release']['version'], $version, '>' ) ) {
					$messages[] = sprintf(
						/* translators: 1: local Font Awesome version, 2: previously downloaded Font Awesome version. */
						__( 'Local files use Font Awesome %1$s; your previously downloaded version is %2$s. Newer icons may be unavailable.', 'better-font-awesome' ),
						$version,
						$record['release']['version']
					);
				}
			}
		}

		printf(
			'<label for="asset_delivery"><input type="checkbox" value="bundled-local" id="asset_delivery" name="%1$s[asset_delivery]" %2$s aria-describedby="%3$s"/> <span id="bfa-delivery-help">%4$s</span></label>',
			esc_attr( $this->option_name ),
			checked( 'bundled-local', $requested, false ),
			esc_attr( empty( $messages ) ? 'bfa-delivery-help' : 'bfa-delivery-help bfa-delivery-status' ),
			esc_html__( 'Load icons from your site instead of a third-party CDN. New icons arrive through plugin updates.', 'better-font-awesome' )
		);
		if ( ! empty( $messages ) ) {
			echo '<div id="bfa-delivery-status">';
			foreach ( $messages as $message ) {
				printf( '<p>%s</p>', esc_html( $message ) );
			}
			echo '</div>';
		}
	}

	/** First settings control: presentation choice, applied only by Save or Connect. */
	public function provider_settings() {
		$state = Better_Font_Awesome_Pro::state();
		$mode  = self::sanitize_asset_delivery( $this->options['asset_delivery'] ?? 'automatic' );
		if ( 'automatic' === $mode && ! empty( $state['enabled'] ) ) {
			$mode = 'kit-css';
		}
		?>
		<table class="form-table" role="presentation"><tbody><tr>
			<th scope="row"><label for="bfa-provider"><?php esc_html_e( 'Font Awesome source', 'better-font-awesome' ); ?></label></th>
			<td><select id="bfa-provider" data-saved="<?php echo esc_attr( $mode ); ?>" aria-describedby="bfa-provider-help">
			<?php
			foreach ( array(
				'automatic'     => __( 'Automatic Free (CDN)', 'better-font-awesome' ),
				'bundled-local' => __( 'Local Free', 'better-font-awesome' ),
				'kit-css'       => __( 'Hosted Pro Kit', 'better-font-awesome' ),
			) as $value => $label ) :
				?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $mode, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
			</select>
			<p id="bfa-provider-help" class="description" role="status"><?php esc_html_e( 'Choose a source, then save settings. A Kit activates only after Connect Kit succeeds.', 'better-font-awesome' ); ?></p>
			</td>
		</tr></tbody></table>
		<?php
	}

	/** WordPress-native, separate connection controls with no secret values rendered. */
	public function pro_settings() {
		wp_enqueue_script( 'bfa-pro-settings', plugins_url( 'js/pro-settings.js', __FILE__ ), array( 'wp-i18n' ), self::VERSION . '-' . md5_file( __DIR__ . '/js/pro-settings.js' ), true );
		wp_set_script_translations( 'bfa-pro-settings', 'better-font-awesome', __DIR__ . '/languages' );
		wp_localize_script(
			'bfa-pro-settings',
			'bfaPro',
			array(
				'url'   => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'bfa-pro' ),
			)
		);
		?>
		<section id="bfa-pro-panel" hidden aria-label="<?php esc_attr_e( 'Hosted Pro Kit settings', 'better-font-awesome' ); ?>">
		<form id="bfa-pro-form" autocomplete="off">
			<table class="form-table" role="presentation"><tbody>
			<tr><th scope="row"><label for="bfa-pro-token"><?php esc_html_e( 'API Key', 'better-font-awesome' ); ?></label></th><td>
			<div id="bfa-pro-saved" hidden>
				<span class="bfa-token-saved"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e( 'API token saved', 'better-font-awesome' ); ?></span>
				<button id="bfa-pro-update-token" type="button" class="button"><?php esc_html_e( 'Update token', 'better-font-awesome' ); ?></button>
				<button type="button" class="button" data-pro-action="disconnect" aria-describedby="bfa-pro-delete-help"><?php esc_html_e( 'Delete token', 'better-font-awesome' ); ?></button>
			</div>
			<div id="bfa-pro-token-entry">
				<input id="bfa-pro-token" class="regular-text" type="password" autocomplete="new-password" spellcheck="false" aria-describedby="bfa-pro-token-help">
				<button id="bfa-pro-find" type="button" class="button button-primary"><?php esc_html_e( 'Find Kits', 'better-font-awesome' ); ?></button>
				<button id="bfa-pro-cancel-token" type="button" class="button" hidden><?php esc_html_e( 'Cancel', 'better-font-awesome' ); ?></button>
				<p id="bfa-pro-token-help"><?php esc_html_e( 'Use Read Kits Data permission. Your token stays encrypted on this server.', 'better-font-awesome' ); ?> <a href="https://fontawesome.com/account#api-tokens" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get an API token from Font Awesome (opens in a new tab)', 'better-font-awesome' ); ?></a></p>
			</div>
			<p class="bfa-discovery-status"><span id="bfa-pro-spinner" class="spinner" aria-hidden="true"></span><span id="bfa-pro-account-status" role="status" aria-live="polite" aria-atomic="true"></span></p>
			<p id="bfa-pro-delete-help" class="description" hidden><?php esc_html_e( 'Deleting the token also disconnects the Kit and removes its saved catalog. Saved icon names and styles are unchanged.', 'better-font-awesome' ); ?></p>
			</td></tr>
			<tr id="bfa-pro-kit-controls"><th scope="row"><label for="bfa-pro-kit"><?php esc_html_e( 'Kit', 'better-font-awesome' ); ?></label></th><td>
				<select id="bfa-pro-kit" aria-describedby="bfa-pro-kit-help" disabled><option value=""><?php esc_html_e( 'Choose a Kit', 'better-font-awesome' ); ?></option></select>
				<button id="bfa-pro-refresh-kits" type="button" class="button"><?php esc_html_e( 'Refresh Kits', 'better-font-awesome' ); ?></button>
				<p id="bfa-pro-kit-help" role="status" aria-live="polite" aria-atomic="true"></p>
				<p><button id="bfa-pro-connect" type="submit" class="button button-primary" disabled><?php esc_html_e( 'Connect Kit', 'better-font-awesome' ); ?></button>
				<button type="button" class="button" data-pro-action="refresh"><?php esc_html_e( 'Refresh active Kit', 'better-font-awesome' ); ?></button></p>
			<p id="bfa-pro-status" role="status" aria-live="polite" aria-atomic="true"></p>
			<p class="description"><?php esc_html_e( 'Use a v7 Pro By Style Web Fonts Kit with compatibility and Classic Solid, Regular and Brands. Light and Thin are optional. CSS and fonts load from Font Awesome, not locally.', 'better-font-awesome' ); ?></p>
			</td></tr></tbody></table>
		</form>
		</section>
		<?php
	}

	/**
	 * Output a checkbox setting.
	 *
	 * @since  0.10.0
	 *
	 * @param array $args Args to callback.
	 */
	public function checkbox_callback( $args ) {
		$option_name  = esc_attr( $this->option_name ) . '[' . $args['id'] . ']';
		$option_value = isset( $this->options[ $args['id'] ] ) ? $this->options[ $args['id'] ] : '';
		printf(
			'<label for="%s"><input type="checkbox" value="1" id="%s" name="%s" %s/> %s</label>',
			esc_attr( $args['id'] ),
			esc_attr( $args['id'] ),
			esc_attr( $option_name ),
			esc_attr( checked( 1, $option_value, false ) ),
			wp_kses_post( $args['description'] )
		);
	}

	/**
	 * Output a text setting.
	 *
	 * @since 0.10.0
	 *
	 * @param array $args Args to callback.
	 */
	public function text_callback( $args ) {
		echo '<div class="bfa-text">' . esc_html( $args['text'] ) . '</div>';
	}

	/**
	 * Sanitize each settings field as needed.
	 *
	 * @param  array $input  Contains all settings fields as array keys.
	 */
	public function sanitize( $input ) {
		$new_input = array();

		if ( isset( $input['include_v4_shim'] ) ) {
			$new_input['include_v4_shim'] = absint( $input['include_v4_shim'] );
		}

		if ( isset( $input['remove_existing_fa'] ) ) {
			$new_input['remove_existing_fa'] = absint( $input['remove_existing_fa'] );
		}

		if ( isset( $input['hide_admin_notices'] ) ) {
			$new_input['hide_admin_notices'] = absint( $input['hide_admin_notices'] );
		}

		$new_input['default_block_icon_style'] = $this->sanitize_default_setting( $input['default_block_icon_style'] ?? self::get_default_block_icon_style() );
		$new_input['asset_delivery']           = self::sanitize_asset_delivery( $input['asset_delivery'] ?? 'automatic' );
		$provider                              = $input['provider_method'] ?? '';
		if ( in_array( $provider, array( 'automatic', 'bundled-local', 'kit-css' ), true ) ) {
			if ( 'kit-css' !== $provider && Better_Font_Awesome_Pro::state() && ! Better_Font_Awesome_Pro::cancel() ) {
				add_settings_error( $this->option_name, 'provider_changed', Better_Font_Awesome_Pro::message( 'changed' ) );
				return get_option( $this->option_name, array() );
			}
			$new_input['asset_delivery'] = self::sanitize_asset_delivery( $provider );
		}

		return $new_input;
	}
}

register_activation_hook( __FILE__, array( 'Better_Font_Awesome_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Better_Font_Awesome_Plugin', 'deactivate_metadata' ) );

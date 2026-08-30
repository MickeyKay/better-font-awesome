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
 * Description:       The ultimate Font Awesome icon plugin for WordPress.
 * Version:           2.0.4
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
	const VERSION = '2.0.4';

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
		'include_v4_shim'    => '',
		'remove_existing_fa' => '',
		'hide_admin_notices' => '',
	);

	/**
	 * Instance of this class.
	 *
	 * @since  0.9.0
	 *
	 * @var    Better_Font_Awesome_Plugin
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
			$this->metadata_manager->boot();
		}

		// Initialize the Better Font Awesome Library.
		$this->initialize_better_font_awesome_library( $this->options );
		if ( $this->metadata_manager ) {
			$this->metadata_manager->set_library( $this->bfa_lib );
		}

		// Load the plugin text domain.
		$this->load_text_domain();

		// Set up the admin settings page.
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'add_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		// Handle saving options via AJAX.
		add_action( 'wp_ajax_bfa_save_options', array( $this, 'save_options' ) );
		add_action( 'wp_ajax_bfa_refresh_release_data', array( $this, 'manual_refresh' ) );

		if ( is_multisite() && $this->metadata_manager ) {
			add_action( 'wp_initialize_site', array( 'Better_Font_Awesome_Metadata_Manager', 'initialize_site' ) );
		}
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
	}

	/**
	 * Check whether the reviewed BFAL asynchronous metadata API is available.
	 *
	 * Keeping this compatibility check allows an emergency lockfile rollback to
	 * BFAL 2.0.3 without making the plugin fail to load.
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
	 * file allows lifecycle scheduling to fail closed in BFAL 2.0.3 rollback
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
			'load_styles'         => true,
			'load_admin_styles'   => true,
			'load_shortcode'      => true,
			'load_tinymce_plugin' => true,
		);

		if ( $this->metadata_manager ) {
			$args['release_data_provider']         = array( $this->metadata_manager, 'provide_release_data' );
			$args['release_data_refresh_callback'] = array( $this->metadata_manager, 'request_release_data_refresh' );
		}

		$this->bfa_lib = Better_Font_Awesome_Library::get_instance( $args );
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
			<form method="post" action="options.php" id="bfa-settings-form">
			<?php
				// This prints out all hidden setting fields.
				settings_fields( self::SLUG );
				do_settings_sections( self::SLUG );
			?>
				<p>
					<span class="button-primary bfa-save-settings-button"><?php esc_html_e( 'Save Settings', 'better-font-awesome' ); ?></span> <img class="bfa-loading-gif" src="<?php echo esc_attr( includes_url() . 'images/spinner.gif' ); ?>" />
				</p>
				<div class="bfa-ajax-response-holder"></div>
			</form>
			<?php $this->metadata_status_callback(); ?>
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
			'settings_section_primary' // Section.
		);

		add_settings_field(
			'version_check_frequency', // ID.
			__( 'Version check frequency', 'better-font-awesome' ), // Title.
			array( $this, 'version_check_frequency_callback' ), // Callback.
			self::SLUG, // Page.
			'settings_section_primary' // Section.
		);

		add_settings_field(
			'include_v4_shim',
			__( 'Include v4 CSS shim', 'better-font-awesome' ),
			array( $this, 'checkbox_callback' ),
			self::SLUG,
			'settings_section_primary',
			array(
				'id'          => 'include_v4_shim',
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
				self::VERSION
			);

			// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion, WordPress.WP.EnqueuedResourceParameters.NotInFooter
			wp_enqueue_script(
				self::SLUG . '-admin',
				plugin_dir_url( __FILE__ ) . 'js/admin.js',
				array( 'jquery' ),
				self::VERSION
			);

			// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NotInFooter
			wp_localize_script(
				self::SLUG . '-admin',
				'bfa_ajax_object',
				array(
					'ajax_url'      => admin_url( 'admin-ajax.php' ),
					'refresh_nonce' => wp_create_nonce( self::SLUG . '-refresh-release-data' ),
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
			'include_v4_shim'    => isset( $_POST['include_v4_shim'] ) && (bool) absint( wp_unslash( $_POST['include_v4_shim'] ) ),
			'remove_existing_fa' => isset( $_POST['remove_existing_fa'] ) && (bool) absint( wp_unslash( $_POST['remove_existing_fa'] ) ),
			'hide_admin_notices' => isset( $_POST['hide_admin_notices'] ) && (bool) absint( wp_unslash( $_POST['hide_admin_notices'] ) ),
		);

		// Sanitize and update the options.
		update_option( $this->option_name, $options );

		// Return a message.
		esc_html_e( 'Settings saved.', 'better-font-awesome' );

		wp_die();
	}

	/**
	 * Schedule an administrator-requested refresh without performing HTTP.
	 *
	 * The override bypasses retry timing, but it does not bypass an active
	 * worker lock. Duplicate and already-running work remains suppressed.
	 */
	public function manual_refresh() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You are not allowed to refresh Font Awesome metadata.', 'better-font-awesome' ) ),
				403
			);
		}

		if ( false === check_ajax_referer( self::SLUG . '-refresh-release-data', 'bfa_nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Metadata refresh was not scheduled due to a missing nonce. Refresh the page and try again.', 'better-font-awesome' ) ),
				403
			);
		}

		if ( ! $this->metadata_manager ) {
			wp_send_json_error(
				array( 'message' => __( 'The asynchronous metadata worker is unavailable.', 'better-font-awesome' ) ),
				503
			);
		}

		$scheduled = $this->metadata_manager->schedule_refresh( true );
		$status    = $this->metadata_manager->get_status();
		$message   = $scheduled
			? __( 'Font Awesome metadata refresh scheduled.', 'better-font-awesome' )
			: __( 'A Font Awesome metadata refresh is already scheduled or running.', 'better-font-awesome' );

		wp_send_json_success(
			array(
				'message' => $message,
				'status'  => sanitize_key( $status['status'] ),
			)
		);
	}

	/**
	 * Display sanitized metadata status and the asynchronous refresh control.
	 */
	public function metadata_status_callback() {
		if ( ! $this->metadata_manager || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$status       = $this->metadata_manager->get_status();
		$labels       = array(
			'never'      => __( 'Waiting for the first background refresh', 'better-font-awesome' ),
			'scheduled'  => __( 'Background refresh scheduled', 'better-font-awesome' ),
			'refreshing' => __( 'Background refresh in progress', 'better-font-awesome' ),
			'fresh'      => __( 'Metadata is fresh', 'better-font-awesome' ),
			'stale'      => __( 'Serving stale metadata while a refresh is scheduled', 'better-font-awesome' ),
			'failed'     => __( 'The last background refresh failed; existing metadata is still being served', 'better-font-awesome' ),
		);
		$status_key   = isset( $labels[ $status['status'] ] ) ? $status['status'] : 'never';
		$fetched_text = empty( $status['fetched_at'] )
			? __( 'Not fetched yet', 'better-font-awesome' )
			: wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $status['fetched_at'] );
		?>
		<div class="bfa-metadata-status">
			<h3><?php esc_html_e( 'Font Awesome metadata', 'better-font-awesome' ); ?></h3>
			<p><strong><?php esc_html_e( 'Status:', 'better-font-awesome' ); ?></strong> <span class="bfa-metadata-status-value"><?php echo esc_html( $labels[ $status_key ] ); ?></span></p>
			<p><strong><?php esc_html_e( 'Fetched:', 'better-font-awesome' ); ?></strong> <?php echo esc_html( $fetched_text ); ?></p>
			<?php if ( 'failed' === $status_key && ! empty( $status['last_error'] ) ) : ?>
				<p><code><?php echo esc_html( $status['last_error_code'] . ': ' . $status['last_error'] ); ?></code></p>
			<?php endif; ?>
			<p>
				<button type="button" class="button bfa-refresh-metadata-button"><?php esc_html_e( 'Refresh metadata', 'better-font-awesome' ); ?></button>
				<span class="spinner bfa-refresh-metadata-spinner"></span>
			</p>
			<p class="description"><?php esc_html_e( 'This schedules background work and does not contact Font Awesome during this browser request.', 'better-font-awesome' ); ?></p>
			<div class="bfa-refresh-response" aria-live="polite"></div>
		</div>
		<?php
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

		Better_Font_Awesome_Metadata_Manager::activate( $network_wide );
	}

	/**
	 * Stop pending metadata work while preserving durable data.
	 *
	 * @param bool $network_wide Whether deactivation is network-wide.
	 */
	public static function deactivate_metadata( $network_wide = false ) {
		Better_Font_Awesome_Metadata_Manager::deactivate( $network_wide );
	}

	/**
	 * Output version information.
	 *
	 * @since  0.10.0
	 */
	public function version_callback() {
		echo wp_kses_post( "<code>{$this->bfa_lib->get_version()}</code>" );
	}

	/**
	 * Version update interval callback.
	 *
	 * @since  2.0.0
	 */
	public function version_check_frequency_callback() {
		$current_time              = time();
		$expiration_time           = time() + $this->bfa_lib->get_transient_expiration() - 1; // -1 to improve readability (e.g. "24 hours" instead of "1 days")
		$human_readable_expiration = human_time_diff( $current_time, $expiration_time );
		/* translators: placeholder is the numeric current version number. */
		echo wp_kses_post( sprintf( __( '%s (The plugin automatically uses the latest version of Font Awesome, and checks for updates at this frequency)', 'better-font-awesome' ), "<code>{$human_readable_expiration}</code>" ) );
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

		return $new_input;
	}
}

register_activation_hook( __FILE__, array( 'Better_Font_Awesome_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Better_Font_Awesome_Plugin', 'deactivate_metadata' ) );

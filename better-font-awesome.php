<?php
/**
 * Better Font Awesome
 *
 * @package   Better Font Awesome
 * @author    Mickey Kay <mickeykay.me>
 * @license   GPL-2.0+
 * @link      http://wordpress.org/plugins/better-font-awesome
 * @copyright 2017 Mickey Kay
 *
 * @wordpress-plugin
 * Plugin Name:       Better Font Awesome
 * Plugin URI:        http://wordpress.org/plugins/better-font-awesome
 * Description:       The ultimate Font Awesome icon plugin for WordPress.
 * Version:           2.0.4
 * Author:            Mickey Kay
 * Author URI:        mickeyskay@gmail.com
 * License:           GPLv2+
 * Text Domain:       better-font-awesome
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/MickeyKay/better-font-awesome
 */

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
	 * @var    Better_Font_Awesome_Library
	 */
	private $bfa_lib_file_path;

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
	 * @var    string
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
	 * @return  Better_Font_Awesome  The BFA object.
	 */
	public static function get_instance( $args = array() ) {

		// If the single instance hasn't been set, set it now.
		// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
		if ( null == self::$instance ) {
			self::$instance = new self( $args );
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
			return false;
		}

		// Include required files.
		$this->includes();

		// Initialize the Better Font Awesome Library.
		$this->initialize_better_font_awesome_library( $this->options );

		// Load the plugin text domain.
		$this->load_text_domain();

		// Set up the admin settings page.
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'add_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		// Handle saving options via AJAX.
		add_action( 'wp_ajax_bfa_save_options', array( $this, 'save_options' ) );
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
		$this->options = maybe_unserialize( get_option( $option_name ) );

		// Initialize the plugin options with defaults if they're not set.
		if ( empty( $this->options ) ) {
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
		// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
		if ( $options && true == $options['hide_admin_notices'] ) {
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
			null, // Title.
			null, // Callback.
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
		if ( false == check_ajax_referer( self::SLUG . '-options', 'bfa_nonce', false ) ) {
			wp_die(
				__( 'Settings were not saved due to a missing nonce. Refresh the page and try again.', 'better-font-awesome' ),
				403
			);
		}

		$options = array(
			'include_v4_shim'    => isset( $_POST['include_v4_shim'] ) && $_POST['include_v4_shim'],
			'remove_existing_fa' => isset( $_POST['remove_existing_fa'] ) && $_POST['remove_existing_fa'],
			'hide_admin_notices' => isset( $_POST['hide_admin_notices'] ) && $_POST['hide_admin_notices'],
		);

		// Sanitize and update the options.
		update_option( $this->option_name, $options );

		// Return a message.
		esc_html_e( 'Settings saved.', 'better-font-awesome' );

		wp_die();
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

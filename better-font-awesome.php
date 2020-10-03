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
 * Version:           1.7.4
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
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
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
		'version'            => 'latest',
		'minified'           => 1,
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
	 * @param   array $args Args to instantiate the plugin object.
	 *
	 * @return  std_class Better_Font_Awesome  The BFA object.
	 */
	public static function get_instance( $args = array() ) {

		// If the single instance hasn't been set, set it now.
		if ( null === self::$instance ) {
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

		// Output admin notices.
		add_action( 'admin_notices', array( $this, 'do_admin_notices' ) );

		// Set up the admin settings page.
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'add_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		// Handle saving options via AJAX.
		add_action( 'wp_ajax_bfa_save_options', array( $this, 'save_options' ) );
		add_action( 'wp_ajax_bfa_dismiss_testing_admin_notice', array( $this, 'dismiss_testing_admin_notice' ) );
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
	 * Deactivate and display an error if the BFAL isn't included.
	 *
	 * @since  0.10.0
	 */
	public function deactivate() {
		deactivate_plugins( plugin_basename( __FILE__ ) );

		$message  = '<h2>' . __( 'Better Font Awesome', 'better-font-awesome' ) . '</h2>';
		$message .= '<p>' . __( 'It appears that Better Font Awesome is missing it\'s <a href="https://github.com/MickeyKay/better-font-awesome-library" target="_blank">core library</a>, which typically occurs when cloning the Git repository and failing to run <code>composer install</code>. Please refer to the plugin\'s <a href="https://github.com/MickeyKay/better-font-awesome" target="_blank">installation instructions</a> for details on how to properly install Better Font Awesome via Git. If you installed from within WordPress, or via the wordpress.org repo, then chances are the install failed and you can try again. If the issue persists, please create a new topic on the plugin\'s <a href="http://wordpress.org/support/plugin/better-font-awesome" target="_blank">support forum</a> or file an issue on the <a href="https://github.com/MickeyKay/better-font-awesome/issues" target="_blank">Github repo</a>.', 'better-font-awesome' ) . '</p>';
		$message .= '<p><a href="' . get_admin_url( null, 'plugins.php' ) . '">' . __( 'Back to the plugins page &rarr;', 'better-font-awesome' ) . '</a></p>';

		wp_die( esc_html( $message ) );
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
	 * @param string $option_name Options key.
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
		if ( true == $options['hide_admin_notices'] ) {
			add_filter( 'bfa_show_errors', '__return_false' );
		}

		// Initialize BFA library.
		$args = array(
			'version'             => isset( $options['version'] ) ? $options['version'] : $this->option_defaults['version'],
			'minified'            => isset( $options['minified'] ) ? $options['minified'] : '',
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
	 * Generate admin notices.
	 *
	 * @since  1.7.3
	 */
	public function do_admin_notices() {
		$user_dismissed_option_data = $this->get_dismissed_admin_notice_testing_data();

		if (
				! isset( $user_dismissed_option_data->{get_current_user_id()} ) ||
				true !== $user_dismissed_option_data->{get_current_user_id()}
			) :
			?>
			<div class="notice notice-info is-dismissible" id="<?php echo esc_attr( self::SLUG . '-testing-notice' ); ?>">
				<p><strong><?php esc_html_e( 'Better Font Awesome - We need your help!', 'better-font-awesome' ); ?></strong> </p>
				<p>
					<?php
						/* translators: placeholders are the opening and closing <a> tags.*/
						printf( wp_kses_post( __( "First of all, thanks so much for using the plugin! Second of all, %1\$sBetter Font Awesome 2.0%2\$s is <i>almost</i> ready for use! The new version adds a few major improvements, most notably support for Font Awesome 5 icons. Before publishing the update, it's important that we get plenty of user testing to validate that everything is working as expected, and we could really use your help.", 'better-font-awesome' ) ), '<a href="https://mickeykay.me/2020/09/better-font-awesome-v2-ready-for-testing/" target="_blank">', '</a>' );
					?>
				</p>
				<p>
					<?php
						/* translators: placeholders are the opening and closing <a> tags.*/
						printf( wp_kses_post( __( 'If you are interested in helping us test the new update, please read the official %1$sblog post%2$s, which includes simple instructions for how to get involved. Thanks so much for you support', 'better-font-awesome' ) ), '<a href="https://mickeykay.me/2020/09/better-font-awesome-v2-ready-for-testing/" target="_blank">', '</a>' );
					?>
					<span class="dashicons dashicons-heart"></span>.
				</p>
				<button type="button" class="notice-dismiss">
					<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'better-font-awesome' ); ?></span>
				</button>
			</div>
			<?php
		endif;
	}

	/**
	 * Get saved option data for dismissed admin notice.
	 *
	 * @return stdClass Dismissed admin notice data.
	 */
	private function get_dismissed_admin_notice_testing_data() {
		$dismissed_option_key = self::SLUG . '-dismissed-notice-testing';
		return get_option( $dismissed_option_key, new stdClass() );
	}

	/**
	 * Dismiss testing admin notice.
	 *
	 * @since  1.7.3
	 */
	public function dismiss_testing_admin_notice() {
		$dismissed_option_key                         = self::SLUG . '-dismissed-notice-testing';
		$dismissed_option_data                        = $this->get_dismissed_admin_notice_testing_data();
		$updated_option_data                          = $dismissed_option_data;
		$updated_option_data->{get_current_user_id()} = true;

		update_option( $dismissed_option_key, $updated_option_data );

		wp_die();
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
				settings_fields( 'option_group' );
				do_settings_sections( self::SLUG );
			?>
				<p>
					<span class="button-primary bfa-save-settings-button"><?php esc_html_e( 'Save Settings', 'better-font-awesome' ); ?></span> <img class="bfa-loading-gif" src="<?php echo esc_attr( includes_url() . 'images/spinner.gif' ); ?>" />
				</p>
				<div class="bfa-ajax-response-holder"></div>
				<?php echo wp_kses_post( $this->get_usage_text() ); ?>
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
			'option_group', // Option group.
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
			__( 'Version', 'better-font-awesome' ), // Title.
			array( $this, 'version_callback' ), // Callback.
			self::SLUG, // Page.
			'settings_section_primary', // Section.
			$this->get_versions_list() // Args.
		);

		add_settings_field(
			'minified',
			__( 'Use minified CSS', 'better-font-awesome' ),
			array( $this, 'checkbox_callback' ),
			self::SLUG,
			'settings_section_primary',
			array(
				'id'          => 'minified',
				'description' => __( 'Whether to include the minified version of the CSS (checked), or the unminified version (unchecked).', 'better-font-awesome' ),
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
	 * @param string $hook Settings page hook.
	 *
	 * @since 1.0.10
	 */
	public function admin_enqueue_scripts( $hook ) {

		// Settings-specific functionality.
		if ( 'settings_page_better-font-awesome' === $hook ) {
			// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			wp_enqueue_style(
				self::SLUG . '-admin',
				plugin_dir_url( __FILE__ ) . 'css/admin.css'
			);

			// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			wp_enqueue_script(
				self::SLUG . '-admin',
				plugin_dir_url( __FILE__ ) . 'js/admin.js',
				array( 'jquery' )
			);

			wp_localize_script(
				self::SLUG . '-admin',
				'bfa_ajax_object',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
				)
			);
		}

		// Admin notices.
		// phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
		wp_enqueue_script(
			self::SLUG . '-admin-notices',
			plugin_dir_url( __FILE__ ) . 'js/admin-notices.js',
			array( 'jquery' )
		);
	}

	/**
	 * Save options via AJAX.
	 *
	 * @since  1.0.10
	 */
	public function save_options() {
		$options = array(
			'version'            => isset( $_POST['version'] ) && $_POST['version'],
			'minified'           => isset( $_POST['minified'] ) && $_POST['minified'],
			'remove_existing_fa' => isset( $_POST['remove_existing_fa'] ) && $_POST['remove_existing_fa'],
			'hide_admin_notices' => isset( $_POST['hide_admin_notices'] ) && $_POST['hide_admin_notices'],
		);

		// Sanitize and update the options.
		update_option( $this->option_name, $options );

		// Return a message.
		echo '<div class="updated"><p>' . esc_html( 'Settings saved.', 'better-font-awesome' ) . '</p></div>';

		wp_die();
	}

	/**
	 * Get all Font Awesome versions available from the jsDelivr API.
	 *
	 * @since 0.10.0
	 *
	 * @return  array  All available versions and the latest version, or an
	 *                 empty array if the API fetch fails.
	 */
	public function get_versions_list() {
		if ( $this->bfa_lib->get_api_value( 'versions' ) ) {
			$versions['latest'] = __( 'Always Latest', 'better-font-awesome' );

			foreach ( $this->bfa_lib->get_api_value( 'versions' ) as $version ) {
				$versions[ $version ] = $version;
			}
		} else {
			$versions = array();
		}

		return $versions;
	}

	/**
	 * Output a <select> version selector.
	 *
	 * @since  0.10.0
	 *
	 * @param array $versions  All available Font Awesome versions.
	 */
	public function version_callback( $versions ) {
		if ( $versions ) {

			// Add 'Always Latest' option.
			$versions['latest'] = __( 'Always Latest', 'better-font-awesome' );

			/**
			 * Remove version 2.0, since its CSS doesn't work with the regex
			 * algorith and no one needs 2.0 anyways.
			 */
			foreach ( $versions as $index => $version ) {
				if ( '2.0' == $version ) {
					unset( $versions[ $index ] );
				}
			}

			// Output the <select> element.
			printf( '<select id="version" name="%s[version]">', esc_attr( $this->option_name ) );

			foreach ( $versions as $version => $text ) {
				printf(
					'<option value="%s" %s>%s</option>',
					esc_attr( $version ),
					selected( $version, $this->options['version'], false ),
					esc_attr( $text )
				);
			}

			echo '</select>';
		} else {
			?>
			<p>
				<?php
				printf(
					// translators: string is the error code + message.
					esc_html__( 'Version selection is currently unavailable. The attempt to reach the jsDelivr API server failed with the following error: %s', 'better-font-awesome' ),
					'<code>' . esc_html( $this->bfa_lib->get_error( 'api' )->get_error_code() . ': ' . $this->bfa_lib->get_error( 'api' )->get_error_message() ) . '</code>'
				);
				?>
			</p>
			<p>
				<?php
				printf(
					// translators: string is the fallback version of font awesome.
					esc_html__( 'Font Awesome will still render using version: %s', 'better-font-awesome' ),
					'<code>' . esc_html( $this->bfa_lib->get_fallback_version() ) . '</code>'
				);
				?>
			</p>
			<p>
				<?php
				printf(
					// translators: placeholders are the opening and closing <a> tags.
					esc_html__( 'This may be the result of a temporary server or connectivity issue which will resolve shortly. However if the problem persists please file a support ticket on the %1$splugin forum%2$s, citing the errors listed above. ', 'better-font-awesome' ),
					'<a href="http://wordpress.org/support/plugin/better-font-awesome" target="_blank" title="Better Font Awesome support forum">',
					'</a>'
				);
				?>
			</small></p>
			<?php
		}
	}

	/**
	 * Output a checkbox setting.
	 *
	 * @since  0.10.0
	 *
	 * @param array $args Args to the checkbox callback.
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
			esc_attr( $args['description'] )
		);
	}

	/**
	 * Generate the admin instructions/usage text.
	 *
	 * @since   0.10.0
	 *
	 * @return  string  Usage text.
	 */
	public function get_usage_text() {
		return '<div class="bfa-usage-text">' .
				__(
					'<h3>Usage</h3>
					 <b>Font Awesome version 4.x +</b>&nbsp;&nbsp;&nbsp;<small><a href="http://fontawesome.io/examples/">See all available options &raquo;</a></small><br /><br />
					 <i class="icon-coffee fa fa-coffee"></i> <code>[icon name="coffee"]</code> or <code>&lt;i class="fa-coffee"&gt;&lt;/i&gt;</code><br /><br />
					 <i class="icon-coffee fa fa-coffee icon-2x fa-2x"></i> <code>[icon name="coffee" class="fa-2x"]</code> or <code>&lt;i class="fa-coffee fa-2x"&gt;&lt;/i&gt;</code><br /><br />
					 <i class="icon-coffee fa fa-coffee icon-2x fa-2x icon-rotate-90 fa-rotate-90"></i> <code>[icon name="coffee" class="fa-2x fa-rotate-90"]</code> or <code>&lt;i class="fa-coffee fa-2x fa-rotate-90"&gt;&lt;/i&gt;</code><br /><br /><br />
					 <b>Font Awesome version 3.x</b>&nbsp;&nbsp;&nbsp;<small><a href="http://fontawesome.io/3.2.1/examples/">See all available options &raquo;</a></small><br /><br />
					 <i class="icon-coffee fa fa-coffee"></i> <code>[icon name="coffee"]</code> or <code>&lt;i class="icon icon-coffee"&gt;&lt;/i&gt;</code><br /><br />
					 <i class="icon-coffee fa fa-coffee icon-2x fa-2x"></i> <code>[icon name="coffee" class="icon-2x"]</code> or <code>&lt;i class="icon icon-coffee icon-2x"&gt;&lt;/i&gt;</code><br /><br />
					 <i class="icon-coffee fa fa-coffee icon-2x fa-2x icon-rotate-90 fa-rotate-90"></i> <code>[icon name="coffee" class="icon-2x icon-rotate-90"]</code> or <code>&lt;i class="icon icon-coffee icon-2x icon-rotate-90"&gt;&lt;/i&gt;</code>',
					'better-font-awesome'
				) .
				'</div>';
	}

	/**
	 * Sanitize each settings field as needed.
	 *
	 * @param  array $input  Contains all settings fields as array keys.
	 */
	public function sanitize( $input ) {
		$new_input = array();

		// Sanitize options to match their type.
		if ( isset( $input['version'] ) ) {
			$new_input['version'] = sanitize_text_field( $input['version'] );
		}

		if ( isset( $input['minified'] ) ) {
			$new_input['minified'] = absint( $input['minified'] );
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

<?php
/**
 * Better Font Awesome
 *
 * @package   Better Font Awesome
 * @author    MIGHTYminnow & Mickey Kay <mickey@mickeykaycreative.com>
 * @license   GPL-2.0+
 * @link      http://wordpress.org/plugins/better-font-awesome
 * @copyright 2014 MIGHTYminnow & Mickey Kay
 *
 * @wordpress-plugin
 * Plugin Name:       Better Font Awesome
 * Plugin URI:        http://wordpress.org/plugins/better-font-awesome
 * Description:       The ultimate Font Awesome icon plugin for WordPress.
 * Version:           1.4.3
 * Author:            MIGHTYminnow & Mickey Kay
 * Author URI:        mickey@mickeykaycreative.com
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
     * @return  Better_Font_Awesome  The BFA object.
     */
    public static function get_instance( $args = array() ) {
        
        // If the single instance hasn't been set, set it now.
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
    function __construct() {

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

        // Handle saving options via AJAX
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
        $this->bfa_lib_file_path = plugin_dir_path( __FILE__ ) . 'lib/better-font-awesome-library/better-font-awesome-library.php';

        // Get plugin options, and populate defaults as needed.
        $this->initialize_options( $this->option_name );

    }

    /**
     * Check if the Better Font Awesome Library is included.
     *
     * @since  0.10.0
     */
    private function bfal_exists() {

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

        $message = '<h2>' . __( 'Better Font Awesome', 'better-font-awesome' ) . '</h2>';
            $message .= '<p>' . __( 'It appears that Better Font Awesome is missing it\'s <a href="https://github.com/MickeyKay/better-font-awesome-library" target="_blank">core library</a>, which typically occurs when cloning the Git repository and not updating all submodules. Please refer to the plugin\'s <a href="https://github.com/MickeyKay/better-font-awesome" target="_blank">installation instructions</a> for details on how to properly install Better Font Awesome via Git. If you installed from within WordPress, or via the wordpress.org repo, then chances are the install failed and you can try again. If the issue persists, please create a new topic on the plugin\'s <a href="http://wordpress.org/support/plugin/better-font-awesome" target="_blank">support forum</a> or file an issue on the <a href="https://github.com/MickeyKay/better-font-awesome/issues" target="_blank">Github repo</a>.' , 'better-font-awesome' ) . '</p>';
            $message .= '<p><a href="' . get_admin_url( null, 'plugins.php' ) . '">' . __( 'Back to the plugins page &rarr;', 'better-font-awesome' ) . '</a></p>';

            wp_die( $message );

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
     * @return  array  Plugin options.
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
     * @param  array  $options  Plugin options.
     */
    private function initialize_better_font_awesome_library( $options ) {

        // Hide admin notices if setting is checked.
        if ( isset( $options['hide_admin_notices'] ) ) {
            add_filter( 'bfa_show_errors', '__return_false' );
        }

        // Initialize BFA library.
        $args = array(
            'version'             => isset( $options['version'] ) ? $options['version'] : $this->option_defaults['version'],
            'minified'            => isset( $options['minified'] ) ? $options['minified'] : '',
            'remove_existing_fa'  => isset( $options['remove_existing_fa'] ) ? $options['remove_existing_fa'] :'',
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
    function load_text_domain() {
        load_plugin_textdomain( self::SLUG, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    /**
     * Create the plugin settings page.
     */
    function add_settings_page() {

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
            <?php screen_icon(); ?>
            <h2><?php echo $this->plugin_display_name; ?></h2>
            <form method="post" action="options.php" id="bfa-settings-form">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'option_group' );
                do_settings_sections( self::SLUG );
            ?>
                <p>
                    <span class="button-primary bfa-save-settings-button"><?php _e( 'Save Settings', 'better-font-awesome' ); ?></span> <img class="bfa-loading-gif" src="<?php echo includes_url() . 'images/spinner.gif'; ?>" />
                </p>
                <div class="bfa-ajax-response-holder"></div>
                <?php echo $this->get_usage_text(); ?>
            </form>
        </div>
    <?php
    }

    /**
     * Populate the settings page with specific settings.
     *
     * @since  0.10.0
     */
    function add_settings() {

        register_setting(
            'option_group', // Option group
            $this->option_name, // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'settings_section_primary', // ID
            null, // Title
            null, // Callback
            self::SLUG // Page
        );

        add_settings_field(
            'version', // ID
            __( 'Version', 'better-font-awesome' ), // Title
            array( $this, 'version_callback' ), // Callback
            self::SLUG, // Page
            'settings_section_primary', // Section
            $this->get_versions_list() // Args
        );

        add_settings_field(
            'minified',
            __( 'Use minified CSS', 'better-font-awesome' ),
            array( $this, 'checkbox_callback' ),
            self::SLUG,
            'settings_section_primary',
            array(
                'id' => 'minified',
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
                'id' => 'remove_existing_fa',
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
                'id' => 'hide_admin_notices',
                'description' => __( 'Hide the default admin warnings that are shown when API and CDN errors occur.', 'better-font-awesome' ),
            )
        );

    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @since 1.0.10
     */
    public function admin_enqueue_scripts( $hook ) {

        if ( 'settings_page_better-font-awesome' === $hook ) {

            wp_enqueue_style(
                self::SLUG . '-admin',
                plugin_dir_url( __FILE__ ) . 'css/admin.css'
            );

            wp_enqueue_script(
                self::SLUG . '-admin',
                plugin_dir_url( __FILE__ ) . 'js/admin.js',
                array( 'jquery' )
            );      

            wp_localize_script(
                self::SLUG . '-admin',
                'bfa_ajax_object',
                array(
                    'ajax_url' => admin_url( 'admin-ajax.php' )
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

        $options = array(
            'version'            => $_POST['version'],
            'minified'           => $_POST['minified'],
            'remove_existing_fa' => $_POST['remove_existing_fa'],
            'hide_admin_notices' => $_POST['hide_admin_notices'],
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
    function get_versions_list() {

        if ( $this->bfa_lib->get_api_value('versions') ) {
            $versions['latest'] = __( 'Always Latest', 'better-font-awesome' );

            foreach ( $this->bfa_lib->get_api_value('versions') as $version ) {
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
     * @param array  $versions  All available Font Awesome versions
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
                printf( __( 'Version selection is currently unavailable. The attempt to reach the jsDelivr API server failed with the following error: %s', 'better-font-awesome' ),
                    '<code>' . $this->bfa_lib->get_error('api')->get_error_code() . ': ' . $this->bfa_lib->get_error('api')->get_error_message() . '</code>'
                );
                ?>
            </p>
            <p>
                <?php
                printf( __( 'Font Awesome will still render using version: %s', 'better-font-awesome' ),
                    '<code>' . $this->bfa_lib->get_fallback_version() . '</code>'
                );
                ?>
            </p>
            <p>
                <?php
                printf( __( 'This may be the result of a temporary server or connectivity issue which will resolve shortly. However if the problem persists please file a support ticket on the %splugin forum%s, citing the errors listed above. ', 'better-font-awesome' ),
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
     */
    public function checkbox_callback( $args ) {
        $option_name = esc_attr( $this->option_name ) . '[' . $args['id'] . ']';
        $option_value = isset( $this->options[ $args['id'] ] ) ? $this->options[ $args['id'] ] : '';
        printf(
            '<label for="%s"><input type="checkbox" value="1" id="%s" name="%s" %s/> %s</label>',
            $args['id'],
            $args['id'],
            $option_name,
            checked( 1, $option_value, false ),
            $args['description']
        );
    }

    /**
     * Output a text setting.
     *
     * @since 0.10.0
     */
    public function text_callback( $args ) {
        echo '<div class="bfa-text">' . $args['text'] . '</div>';
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
                __( '<h3>Usage</h3>
                     <b>Font Awesome version 4.x +</b>&nbsp;&nbsp;&nbsp;<small><a href="http://fontawesome.io/examples/">See all available options &raquo;</a></small><br /><br />
                     <i class="icon-coffee fa fa-coffee"></i> <code>[icon name="coffee"]</code> or <code>&lt;i class="fa-coffee"&gt;&lt;/i&gt;</code><br /><br />
                     <i class="icon-coffee fa fa-coffee icon-2x fa-2x"></i> <code>[icon name="coffee" class="fa-2x"]</code> or <code>&lt;i class="fa-coffee fa-2x"&gt;&lt;/i&gt;</code><br /><br />
                     <i class="icon-coffee fa fa-coffee icon-2x fa-2x icon-rotate-90 fa-rotate-90"></i> <code>[icon name="coffee" class="fa-2x fa-rotate-90"]</code> or <code>&lt;i class="fa-coffee fa-2x fa-rotate-90"&gt;&lt;/i&gt;</code><br /><br /><br />
                     <b>Font Awesome version 3.x</b>&nbsp;&nbsp;&nbsp;<small><a href="http://fontawesome.io/3.2.1/examples/">See all available options &raquo;</a></small><br /><br />
                     <i class="icon-coffee fa fa-coffee"></i> <code>[icon name="coffee"]</code> or <code>&lt;i class="icon icon-coffee"&gt;&lt;/i&gt;</code><br /><br />
                     <i class="icon-coffee fa fa-coffee icon-2x fa-2x"></i> <code>[icon name="coffee" class="icon-2x"]</code> or <code>&lt;i class="icon icon-coffee icon-2x"&gt;&lt;/i&gt;</code><br /><br />
                     <i class="icon-coffee fa fa-coffee icon-2x fa-2x icon-rotate-90 fa-rotate-90"></i> <code>[icon name="coffee" class="icon-2x icon-rotate-90"]</code> or <code>&lt;i class="icon icon-coffee icon-2x icon-rotate-90"&gt;&lt;/i&gt;</code>',
                'better-font-awesome' ) .
                '</div>';
    }

    /**
     * Sanitize each settings field as needed.
     *
     * @param  array  $input  Contains all settings fields as array keys.
     */
    public function sanitize( $input ) {

        $new_input = array();

        // Sanitize options to match their type
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

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
 * Plugin Name: Better Font Awesome
 * Plugin URI: http://wordpress.org/plugins/better-font-awesome
 * Description: The ultimate Font Awesome icon plugin for Wordpress.
 * Version: 0.9.6
 * Author: MIGHTYminnow
 * Author URI: mickey@mickeykaycreative.com
 * License:     GPLv2+
 * Text Domain: bfa
 * Domain Path: /languages
 */

// Includes
require_once plugin_dir_path( __FILE__ ) . 'lib/better-font-awesome-library/better-font-awesome-library.php';

add_action( 'plugins_loaded', 'bfa_start', 5 );
/**
 * Initialize Better Font Awesome plugin.
 *
 * Start up Better Font Awesome early on the plugins_loaded
 * hook in order to load it before any other plugins that
 * might also use the Better Font Awesome Library.
 *
 * @since 0.9.5
 */
function bfa_start() {
    global $better_font_awesome;
    $better_font_awesome = Better_Font_Awesome_Plugin::get_instance();
}

/**
 * Better Font Awesome plugin class
 */
class Better_Font_Awesome_Plugin {

    /**
     * Plugin slug.
     *
     * @since 0.9.0
     *
     * @var   string
     */
    const SLUG = 'better-font-awesome';

    /**
     * Better Font Awesome Library object.
     *
     * @since  1.0.0
     *
     * @var    Better_Font_Awesome_Library
     */
    private $bfa_lib;

    /**
     * Plugin display name.
     *
     * @since 0.9.0
     *
     * @var   string
     */
    private $plugin_display_name;

    /**
     * Plugin option nav_menu_description.
     *
     * @since 0.9.0
     *
     * @var   string
     */
    protected $option_name;

    /**
     * Plugin options.
     *
     * @since 0.9.0
     *
     * @var   string
     */
    protected $options;

    /**
     * Default options.
     *
     * Used for setting uninitialized plugin options.
     *
     * @since 0.9.0
     *
     * @var   array
     */
    protected $option_defaults = array(
        'version'            => 'latest',
        'minified'           => 1,
        'remove_existing_fa' => '',
    );

    /**
     * Instance of this class.
     *
     * @since    1.0.0
     *
     * @var      Better_Font_Awesome_Plugin
     */
    protected static $instance = null;


    /**
     * Returns the instance of this class, and initializes
     * the instance if it doesn't already exist
     *
     * @return Better_Font_Awesome The BFA object
     */
    public static function get_instance( $args = '' ) {
        static $instance = null;
        if ( null === $instance ) {
            $instance = new static( $args );
        }

        return $instance;
    }

    /**
     * Better Font Awesome Plugin constructor.
     *
     * @param  array  $args  Initialization arguments.
     */
    function __construct() {

        // Initialization actions (set up properties).
        $this->initialize();

        // Initialize Better Font Awesome Library with plugin options as args.
        $this->initialize_better_font_awesome_library( $this->options );

        // Load plugin text domain.
        add_action( 'init', array( $this, 'load_text_domain' ) );

        // Do options page
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'add_settings' ) );

    }

    /**
     * Do necessary initialization actions.
     *
     * @since  0.10.0
     */
    private function initialize() {
        
        // Set display name.
        $this->plugin_display_name = __( 'Better Font Awesome', 'bfa' );

        // Set options name.
        $this->option_name = self::SLUG . '_options';

        // Get plugin options, and populate defaults as needed.
        $this->initialize_options( $this->option_name );

    }

    /**
     * Get plugin options or initialize with default values.
     *
     * @since   0.10.0
     *
     * @return  array  Plugin options.
     */
    private function initialize_options( $option_name ) {

        /**
         * Get plugin options.
         *
         * Run maybe_unserialize() in case updating from old serialized
         * Titan Framwork option to new array-based options.
         */
        $this->options = maybe_unserialize( get_option( $option_name ) );
        
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
        
        $args = array(
            'version'             => $options['version'],
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
     * Create the plugin settings page.
     *
     * @since  0.10.0
     */
    public function create_admin_page() {
    ?>
        <div class="wrap">
            <?php screen_icon(); ?>
            <h2><?php echo $this->plugin_display_name; ?></h2>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'option_group' );
                do_settings_sections( self::SLUG );
                submit_button();
                echo $this->get_usage_text();
            ?>
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
            __( 'Version', 'bfa' ), // Title
            array( $this, 'version_callback' ), // Callback
            self::SLUG, // Page
            'settings_section_primary', // Section
            $this->get_versions_list() // Args
        );

        add_settings_field(
            'minified',
            __( 'Use minified CSS', 'bfa' ),
            array( $this, 'checkbox_callback' ),
            self::SLUG,
            'settings_section_primary',
            array(
                'id' => 'minified',
                'description' => __( 'Whether to include the minified version of the CSS (checked), or the unminified version (unchecked).', 'bfa' ),
            )
        );

        add_settings_field(
            'remove_existing_fa',
            __( 'Remove existing Font Awesome', 'bfa' ),
            array( $this, 'checkbox_callback' ),
            self::SLUG,
            'settings_section_primary',
            array(
                'id' => 'remove_existing_fa',
                'description' => __( 'Attempt to remove Font Awesome CSS and shortcodes added by other plugins and themes.', 'bfa' ),
            )
        );

    }

    /**
     * Get all Font Awesome versions available from the jsDelivr API.
     *
     * @return  array  All available versions and the latest version, or an
     *                 empty array if the API fetch fails.
     */
    function get_versions_list() {

        if ( $this->bfa_lib->get_api_value('versions') ) {
            $versions['latest'] = __( 'Always Latest', 'bfa' );

            foreach ( $this->bfa_lib->get_api_value('versions') as $version ) {
                $versions[ $version ] = $version;
            }

        } else {
            $versions = array();
        }

        return $versions;

    }

    /**
     * Get the settings option array and print one of its values
     *
     * @param array  $versions  All available Font Awesome versions
     */
    public function version_callback( $versions ) {

        if ( $versions ) {

            // Add 'Latest' option.
            $versions[ 'latest' ] = __( 'Always Latest', 'bfa' );

            // Remove version 2.0 as its CSS doesn't work with the regex algorith.
            foreach ( $versions as $index => $version ) {
                
                if ( '2.0' == $version ) {
                    unset( $versions[ $index ] );
                }

            }

            // Output select element
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
                printf( __( 'Version selection is currently unavailable. The attempt to reach the jsDelivr API server failed with the following error: %s', 'bfa' ), 
                    '<code>' . $this->bfa_lib->get_error('api')->get_error_code() . ': ' . $this->bfa_lib->get_error('api')->get_error_message() . '</code>'
                );
                ?>
            </p>
            <p>
                <?php 
                printf( __( 'Font Awesome will still render using version: %s', 'bfa' ),
                    '<code>' . $this->bfa_lib->get_active_version() . '</code>'
                );
                ?>
            </p>
            <p>
                <?php
                printf( __( 'This may be the result of a temporary server or connectivity issue which will resolve shortly. However if the problem persists please file a support ticket on the %splugin forum%s, citing the errors listed above. ', 'bfa' ),
                        '<a href="http://wordpress.org/support/plugin/better-font-awesome" target="_blank" title="Better Font Awesome support forum">',
                        '</a>'
                );
                ?>
            </small></p>
            <?php
        }

    }

    /**
     * Get the settings option array and print one of its values
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
     * Get the settings option array and print one of its values
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
                     <i class="icon-coffee fa fa-coffee"></i> <code>[icon name="coffee"]</code> or <code>&lt;i class="icon-coffee"&gt;&lt;/i&gt;</code><br /><br />
                     <i class="icon-coffee fa fa-coffee icon-2x fa-2x"></i> <code>[icon name="coffee" class="icon-2x"]</code> or <code>&lt;i class="icon-coffee icon-2x"&gt;&lt;/i&gt;</code><br /><br />
                     <i class="icon-coffee fa fa-coffee icon-2x fa-2x icon-rotate-90 fa-rotate-90"></i> <code>[icon name="coffee" class="icon-2x icon-rotate-90"]</code> or <code>&lt;i class="icon-coffee icon-2x icon-rotate-90"&gt;&lt;/i&gt;</code>',
                'bfa' ) .
                '</div>';
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
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

        return $new_input;

    }

}

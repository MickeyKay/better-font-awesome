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

/**
 * @todo ensure defaults are working for new manual options
 * @todo add a reset defaults button
 * @todo test to ensure that BFA trumps any BFA Library inclusions in plugins/themes
 * @todo add filters in all necessary locations
 * @todo check all comments for formatting and thoroughness
 * @todo test in both pre and post TinyMCE V4 (make sure icons all appear in editor and front end)
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
	$better_font_awesome = new Better_Font_Awesome_Plugin();
}

/**
 * Better Font Awesome plugin class
 */
class Better_Font_Awesome_Plugin {

	/*--------------------------------------------*
	 * Constants
	 *--------------------------------------------*/
	const name = 'Better Font Awesome';
	const slug = 'better-font-awesome';

	/*--------------------------------------------*
	 * Variables
	 *--------------------------------------------*/
	
	// Main libraries
	protected $bfa_lib;
	
	// jsDelivr CDN data
	protected $jsdelivr_data = array();
	
	// Plugin variables
	protected $plugin_display_name;
	protected $option_name;

	/**
     * Holds the values to be used in the fields callbacks
     */
    protected $options;

	/**
	 * Constructor
	 */
	function __construct() {

		// Setup plugin details
		$this->plugin_display_name = __( 'Better Font Awesome', 'bfa' );
		$this->option_name = self::slug . '_options';

		// Set Font Awesome variables (stylesheet url, prefix, etc)
		$this->setup_global_variables();

		// Hook up to the init action - priority 5 to execute before other hooked actions
		add_action( 'init', array( $this, 'init' ), 5 );

		// Do options page
		add_action( 'admin_menu', array( $this, 'add_setting_page' ) );
		add_action( 'admin_init', array( $this, 'add_settings' ) );

	}

	/**
	 * Set the Font Awesome stylesheet url to use based on the settings.
	 */
	function setup_global_variables() {

		/**
         * Get plugin options.
         *
         * Run maybe_unserialize() in case updating from old serialized 
         * Titan Framwork option to new array-based options.
         */
        $this->options = maybe_unserialize( get_option( $this->option_name ) );

		// Initialize jsDelivr Fercher class_alias()
		$jsdelivr_fetcher = new jsDeliver_Fetcher();

		// jsDelivr CDN data
		$this->jsdelivr_data['versions'] = $jsdelivr_fetcher->get_value( 'versions' );
		$this->jsdelivr_data['last_version'] = $jsdelivr_fetcher->get_value( 'lastversion' );

	}

	/**
	 * Runs when the plugin is initialized
	 */
	function init() {

		// Setup localization
		load_plugin_textdomain( self::slug, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Initialize Better Font Awesome Library with BFA options
		$this->do_better_font_awesome_library();

	}

	/**
	 * Create the plugin settings page.
	 */
	function add_setting_page() {

        add_options_page(
            $this->plugin_display_name, 
            $this->plugin_display_name, 
            'manage_options', 
            self::slug, 
            array( $this, 'create_admin_page' )
        );

	}

	/**
     * Options page callback
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
                do_settings_sections( self::slug );
                submit_button(); 
            ?>
            </form>
        </div>
        <?php

    }

	/**
	 * Populate the plugin settings page.
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
            self::slug // Page
        );

        add_settings_field(
            'version', // ID
            __( 'Version', 'bfa' ), // Title 
            array( $this, 'version_callback' ), // Callback
            self::slug, // Page
            'settings_section_primary', // Section
            $this->get_available_bfa_versions() // Args
        );      

        add_settings_field(
            'minified', 
            __( 'Use minified CSS', 'bfa' ), 
            array( $this, 'checkbox_callback' ), 
            self::slug, 
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
            self::slug, 
            'settings_section_primary',
            array(
            	'id' => 'remove_existing_fa',
            	'description' => __( 'Remove Font Awesome CSS and shortcodes added by other plugins and themes. This may help if icons are not rendering properly.', 'bfa' ),
            )
        );

	}

	/**
	 * Get all Font Awesome versions available from the jsDelivr API.
	 *
	 * @return array All available versions and the latest version
	 */
	function get_available_bfa_versions() {

		$versions['latest'] = __( 'Always Latest', 'better-font-awesome' ) . ' (' . $this->jsdelivr_data['last_version'] . ')';

		foreach ( $this->jsdelivr_data['versions'] as $version ) {
			
			// Exclude v2.0 since it is obsolete and uses a different file structure
			if ( '2' != substr( $version, 0, 1 ) ) {
				$versions[ $version ] = $version;
			}

		}

		return $versions;

	}

	/**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input ) {
        
        $new_input = array();

        if ( isset( $input['version'] ) )
            $new_input['version'] = sanitize_text_field( $input['version'] );

		if ( isset( $input['minified'] ) )
            $new_input['minified'] = absint( $input['minified'] );

        if ( isset( $input['remove_existing_fa'] ) )
            $new_input['remove_existing_fa'] = absint( $input['remove_existing_fa'] );

        return $new_input;

    }

    /** 
     * Print the Section text
     */
    public function print_section_info() {
        print 'Enter your settings below:';
    }

    /** 
     * Get the settings option array and print one of its values
     *
     * @param array $versions All available Font Awesome versions
     */
    public function version_callback( $versions ) {
    	
    	if ( $versions ) {

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

    	}

    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function checkbox_callback( $args ) {
      	$option_name = esc_attr( $this->option_name ) . '[' . $args['id'] . ']';
        printf(
            '<label for="%s"><input type="checkbox" value="1" id="%s" name="%s" %s/> %s</label>',
            $option_name,
            $args['id'],
            $option_name,
            checked( 1, $this->options[ $args['id'] ], false ),
            $args['description']
        );
    }

	function do_better_font_awesome_library() {
		$args = array(
			'version' => 'latest' == $this->options['version'] ? $this->jsdelivr_data['last_version'] : $this->options['version'],
			'minified' => $this->options['minified'],
			'remove_existing_fa' => $this->options['remove_existing_fa'],
			'load_styles' => true,
			'load_admin_styles' => true,
			'load_shortcode' => true,
			'load_tinymce_plugin' => true,
		);

		$this->bfa_lib = Better_Font_Awesome_Library::get_instance( $args );
	}
}

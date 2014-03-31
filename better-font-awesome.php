<?php
/*
 * Plugin Name: Better Font Awesome
 * Plugin URI: http://wordpress.org/plugins/better-font-awesome
 * Description: The better Font Awesome for WordPress. 
 * Version: 0.9
 * Author: Mickey Kay
 * Author URI: mickey@mickeykaycreative.com
 * License:     GPLv2+
 * Text Domain: gp
 * Domain Path: /languages
 */

/**
 * Copyright (c) 2014 Mickey Kay (email : mickey@mickeykaycreative.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**

	TODO:
	- Make sure all icons are showing
	- Fix conflict when another font awesome plugin is installed
	- Make backwards compatible for all shortcodes including "FA Icons", "FA More", and "FA Shortcodes"

**/

/*--------------------------------------------*
 * Titan Framework
 *--------------------------------------------*/
/*
 * When using the embedded framework, use it only if the framework
 * plugin isn't activated.
 */
 
// Don't do anything when we're activating a plugin to prevent errors
// on redeclaring Titan classes
if ( ! empty( $_GET['action'] ) && ! empty( $_GET['plugin'] ) ) {
    if ( $_GET['action'] == 'activate' ) {
        return;
    }
}
// Check if the framework plugin is activated
$useEmbeddedFramework = true;
$activePlugins = get_option('active_plugins');
if ( is_array( $activePlugins ) ) {
    foreach ( $activePlugins as $plugin ) {
        if ( is_string( $plugin ) ) {
            if ( stripos( $plugin, '/titan-framework.php' ) !== false ) {
                $useEmbeddedFramework = false;
                break;
            }
        }
    }
}
// Use the embedded Titan Framework
if ( $useEmbeddedFramework && ! class_exists( 'TitanFramework' ) ) {
    require_once( plugin_dir_path( __FILE__ ) . 'titan-framework/titan-framework.php' );
}

/**
 * Better Font Awesome plugin class
 */
class BetterFontAwesome {

	/*--------------------------------------------*
	 * Constants
	 *--------------------------------------------*/
	const name = 'Better Font Awesome';
	const slug = 'better-font-awesome';


	/*--------------------------------------------*
	 * variables
	 *--------------------------------------------*/
	protected $cdn_data, $titan, $version, $minified, $stylsheet;

	/**
	 * Constructor
	 */
	function __construct() {
		// Register an activation hook for the plugin
		register_activation_hook( __FILE__, array( $this, 'install' ) );

		// Setup Titan instance
		$this->titan = TitanFramework::getInstance( 'better-font-awesome' );

		// Get CDN data
		$this->setup_cdn_data();

		// Do options page
		$this->do_options_page();

		// Hook up to the init action - on 11 to make sure it loads after other FA plugins
		add_action( 'init', array( $this, 'init' ), 11 );

		// Do scripts and styles
		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts_and_styles' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts_and_styles' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'custom_admin_css' ), 99 );

	}
  
	/**
	 * Runs when the plugin is activated
	 */  
	function install() {
		// do not generate any output here
	}

	/**
	 * Runs when the plugin is initialized
	 */
	function init() {

		// Setup localization
		load_plugin_textdomain( self::slug, false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Register the shortcode [icon]
		add_shortcode( 'icon', array( $this, 'render_shortcode' ) );

		// Set Font Awesome stylesheet URL
		$this->set_stylesheet_url();

        // Add PHP variables in head for use by TinyMCY JavaScript
        foreach( array('post.php','post-new.php') as $hook ) {
        	add_action( "admin_head-$hook", array( $this, 'admin_head_variables' ) );
        	
        	if ( ( current_user_can('edit_posts') || current_user_can('edit_pages') ) &&
                get_user_option('rich_editing') ) {
	        	add_filter( 'mce_external_plugins', array( $this, 'register_tinymce_plugin' ) );
	        	add_filter( 'mce_buttons', array( $this, 'add_tinymce_buttons' ) );
	        }
        }

		// Add Font Awesome stylesheet to TinyMCE
		add_editor_style( $this->stylesheet_url );

	}

	/**
	 * Registers and enqueues stylesheets for the administration panel and the
	 * public facing site.
	 */
	function setup_cdn_data() {
		$this->cdn_data = json_decode( $this->get_data( 'http://api.jsdelivr.com/v1/bootstrap/libraries/font-awesome/' ) )[0];
	}

	/*
	 * Set the Font Awesome stylesheet url to use based on the settings
	 */
	function set_stylesheet_url() {
		$this->version = $this->titan->getOption( 'version' );
		$this->minified = $this->titan->getOption( 'minified' );

		// Get latest version if need be
		if ( 'latest' == $this->version )
			$this->version = $this->cdn_data->lastversion;

		$stylesheet = $this->minified ? '/css/font-awesome.min.css' : '/css/font-awesome.css';
		$this->stylesheet_url = '//netdna.bootstrapcdn.com/font-awesome/' . $this->version . $stylesheet;
	}

	function do_options_page() {

		// Setup available versions
		$versions[ 'latest' ] = __( 'Latest', 'better-font-awesome' ) . ' (' . $this->cdn_data->lastversion . ')';

		foreach( $this->cdn_data->versions as $version ) {
			$versions[$version] = $version;
		}

		$optionsPage = $this->titan->createAdminPanel( array(
		    'name' => __( 'Better Font Awesome', 'better-font-awesome'),
		    'parent' => 'options-general.php',
		) );

		$optionsPage->createOption( array(
		    'name' => __( 'Font Awesome version', 'better-font-awesome' ),
		    'id' => 'version',
		    'type' => 'select',
		    'desc' => __( 'Select the version of Font Awesome you would like to use. Visit the <a href="http://fontawesome.io/" target="_blank">Font Awesome website</a> for more information.', 'better-font-awesome') ,
		    'options' => $versions,
		    'default' => $this->cdn_data->lastversion,
		) );

		$optionsPage->createOption( array(
		    'name' => __( 'Use minified CSS', 'better-font-awesome' ),
		    'id' => 'minified',
		    'type' => 'checkbox',
		    'desc' => __( 'Whether to include the minified version of the CSS (checked), or the unminified version (unchecked).', 'better-font-awesome' ),
		    'default' => false,
		) );

		$optionsPage->createOption( array(
			'name' => __( 'Usage', 'better-font-awesome' ),
		    'type' => 'note',
		    'desc' => __( '
		    		<b>Version 4</b>&nbsp;&nbsp;&nbsp;<small><a href="http://fontawesome.io/examples/">See all available classes &raquo;</a></small><br /><br />
		    		<i class="icon-star fa fa-star"></i> <code>[icon name="star"]</code> or <code>&lt;i class="fa-star"&gt;&lt;/i&gt;</code><br /><br />
		    		<i class="icon-star fa fa-star icon-2x fa-2x"></i> <code>[icon name="star" class="fa-2x"]</code> or <code>&lt;i class="fa-star fa-2x"&gt;&lt;/i&gt;</code><br /><br />
		    		<i class="icon-star fa fa-star icon-2x fa-2x icon-border fa-border"></i> <code>[icon name="star" class="fa-2x fa-border"]</code> or <code>&lt;i class="fa-star fa-2x fa-border"&gt;&lt;/i&gt;</code><br /><br /><br />
		    		<b>Version 3</b>&nbsp;&nbsp;&nbsp;<small><a href="http://fontawesome.io/3.2.1/examples/">See all available classes &raquo;</a></small><br /><br />
		    		<i class="icon-star fa fa-star"></i> <code>[icon name="star"]</code> or <code>&lt;i class="icon-star"&gt;&lt;/i&gt;</code><br /><br />
		    		<i class="icon-star fa fa-star icon-2x fa-2x"></i> <code>[icon name="star" class="icon-2x"]</code> or <code>&lt;i class="icon-star icon-2x"&gt;&lt;/i&gt;</code><br /><br />
		    		<i class="icon-star fa fa-star icon-2x fa-2x icon-border fa-border"></i> <code>[icon name="star" class="icon-2x icon-border"]</code> or <code>&lt;i class="icon-star icon-2x icon-border"&gt;&lt;/i&gt;</code>

		    		', 'better-font-awesome' ),
		) );

		$optionsPage->createOption( array(
		    'type' => 'save',
		) );
	}

	function render_shortcode( $atts ) {
		extract(shortcode_atts(array(
			'name' => '',
			'class' => '',
			'title'     => '',
            'size'      => '',
            'space'     => ''
			), $atts)
		);

		// Get selected Font Awesome version
		$this->titan = TitanFramework::getInstance( 'better-font-awesome' );
		$version = $this->titan->getOption( 'version' );

		// Include for backwards compatibility with Font Awesome More Icons plugin
		$title = $title ? 'title="' . $title . '" ' : '';
		$space = 'false' == $space ? '' : '&nbsp;';
        $size = 'icon-' . $size . ' fa-' . $size;

		// Remove "icon-" and "fa-" from name
		// This helps both:
		// 	1. Incorrect shortcodes (when user includes full class name including prefix)
		// 	2. Old shortcodes from other plugins that required prefixes
		$name = str_replace( 'icon-', '', $name );
		$name = str_replace( 'fa-', '', $name );

		$icon_names = 'icon-' . $name . ' fa fa-' . $name;

		return '<i class="' . $icon_names . ' ' . $class . ' ' . $size . '" ' . $title . '>' . $space . '</i>';
	}
  
	/**
	 * Registers and enqueues stylesheets for the administration panel and the
	 * public facing site.
	 */
	function register_scripts_and_styles() {
		// Deregister any existing Font Awesome CSS (including Titan Framework)
		wp_dequeue_style( 'tf-font-awesome' );
		wp_dequeue_style( 'font-awesome' );

		// Enqueue Font Awesome CSS
		wp_register_style( 'font-awesome', $this->stylesheet_url, '', $this->version );
		wp_enqueue_style( 'font-awesome' );
	}

	/*
	 * Load admin CSS
	 */
	function custom_admin_css() {
		wp_enqueue_style( 'bfa-admin-styles', plugins_url( 'inc/css/admin-styles.css', __FILE__ ) );
	}

	function register_tinymce_plugin( $plugin_array ) {
        $plugin_array['font_awesome_icons'] = plugins_url('inc/js/tinymce-icons.js', __FILE__ );

        return $plugin_array;
    }

    function add_tinymce_buttons( $buttons ) {
        array_push($buttons, '|', 'fontAwesomeIconSelect');

        return $buttons;
    }

    /**
	 * Add PHP variables in head for use by TinyMCE JavaScript
	 */
	function admin_head_variables() {
	    
		// Get Font Awesome CSS
	    if( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == "on" )
		    $prefix = 'https:';
		else
		    $prefix = 'http:';

	    $css = $this->get_data( $prefix . $this->stylesheet_url );
	 
	 	// Get all CSS selectors that have a content: pseudo-element rule
	 	preg_match_all('/(\.[^}]*)\s*{\s*(content:)/s', $css, $matches );
	    $selectors = $matches[1];

	    // Select all icon- and fa- selectors from and split where there are commas
	    foreach ( $selectors as $selector ) {
	    	preg_match_all('/\.(icon-|fa-)([^,]*)\s*:before/s', $selector, $matches );
	    	$clean_selectors = $matches[2];

	    	// Create array of selectors
	   		foreach( $clean_selectors as $clean_selector )
	   			$classes[] = $clean_selector;
	    }

	    // Alphabetize & join with comma for use in JS array
		sort( $classes );
		$classes = implode( ",", $classes );
	    ?>
		<!-- Pass $classes variable so it is accessible to TinyMCE JavaScript -->
		<script type='text/javascript'>
		var bfa_vars = {
		    'fa_icons': '<?php echo $classes; ?>',
		};
		</script>
		<!-- TinyMCE Shortcode Plugin -->
	    <?php
	}

	/**
	 * Get contents of URL
	 *
	 * @param   string $url URL to get content
	 * @return  mixed Contents of URL
	 */
	function get_data( $url ) {
		$ch = curl_init();
		$timeout = 5;
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		$data = curl_exec($ch);
		curl_close($ch);
		return $data;
	}
  
}
$better_font_awesome = new BetterFontAwesome();
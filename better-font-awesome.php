<?php
/*
Plugin Name: Better Font Awesome
Plugin URI: http://wordpress.org/plugins/better-font-awesome
Description: The better Font Awesome for WordPress. 
Version: 0.9
Author: Mickey Kay
Author Email: mickey@mickeykaycreative.com
License:

  Copyright 2011 Mickey Kay (mickey@mickeykaycreative.com)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as 
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
  
*/

/*--------------------------------------------*
 * Titan Framework
 *--------------------------------------------*/
 
// Don't do anything when we're activating a plugin to prevent errors
// on redeclaring Titan classes
if ( ! empty( $_GET['action'] ) && ! empty( $_GET['plugin'] ) ) {
    if ( $_GET['action'] == 'activate' ) {
        return;
    }
}

// Use the embedded Titan Framework
if ( ! class_exists( 'TitanFramework' ) ) {
    require_once( plugin_dir_path( __FILE__ ) . 'titan-framework/titan-framework.php' );
}

class BetterFontAwesome {

	/*--------------------------------------------*
	 * Constants
	 *--------------------------------------------*/
	const name = 'Better Font Awesome';
	const slug = 'better-font-awesome';

	/*--------------------------------------------*
	 * Private variables
	 *--------------------------------------------*/
	private $cdn_data;
	
	/**
	 * Constructor
	 */
	function __construct() {
		//register an activation hook for the plugin
		register_activation_hook( __FILE__, array( &$this, 'install' ) );

		//Hook up to the init action
		add_action( 'init', array( &$this, 'init' ) );
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
		load_plugin_textdomain( self::slug, false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
		// Load JavaScript and stylesheets
		$this->register_scripts_and_styles();

		// Register the shortcode [icon]
		add_shortcode( 'icon', array( &$this, 'render_shortcode' ) );

		// Get CDN data
		$this->setup_cdn_data();
	
		// Do options page
		$this->do_options_page();
	}

	function action_callback_method_name() {
		// TODO define your action method here
	}

	function filter_callback_method_name() {
		// TODO define your filter method here
	}

	function render_shortcode( $atts ) {
		extract(shortcode_atts(array(
			'name' => '',
			'classes' => ''
			), $atts)
		);

		return '<i class="fa fa-' . sanitize_html_class( $name ) . ' ' . sanitize_html_class( $classes ) . '"></i>';
	}

	/**
	 * Registers and enqueues stylesheets for the administration panel and the
	 * public facing site.
	 */
	private function setup_cdn_data() {
		$this->cdn_data = json_decode( $this->get_data( 'http://api.jsdelivr.com/v1/bootstrap/libraries/font-awesome/' ) )[0];
		
	} // end setup_cdn_data

	private function do_options_page() {
		$titan = TitanFramework::getInstance( 'better-font-awesome' );

		foreach( $this->cdn_data->versions as $version ) {
			$verions[$version] = $version;
		}

		$optionsPage = $titan->createAdminPanel( array(
		    'name' => __( 'Better Font Awesome', 'better-font-awesome'),
		    'parent' => 'options-general.php',
		) );

		$optionsPage->createOption( array(
		    'name' => __( 'Version', 'better-font-awesome' ),
		    'id' => 'version',
		    'type' => 'select',
		    'desc' => __( 'Select the version of Font Awesome you would like to use. Please note that different versions use different icon names, and switching versions. . .', 'better-font-awesome') ,
		    'options' => $this->cdn_data->versions,
		) );

		$optionsPage->createOption( array(
		    'name' => __( 'Minified', 'better-font-awesome' ),
		    'id' => 'minified',
		    'type' => 'checkbox',
		    'desc' => 'Whether to include the minified version of the CSS (checked), or the unminified version (unchecked).',
		    'default' => false,
		) );

		$optionsPage->createOption( array(
		    'type' => 'save',
		) );
	}
  
	/**
	 * Registers and enqueues stylesheets for the administration panel and the
	 * public facing site.
	 */
	private function register_scripts_and_styles() {
		$titan = TitanFramework::getInstance( 'better-font-awesome' );

		$version = $titan->getOption( 'version' );
		$minified = $titan->getOption( 'minified' );
		$version='4.0.3';

		$stylesheet = $minified ? '/css/font-awesome.min.css' : '/css/font-awesome.css';

		// Enqueue Font Awesome CSS
		wp_register_style( 'font-awesome', '//netdna.bootstrapcdn.com/font-awesome/' . $version . $stylesheet, '', $version );
		wp_enqueue_style( 'font-awesome' );
		
	} // end register_scripts_and_styles
	
	/**
	 * Helper function for registering and enqueueing scripts and styles.
	 *
	 * @name	The 	ID to register with WordPress
	 * @file_path		The path to the actual file
	 * @is_script		Optional argument for if the incoming file_path is a JavaScript source file.
	 */
	private function load_file( $name, $file_path, $is_script = false ) {

		$url = plugins_url($file_path, __FILE__);
		$file = plugin_dir_path(__FILE__) . $file_path;

		if( file_exists( $file ) ) {
			if( $is_script ) {
				wp_register_script( $name, $url, array('jquery') ); //depends on jquery
				wp_enqueue_script( $name );
			} else {
				wp_register_style( $name, $url );
				wp_enqueue_style( $name );
			} // end if
		} // end if

	} // end load_file

	/**
	 * Get contents of URL
	 *
	 * @param   string $url URL to get content
	 * @return  mixed Contents of URL
	 */
	private function get_data( $url ) {
		$ch = curl_init();
		$timeout = 5;
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		$data = curl_exec($ch);
		curl_close($ch);
		return $data;
	}
  
} // end class
new BetterFontAwesome();
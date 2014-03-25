<?php 

/**
 * Creates admin settings page
 *
 * @package jQuery Responsive Select Menu
 * @since   1.0
 */
function jrsm_do_settings_page() {

	// Create admin menu item
	add_options_page( PLUGIN_NAME, 'jQuery Responsive Select Menu', 'manage_options', 'jquery-responsive-select-menu', 'jrsm_output_settings');

}
add_action('admin_menu', 'jrsm_do_settings_page');

/**
 * Outputs settings page with form
 *
 * @package jQuery Responsive Select Menu
 * @since   1.0
 */
function jrsm_output_settings() { ?>
	<div class="wrap">
		<?php screen_icon(); ?>
		<h2><?php echo PLUGIN_NAME; ?></h2>
		<form method="post" action="options.php">
		    <?php settings_fields( 'jquery-responsive-select-menu' ); ?>
		    <?php do_settings_sections( 'jquery-responsive-select-menu' ); ?>
			<?php submit_button(); ?>
		</form>
	</div>
<?php }

/**
 * Registers plugin settings
 *
 * @package jQuery Responsive Select Menu
 * @since   1.0
 */
function jrsm_register_settings() {

	register_setting( 'jrsm-settings-group', 'jrsm-settings-group', 'jrsm-settings-validate' );
	
	// Setting sections
	add_settings_section(
		'jrsm-settings-section',
		'Main Settings',
		'',
		'jquery-responsive-select-menu'
	);

	/* Define settings fields */

	// Menu Containers
	$fields[] = array (
		'id' => 'jrsm-containers',
		'title' => __( 'Menu Container(s) Class / ID', 'jrsm' ),
		'callback' => 'jrsm_output_fields',
		'section' => 'jquery-responsive-select-menu',
		'page' => 'jrsm-settings-section',
		'args' => array( 
			'type' => 'text',
			'validation' => 'wp_kses_post',
			'description' => __( 'Comma separated list of selectors for the parent div containing each menu &lt;ul&gt;.<br />Example: #nav, .mini-nav', 'jrsm' ),
		)
	);

	// Maximum width
	$fields[] = array (
		'id' => 'jrsm-width',
		'title' => __( 'Maximum Menu Width', 'jrsm' ),
		'callback' => 'jrsm_output_fields',
		'section' => 'jquery-responsive-select-menu',
		'page' => 'jrsm-settings-section',
		'args' => array( 
			'type' => 'text',
			'validation' => 'intval',
			'after_text' => 'px',
			'description' => __( 'The width at which the responsive select menu should appear/disappear.', 'jrsm' ),
		)
	);

	// Sub-item spacer
	$fields[] = array (
		'id' => 'jrsm-sub-item-spacer',
		'title' => __( 'Sub Item Spacer', 'jrsm' ),
		'callback' => 'jrsm_output_fields',
		'section' => 'jquery-responsive-select-menu',
		'page' => 'jrsm-settings-section',
		'args' => array(
			'type' => 'text',
			'validation' => 'wp_kses_post',
			'description' => __( 'The character(s) used to indent sub items.', 'jrsm' ),
		)
	);

	// First term name
	$fields[] = array (
		'id' => 'jrsm-first-term',
		'title' => __( 'First Term', 'jrsm' ),
		'callback' => 'jrsm_output_fields',
		'section' => 'jquery-responsive-select-menu',
		'page' => 'jrsm-settings-section',
		'args' => array(
			'type' => 'text',
			'validation' => 'wp_kses_post',
			'description' => __( 'The text for the select menu\'s top-level "dummy" item.<br />Example: ⇒ Navigation', 'jrsm' ),
		)
	);

	// Show current page
	$fields[] = array (
		'id' => 'jrsm-show-current-page',
		'title' => __( 'Show Current Page', 'jrsm' ),
		'callback' => 'jrsm_output_fields',
		'section' => 'jquery-responsive-select-menu',
		'page' => 'jrsm-settings-section',
		'args' => array(
			'type' => 'checkbox',
			'after_text' => __( 'Show the currently selected page instead of the top level "dummy" item.', 'jrsm' ),
		)
	);

	// Add settings fields
	foreach( $fields as $field ) {
		jrsm_register_settings_field( $field['id'], $field['title'], $field['callback'], $field['section'], $field['page'], $field );	
	}

	// Register settings
	register_setting('jquery-responsive-select-menu','jrsm-output-method');

}
add_action( 'admin_init', 'jrsm_register_settings' );

/**
 * Adds and registers settings field
 *
 * @package jQuery Responsive Select Menu
 * @since   1.0		
 */	
function jrsm_register_settings_field( $id, $title, $callback, $section, $page, $field ) {

	// Add settings field	
	add_settings_field( $id, $title, $callback, $section, $page, $field );

	// Register setting with appropriate validation
	$validation = !empty( $field['args']['validation'] ) ? $field['args']['validation'] : '';
	register_setting( $section, $id, $validation );

}

function jrsm_output_fields( $field ) {
	
	/* Set default values if setting is empty */

	// Get setting
	$value = get_option( $field['id'] );
	
	// Set defaults if empty
	if ( empty( $value ) ) {

		switch( $field['id'] ) {

			// Examples
			
			/*
			case 'jrsm-first-term-name':
				update_option( 'jrsm-first-term-name', '⇒ Navigation' );
				break;

			case 'jrsm-sub-item-spacer':
				update_option( 'jrsm-sub-item-spacer', '-' );
				break;
			*/
		
		}

	}
	
	/* Output admin form elements for each settings field */
	
	// Get necessary input args
	$type = $field['args']['type'];
	$placeholder = !empty( $field['args']['placeholder'] ) ? ' placeholder="' . $field['args']['placeholder'] . '" ' : '';

	// Output form elements
	switch( $type ) {

		// Text fields
		case 'text':
			echo '<input name="' . $field['id'] . '" id="' . $field['id'] . '" type="' . $type . '" value="' . $value . '"' . $placeholder . '" />';
			break;

		// Checkbox
		case 'checkbox':
			echo '<input name="' . $field['id'] . '" id="' . $field['id'] . '" type="' . $type . '" value="1"' . $placeholder . checked( get_option( $field['id'] ), 1, false ) . '" />';
			break;

	}
	
	// After text
	if ( !empty( $field['args']['after_text'] ) )
		echo ' <em>' . $field['args']['after_text'] . '</em>';

	// Description
	if ( !empty( $field['args']['description'] ) )
		echo '<br /><em>' . $field['args']['description'] . "</em>\n";
}
<?php
/**
 * Plugin Name: Better Font Awesome editor acceptance fixture
 * Description: Test-only Classic Editor and hybrid wp_editor() surfaces.
 */

add_action(
	'init',
	static function () {
		register_post_type(
			'bfa_classic_test',
			array(
				'labels'       => array(
					'name'          => 'BFA Classic fixtures',
					'singular_name' => 'BFA Classic fixture',
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => false,
				'supports'     => array( 'title', 'editor' ),
			)
		);

		register_post_type(
			'bfa_iframe_test',
			array(
				'labels'       => array(
					'name'          => 'BFA iframe fixtures',
					'singular_name' => 'BFA iframe fixture',
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor' ),
			)
		);
	}
);

add_filter(
	'use_block_editor_for_post_type',
	static function ( $use_block_editor, $post_type ) {
		return 'bfa_classic_test' === $post_type ? false : $use_block_editor;
	},
	10,
	2
);

add_action(
	'add_meta_boxes_post',
	static function () {
		add_meta_box(
			'bfa-hybrid-editor-fixture',
			'BFA hybrid editor fixture',
			static function () {
				wp_editor(
					'',
					'bfa_hybrid_editor',
					array(
						'media_buttons' => true,
						'textarea_rows' => 6,
					)
				);
			},
			'post',
			'normal',
			'high'
		);
	}
);

// Keep browser fixtures deterministic when automatic-mode cron becomes due.
add_filter(
	'pre_http_request',
	static function ( $preempt, $args, $url ) {
		if ( preg_match( '#^https://(?:api\.fontawesome\.com|registry\.npmjs\.org|cdnjs\.cloudflare\.com|cdn\.jsdelivr\.net|use\.fontawesome\.com)(?:/|$)#', $url ) ) {
			return new WP_Error( 'bfa_e2e_offline', 'Font Awesome remote transport is disabled by the browser test fixture.' );
		}
		return $preempt;
	},
	5,
	3
);

// Opt-in synthetic Pro API for this isolated test plugin. Never ships in BFA.
add_filter( 'pre_http_request', static function ( $preempt, $args, $url ) {
	if ( 'local' !== wp_get_environment_type() || ! get_option( 'bfa_test_pro_enabled' ) ) { return $preempt; }
	require_once dirname( ( new ReflectionClass( Better_Font_Awesome_Plugin::class ) )->getFileName() ) . '/tests/pro-fixture.php';
	$fixture = new Better_Font_Awesome_Pro_Fixture();
	$fixture->fault = get_option( 'bfa_test_pro_fault', '' );
	$fixture->revision = get_option( 'bfa_test_pro_revision', 'synthetic-r1' );
	if ( get_option( 'bfa_test_pro_large' ) ) { $fixture->expand(); }
	return $fixture->response( $preempt, $args, $url );
}, 20, 3 );

add_action( 'admin_menu', static function () {
	add_management_page( 'BFA synthetic Pro service', 'BFA synthetic Pro service', 'manage_options', 'bfa-pro-fixture', static function () {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		if ( isset( $_POST['bfa_fixture_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bfa_fixture_nonce'] ) ), 'bfa-fixture' ) ) {
			update_option( 'bfa_test_pro_enabled', isset( $_POST['enabled'] ) );
			update_option( 'bfa_test_pro_large', isset( $_POST['large'] ) );
			update_option( 'bfa_test_pro_fault', isset( $_POST['fault'] ) ? sanitize_key( wp_unslash( $_POST['fault'] ) ) : '' );
		}
		echo '<div class="wrap"><h1>BFA synthetic Pro service</h1><p>No real account, Kit or Pro assets. Only use on this isolated QA site.</p><form method="post">';
		wp_nonce_field( 'bfa-fixture', 'bfa_fixture_nonce' );
		echo '<p><label><input type="checkbox" name="enabled" ' . checked( get_option( 'bfa_test_pro_enabled' ), true, false ) . '>Enable synthetic API</label></p>';
		echo '<p><label><input type="checkbox" name="large" ' . checked( get_option( 'bfa_test_pro_large' ), true, false ) . '>Full-sized synthetic catalog</label></p>';
		echo '<p><label>Failure <select name="fault">';
		foreach ( array( '' => 'None', 'auth' => 'Authorization', 'service' => 'Service', 'partial' => 'Incomplete catalog', 'empty-account' => 'No account Kits' ) as $value => $label ) { echo '<option value="' . esc_attr( $value ) . '" ' . selected( get_option( 'bfa_test_pro_fault' ), $value, false ) . '>' . esc_html( $label ) . '</option>'; }
		echo '</select></label></p><button class="button" type="submit">Save fixture</button></form></div>';
	} );
} );

add_action( 'template_redirect', static function () {
	if ( empty( $_GET['bfa_pro_preview'] ) || ! current_user_can( 'manage_options' ) ) { return; }
	$post = get_post( absint( $_GET['bfa_pro_preview'] ) );
	if ( ! $post ) { return; }
	echo '<!doctype html><html><head>'; wp_head(); echo '</head><body>';
	echo do_blocks( $post->post_content );
	echo do_shortcode( '[icon name="flag"] [icon name="github" style="brands"] [icon name="coffee"]' );
	wp_footer(); echo '</body></html>'; exit;
} );

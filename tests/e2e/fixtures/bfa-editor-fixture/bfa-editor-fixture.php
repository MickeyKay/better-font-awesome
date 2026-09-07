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

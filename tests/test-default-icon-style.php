<?php
/**
 * Site default style integration coverage.
 *
 * @package Better_Font_Awesome
 */
require_once __DIR__ . '/MetadataTestCase.php';

class Better_Font_Awesome_Default_Icon_Style_Test extends Better_Font_Awesome_Metadata_Test_Case {

	public function setUp(): void {
		parent::setUp();
		$this->use_default_release_channel();
		if ( WP_Block_Type_Registry::get_instance()->is_registered( Better_Font_Awesome_Icon_Block::NAME ) ) {
			unregister_block_type( Better_Font_Awesome_Icon_Block::NAME );
		}
	}

	/** @dataProvider delivery_modes */
	public function test_setting_changes_only_inherited_output_without_rewriting_posts_or_http( $mode ) {
		$plugin = $this->initialize_plugin( array( 'asset_delivery' => $mode ) );
		$block = $plugin->get( 'icon_block' );
		$block->register();
		$content = '<!-- wp:better-font-awesome/icon {"iconName":"heart"} /-->' .
			'<!-- wp:better-font-awesome/icon {"iconName":"heart","iconStyle":"solid"} /-->' .
			'<!-- wp:better-font-awesome/icon {"iconName":"heart","iconStyle":"regular"} /-->' .
			'<!-- wp:better-font-awesome/icon {"iconName":"heart","iconStyle":"site-default"} /-->';
		$post_id = self::factory()->post->create( array( 'post_content' => $content ) );
		$before = do_blocks( $content );
		$shortcode = do_shortcode( '[icon name="heart"]' );
		$this->assertSame( 3, substr_count( $before, 'fas fa-heart' ) );
		$this->assertSame( 1, substr_count( $before, 'far fa-heart' ) );
		$options = get_option( $plugin->get( 'option_name' ) );
		$options['default_block_icon_style'] = 'regular';
		update_option( $plugin->get( 'option_name' ), $options );
		$after = do_blocks( get_post( $post_id )->post_content );
		$this->assertSame( 2, substr_count( $after, 'fas fa-heart' ) );
		$this->assertSame( 2, substr_count( $after, 'far fa-heart' ) );
		$this->assertSame( $content, get_post( $post_id )->post_content );
		$this->assertSame( $shortcode, do_shortcode( '[icon name="heart"]' ) );
		foreach ( array( 'github' => 'fab', 'arrow-right' => 'fas', 'missing-icon' => 'far' ) as $name => $prefix ) {
			$this->assertStringContainsString( $prefix . ' fa-' . $name, $this->render_icon( $block, array( 'iconName' => $name, 'iconStyle' => 'site-default' ) ) );
		}
		// Unavailable explicit styles retain the established BFAL output.
		$this->assertStringContainsString( 'far fa-github', $this->render_icon( $block, array( 'iconName' => 'github', 'iconStyle' => 'regular' ) ) );
		$block->enqueue_editor_data();
		$registered = WP_Block_Type_Registry::get_instance()->get_registered( Better_Font_Awesome_Icon_Block::NAME );
		$this->assertStringContainsString( '"defaultIconStyle":"regular"', wp_scripts()->get_data( $registered->editor_script_handles[0], 'data' ) );
		$this->assertSame( 'solid', $registered->attributes['iconStyle']['default'] );
		$this->assertSame( array( 'iconStyle' => 'site-default' ), $registered->variations[0]['attributes'] );
		$this->assertSame( 0, $this->font_awesome_http_calls );
	}

	public static function delivery_modes() {
		return array( array( 'automatic' ), array( 'bundled-local' ) );
	}

	public function test_default_resolution_uses_validated_active_catalog_with_deterministic_fallback() {
		$library = new class() {
			public function get_icons() {
				return array(
					array( 'slug' => 'custom', 'title' => 'Custom', 'style' => 'brands' ),
					array( 'slug' => 'custom', 'title' => 'Custom', 'style' => 'light' ),
					array( 'slug' => 'custom', 'title' => 'Custom', 'style' => 'regular' ),
					array( 'slug' => 'custom', 'style' => 'solid' ),
				);
			}
			public function render_shortcode( $attributes ) {
				return $attributes['style'] . ':' . $attributes['name'];
			}
		};
		$block = new Better_Font_Awesome_Icon_Block( $library );
		$this->assertStringContainsString( 'regular:custom', $this->render_icon( $block, array( 'iconName' => 'custom', 'iconStyle' => 'site-default' ) ) );
		$this->assertStringContainsString( 'solid:custom', $this->render_icon( $block, array( 'iconName' => 'custom' ) ) );
	}

	public function test_default_setting_follows_current_site_without_singleton_leakage() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite-only isolation coverage.' );
		}
		$plugin = $this->initialize_plugin( array( 'default_block_icon_style' => 'regular' ) );
		$block = $plugin->get( 'icon_block' );
		$this->assertSame( 'regular', Better_Font_Awesome_Plugin::get_default_block_icon_style() );
		$site_id = self::factory()->blog->create();
		switch_to_blog( $site_id );
		try {
			$this->assertSame( 'solid', Better_Font_Awesome_Plugin::get_default_block_icon_style() );
			$this->assertStringContainsString( 'fas fa-heart', $this->render_icon( $block, array( 'iconName' => 'heart', 'iconStyle' => 'site-default' ) ) );
			update_option( $plugin->get( 'option_name' ), $plugin->sanitize( array( 'default_block_icon_style' => 'solid' ) ) );
		} finally {
			restore_current_blog();
		}
		$this->assertSame( 'regular', Better_Font_Awesome_Plugin::get_default_block_icon_style() );
		$this->assertStringContainsString( 'far fa-heart', $this->render_icon( $block, array( 'iconName' => 'heart', 'iconStyle' => 'site-default' ) ) );
		$this->assertSame( 0, $this->font_awesome_http_calls );
	}
	private function render_icon( $block, $attributes ) {
		$registered = $block->register();
		$registered->render_callback = array( $block, 'render' );
		return render_block( array(
			'blockName' => Better_Font_Awesome_Icon_Block::NAME,
			'attrs' => $attributes, 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array(),
		) );
	}

}

<?php
/**
 * Native icon block tests.
 *
 * @package Better_Font_Awesome
 */

class Better_Font_Awesome_Icon_Block_Test extends WP_UnitTestCase {

	/**
	 * Plugin instance.
	 *
	 * @var Better_Font_Awesome_Plugin
	 */
	private $plugin;

	/**
	 * Block controller.
	 *
	 * @var Better_Font_Awesome_Icon_Block
	 */
	private $block;

	public function setUp(): void {
		parent::setUp();

		$this->plugin = Better_Font_Awesome_Plugin::get_instance();
		$this->block  = $this->plugin->get( 'icon_block' );
		$this->block->register();
	}

	public function tearDown(): void {
		unregister_block_type( Better_Font_Awesome_Icon_Block::NAME );
		remove_all_filters( 'bfa_icon' );
		parent::tearDown();
	}

	public function test_registers_api_version_three_dynamic_block_from_metadata() {
		$registered = WP_Block_Type_Registry::get_instance()->get_registered( Better_Font_Awesome_Icon_Block::NAME );

		$this->assertInstanceOf( WP_Block_Type::class, $registered );
		$this->assertSame( 3, $registered->api_version );
		$this->assertSame( 'flag', $registered->attributes['iconName']['default'] );
		$this->assertSame( 'solid', $registered->attributes['iconStyle']['default'] );
		$this->assertSame( 'left', $registered->attributes['iconJustification']['default'] );
		$this->assertSame( array( 'left', 'center', 'right' ), $registered->attributes['iconJustification']['enum'] );
		$this->assertSame( '', $registered->attributes['label']['default'] );
		$this->assertArrayNotHasKey( 'align', $registered->supports );
		$this->assertTrue( is_callable( $registered->render_callback ) );
	}

	public function test_shared_layout_style_uses_a_neutral_handle() {
		$registered = WP_Block_Type_Registry::get_instance()->get_registered( Better_Font_Awesome_Icon_Block::NAME );

		$this->assertSame( array( 'bfa-icon-block-style' ), $registered->style_handles );
		$this->assertStringNotContainsString( 'font-awesome', $registered->style_handles[0] );
		$this->assertStringNotContainsString( 'fontawesome', $registered->style_handles[0] );
	}

	public function test_decorative_icon_uses_existing_renderer_and_is_hidden_from_assistive_technology() {
		$output = $this->render_block(
			array(
				'iconName'  => 'heart',
				'iconStyle' => 'regular',
				'label'     => '',
			)
		);

		$this->assertStringStartsWith( '<div ', $output );
		$this->assertStringContainsString( 'class="items-justified-left wp-block-better-font-awesome-icon"', $output );
		$this->assertStringContainsString( 'aria-hidden="true"', $output );
		$this->assertStringContainsString( '<i class="far fa-heart " ></i>', $output );
		$this->assertStringEndsWith( '</div>', $output );
	}

	public function test_internal_justification_uses_wrapper_classes_without_core_alignment() {
		foreach ( array( 'left', 'center', 'right' ) as $justification ) {
			$output = $this->render_block(
				array(
					'iconJustification' => $justification,
					'iconName'          => 'star',
					'iconStyle'         => 'solid',
				)
			);

			$this->assertStringStartsWith( '<div ', $output );
			$this->assertStringContainsString( 'class="items-justified-' . $justification . ' wp-block-better-font-awesome-icon"', $output );
			$this->assertStringNotContainsString( 'align' . $justification, $output );
			$this->assertStringContainsString( '<i class="fas fa-star " ></i>', $output );
		}
	}

	public function test_labelled_icon_exposes_sanitized_accessible_name() {
		$output = $this->render_block(
			array(
				'iconName'  => 'coffee',
				'iconStyle' => 'solid',
				'label'     => 'Coffee <strong>time</strong>',
			)
		);

		$this->assertStringContainsString( 'role="img"', $output );
		$this->assertStringContainsString( 'aria-label="Coffee time"', $output );
		$this->assertStringNotContainsString( '<strong>', $output );
		$icon_name = 'rollback' === getenv( 'BFA_BFAL_VALIDATION_MODE' ) ? 'coffee' : 'mug-saucer';
		$this->assertStringContainsString( '<i class="fas fa-' . $icon_name . ' " ></i>', $output );
	}

	public function test_render_preserves_existing_icon_output_filter() {
		add_filter(
			'bfa_icon',
			static function ( $html ) {
				return '<span data-filtered="true">' . $html . '</span>';
			}
		);

		$output = $this->render_block(
			array(
				'iconName'  => 'star',
				'iconStyle' => 'solid',
			)
		);

		$this->assertStringContainsString( '<span data-filtered="true"><i class="fas fa-star " ></i></span>', $output );
	}

	public function test_invalid_attribute_shapes_fail_closed_to_safe_defaults() {
		$output = $this->render_block(
			array(
				'iconJustification' => 'unsupported-justification',
				'iconName'          => array( 'not-a-string' ),
				'iconStyle'         => 'unsupported-style',
				'label'             => array( 'not-a-string' ),
			)
		);

		$this->assertStringContainsString( 'aria-hidden="true"', $output );
		$this->assertStringContainsString( 'class="items-justified-left wp-block-better-font-awesome-icon"', $output );
		$this->assertStringContainsString( '<i class="fas fa-flag " ></i>', $output );
	}

	public function test_editor_catalog_contains_only_safe_free_icon_fields() {
		$catalog = $this->block->get_editor_catalog();

		$this->assertNotEmpty( $catalog );
		$this->assertSame( array( 'label', 'name', 'style' ), array_keys( $catalog[0] ) );
		$this->assertMatchesRegularExpression( '/^[a-z0-9-]+$/', $catalog[0]['name'] );
		$this->assertContains( $catalog[0]['style'], array( 'brands', 'regular', 'solid' ) );
	}

	/**
	 * Render the registered dynamic block through WordPress's public API.
	 *
	 * @param array $attributes Block attributes.
	 * @return string Rendered block markup.
	 */
	private function render_block( $attributes ) {
		return render_block(
			array(
				'blockName'    => Better_Font_Awesome_Icon_Block::NAME,
				'attrs'        => $attributes,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}
}

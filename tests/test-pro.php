<?php
/** Pro acquisition and delivery tests use synthetic service responses only. */
require_once __DIR__ . '/MetadataTestCase.php';
require_once __DIR__ . '/pro-fixture.php';

class Better_Font_Awesome_Pro_Test extends Better_Font_Awesome_Metadata_Test_Case {
	private $api;
	private $pro;
	public function setUp(): void {
		parent::setUp();
		$this->use_default_release_channel();
		delete_option( Better_Font_Awesome_Pro::OPTION );
		$this->api = new Better_Font_Awesome_Pro_Fixture();
		add_filter( 'pre_http_request', array( $this->api, 'response' ), 20, 3 );
		$plugin    = $this->initialize_plugin( array( 'asset_delivery' => 'automatic' ) );
		$this->pro = $plugin->get( 'pro' );
	}
	public function tearDown(): void {
		remove_filter( 'pre_http_request', array( $this->api, 'response' ), 20 );
		Better_Font_Awesome_Pro::cancel( true );
		delete_option( Better_Font_Awesome_Pro::OPTION );
		parent::tearDown();
	}
	private function complete() {
		for ( $i = 0; $i < 90 && $this->pro->status()['pending']; $i++ ) {
			$status = $this->pro->step( $this->pro->status()['operation'] );
			$this->assertEmpty( $status['error'] );
		}
		$this->assertTrue( Better_Font_Awesome_Pro::state()['enabled'] );
		$this->assertFalse( $this->pro->status()['connected'], 'Activation is confirmed only after a request initializes the Kit.' );
		return Better_Font_Awesome_Pro::state()['active'];
	}
	public function test_immediate_bounded_connect_and_refresh_without_running_cron() {
		$this->assertSame( 0, $this->api->requests );
		$status = $this->pro->start( 'KIT_ID', 'SYNTHETIC-ACCOUNT-NOT-A-CREDENTIAL' );
		$this->assertSame( 1, $this->api->requests );
		$this->assertFalse( $status['connected'] );
		$this->assertSame( 'metadata', $status['phase'] );
		$active = $this->complete();
		$this->assertCount( count( $this->api->rows ), $active['icons'] );
		$this->assertSame( 5, count( $active['styles'] ) );
		$this->assertSame( (int) ceil( count( $this->api->rows ) / 16000 ) + 4, $this->api->requests );
		$before = $this->api->requests;
		$this->pro->start();
		$this->assertSame( $before + 1, $this->api->requests );
		$this->assertSame( $active, Better_Font_Awesome_Pro::state()['active'] );
		$this->complete();
	}
	public function test_full_sized_catalog_uses_one_catalog_request_and_four_validation_requests() {
		$this->api->expand();
		$this->pro->start( 'KIT_ID', 'SYNTHETIC-TOKEN' );
		$active = $this->complete();
		$this->assertCount( count( $this->api->rows ), $active['icons'] );
		$this->assertSame( 5, $this->api->requests );
		$queries = array_filter( $this->api->queries, static function ( $query ) { return false !== strpos( $query, 'p1:iconVariantsPaginated' ); } );
		$this->assertCount( 1, $queries );
		$this->assertStringContainsString( 'p32:iconVariantsPaginated(page:32,pageSize:500)', reset( $queries ) );
		$this->assertStringNotContainsString( 'p33:', reset( $queries ) );
	}

	public function test_large_catalog_continues_after_32_pages_without_repeating_or_skipping_icons() {
		$this->api->expand();
		for ( $i = 0; $i < 1000; ++$i ) {
			$this->api->rows[] = array( 'name' => 'extra-' . $i, 'familyStyle' => array( 'family' => 'classic', 'style' => 'solid', 'prefix' => 'fas' ) );
		}
		$this->pro->start( 'KIT_ID', 'SYNTHETIC-TOKEN' );
		$active = $this->complete();
		$this->assertCount( count( $this->api->rows ), $active['icons'] );
		$this->assertSame( 6, $this->api->requests );
		$this->assertTrue( $active['icons']['extra-999:solid'] );
	}

	public function test_in_progress_single_page_candidate_resumes_with_batched_pages() {
		$this->api->expand();
		$this->pro->start( 'KIT_ID', 'SYNTHETIC-TOKEN' );
		$this->pro->step( $this->pro->status()['operation'] );
		$state = Better_Font_Awesome_Pro::state();
		$state['candidate']['page'] = 2;
		foreach ( array_slice( $this->api->rows, 0, 500 ) as $row ) {
			$style = $row['familyStyle']['style'];
			$state['candidate']['icons'][ $row['name'] . ':' . $style ] = true;
			$state['candidate']['counts'][ $style ] = ( $state['candidate']['counts'][ $style ] ?? 0 ) + 1;
		}
		update_option( Better_Font_Awesome_Pro::OPTION, $state, false );
		$active = $this->complete();
		$this->assertCount( count( $this->api->rows ), $active['icons'] );
		$this->assertSame( 5, $this->api->requests );
	}

	public function test_credentials_are_encrypted_non_autoloaded_and_never_in_status_or_html() {
		$this->pro->start( 'KIT_ID', 'SYNTHETIC-ACCOUNT-NOT-A-CREDENTIAL' );
		$state = wp_json_encode( Better_Font_Awesome_Pro::state() );
		$this->assertStringNotContainsString( 'SYNTHETIC-ACCOUNT', $state );
		$this->assertStringNotContainsString( 'SYNTHETIC-ACCESS', $state );
		$this->assertStringNotContainsString( 'credential', wp_json_encode( $this->pro->status() ) );
		$this->assertArrayNotHasKey( Better_Font_Awesome_Pro::OPTION, wp_load_alloptions() );
		ob_start();
		Better_Font_Awesome_Plugin::get_instance()->pro_settings();
		$html = ob_get_clean();
		$this->assertStringNotContainsString( 'SYNTHETIC-', $html );
	}
	/** @dataProvider failures */
	public function test_partial_failed_and_unsupported_candidates_never_replace_active( $fault, $phase, $expected ) {
		$this->pro->start( 'KIT_ID', 'SYNTHETIC-TOKEN' );
		$active = $this->complete();
		$this->pro->start( 'REPLACEMENT' );
		while ( $phase !== $this->pro->status()['phase'] ) {
			$this->pro->step( $this->pro->status()['operation'] );
		}
		$before = Better_Font_Awesome_Pro::state()['candidate'];
		$this->api->fault = $fault;
		$this->pro->step( $this->pro->status()['operation'] );
		$this->assertSame( $expected, $this->pro->status()['error'] );
		$this->assertSame( $active, Better_Font_Awesome_Pro::state()['active'] );
		$after = Better_Font_Awesome_Pro::state()['candidate'];
		$this->assertSame( $before['icons'], $after['icons'], 'A failed batch must not save partial pages.' );
		$this->assertSame( $before['counts'], $after['counts'] );
		$this->assertSame( $before['page'], $after['page'] );
		$this->assertStringNotContainsString( 'DO-NOT-EXPOSE', wp_json_encode( Better_Font_Awesome_Pro::state() ) );
	}
	public static function failures() {
		return array(
			array( 'service', 'icons', 'service' ),
			array( 'auth', 'icons', 'auth' ),
			array( 'unsupported', 'metadata', 'unsupported' ),
			array( 'partial', 'icons', 'incomplete' ),
			array( 'missing-batch-page', 'icons', 'incomplete' ),
			array( 'wrong-batch-page', 'icons', 'incomplete' ),
			array( 'cross-page-duplicate', 'icons', 'incomplete' ),
			array( 'duplicate', 'icons', 'incomplete' ),
			array( 'coverage', 'free-coverage', 'coverage' ),
		);
	}
	public function test_revision_change_before_promotion_is_rejected() {
		$this->pro->start( 'KIT_ID', 'SYNTHETIC-TOKEN' );
		while ( 'verify' !== $this->pro->status()['phase'] ) {
			$this->pro->step( $this->pro->status()['operation'] ); }
		$this->api->revision = 'new-revision';
		$this->pro->step( $this->pro->status()['operation'] );
		$this->assertSame( 'revision', $this->pro->status()['error'] );
		$this->assertArrayNotHasKey( 'active', Better_Font_Awesome_Pro::state() );
	}
	/** @dataProvider invalid_exchange */
	public function test_invalid_permission_or_token_is_rejected_on_initial_exchange( $fault ) {
		$this->api->fault = $fault;
		$this->pro->start( 'KIT_ID', 'SYNTHETIC-TOKEN' );
		$this->assertSame( 'auth', $this->pro->status()['error'] );
		$this->assertFalse( $this->pro->status()['pending'] );
	}
	public static function invalid_exchange() {
		return array( array( 'scope' ), array( 'empty-token' ) );
	}
	public function test_replacement_cancellation_and_late_worker_cannot_resurrect_state() {
		$first  = $this->pro->start( 'KIT_ID', 'SYNTHETIC-TOKEN' );
		$second = $this->pro->start( 'SECOND', 'SYNTHETIC-SECOND' );
		$count  = $this->api->requests;
		$this->pro->step( $first['operation'] );
		$this->assertSame( $count, $this->api->requests );
		$cancel = static function ( $response ) {
			Better_Font_Awesome_Pro::cancel( true );
			return $response;
		};
		add_filter( 'pre_http_request', $cancel, 30 );
		$this->pro->step( $second['operation'] );
		remove_filter( 'pre_http_request', $cancel, 30 );
		$this->assertFalse( $this->pro->status()['pending'] );
		$this->assertFalse( $this->pro->status()['connected'] );
		$this->assertArrayNotHasKey( 'active', Better_Font_Awesome_Pro::state() );
		$this->pro->worker( $second['operation'] );
		foreach ( _get_cron_array() as $hooks ) {
			$this->assertArrayNotHasKey( Better_Font_Awesome_Pro::HOOK, $hooks ); }
	}
	public function test_failed_reads_do_not_acquire_and_local_switch_cancels_work() {
		$this->pro->start( 'KIT_ID', 'SYNTHETIC-TOKEN' );
		$this->complete();
		$this->api->fault = 'auth';
		$this->pro->start();
		$count = $this->api->requests;
		for ( $i = 0; $i < 5; $i++ ) {
			$this->pro->status();
			$this->pro->icons( array() );
			$this->pro->schedule(); }
		$this->assertSame( $count, $this->api->requests );
		update_option( 'better-font-awesome_options', array( 'asset_delivery' => 'bundled-local' ) );
		$this->assertWPError( $this->pro->start() );
		$this->assertFalse( $this->pro->status()['pending'] );
		$this->assertArrayHasKey( 'active', Better_Font_Awesome_Pro::state() );
		$this->assertSame( $count, $this->api->requests );
	}
	public function test_transient_retry_is_bounded_and_shared_with_worker() {
		$this->api->fault = 'service';
		$this->pro->start( 'KIT_ID', 'SYNTHETIC-TOKEN' );
		$id = $this->pro->status()['operation'];
		$this->pro->worker( $id );
		$this->assertSame( 1, $this->api->requests, 'Backoff must prevent a request.' );
		for ( $i = 0; $i < 4; $i++ ) {
			$s                          = Better_Font_Awesome_Pro::state();
			$s['candidate']['retry_at'] = 0;
			update_option( Better_Font_Awesome_Pro::OPTION, $s, false );
			$this->pro->worker( $id );
		}
		$this->assertSame( 5, $this->api->requests );
		$this->assertFalse( $this->pro->status()['pending'] );
		$this->pro->worker( $id );
		$this->assertSame( 5, $this->api->requests );
	}
	public function test_earlier_matching_kit_owner_cannot_install_bfa_catalog() {
		$this->pro->start( 'KIT_ID', 'SYNTHETIC-TOKEN' );
		$this->complete();
		$controller = new Better_Font_Awesome_Pro();
		$args       = $controller->initialization_args( array() );
		$library    = new class() {
			public function get_asset_delivery() {
				return 'kit-css'; }
			public function get_release_channel() {
				return '7.x'; }
			public function get_stylesheet_url() {
				return 'https://kit.fontawesome.com/KIT_ID.css'; }
		};
		$controller->boot( $library, false );
		$this->assertFalse( $controller->effective() );
		$this->assertSame( array( 'earlier-owner' ), $controller->icons( array( 'earlier-owner' ) ) );
		$this->assertWPError( $controller->start() );
		$this->assertSame( 'kit-css', $args['asset_delivery'] );
	}
	public function test_pro_assets_styles_and_inheritance_are_paired_after_next_initialization() {
		$this->pro->start( 'KIT_ID', 'SYNTHETIC-TOKEN' );
		$this->complete();
		foreach ( array( Better_Font_Awesome_Plugin::class, Better_Font_Awesome_Library::class ) as $class ) {
			$p = new ReflectionProperty( $class, 'instance' );
			$p->setAccessible( true );
			$p->setValue( null, null );
		}
		$plugin = Better_Font_Awesome_Plugin::get_instance();
		$pro    = $plugin->get( 'pro' );
		$this->assertTrue( $pro->effective() );
		ob_start();
		$plugin->asset_delivery_callback();
		$this->assertStringNotContainsString( 'id="bfa-delivery-status"', ob_get_clean() );
		$library = $plugin->get( 'bfa_lib' );
		$this->assertSame( 'https://kit.fontawesome.com/KIT_ID.css', $library->get_stylesheet_url() );
		$block = $plugin->get( 'icon_block' );
		$block->register();
		foreach ( array(
			'solid'   => 'fas',
			'regular' => 'far',
			'light'   => 'fal',
			'thin'    => 'fat',
		) as $style => $prefix ) {
			update_option( 'better-font-awesome_options', array( 'default_block_icon_style' => $style ) );
			$this->assertStringContainsString(
				$prefix . ' fa-pro-fixture',
				$this->render_icon(
					$block,
					array(
						'iconName'  => 'pro-fixture',
						'iconStyle' => 'site-default',
					)
				)
			);
		}
		$this->assertStringContainsString( 'fas fa-pro-fixture', $this->render_icon( $block, array( 'iconName' => 'pro-fixture' ) ) );
		$this->assertStringContainsString(
			'far fa-pro-fixture',
			$this->render_icon(
				$block,
				array(
					'iconName'  => 'pro-fixture',
					'iconStyle' => 'regular',
				)
			)
		);
		$this->assertStringContainsString(
			'fab fa-github',
			$this->render_icon(
				$block,
				array(
					'iconName'  => 'github',
					'iconStyle' => 'site-default',
				)
			)
		);
		$this->assertStringContainsString(
			'fat fa-missing',
			$this->render_icon(
				$block,
				array(
					'iconName'  => 'missing',
					'iconStyle' => 'thin',
				)
			)
		);
		$this->assertSame( array(), $library->get_stylesheet_url_v4_shim() ? array( 'bad' ) : array() );
	}
	/** @dataProvider authorization_cases */
	public function test_ajax_rejects_bad_nonce_and_non_administrators_without_http( $role, $valid_nonce ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
		$_POST    = array(
			'operation' => 'connect',
			'kit'       => 'KIT_ID',
			'token'     => 'SYNTHETIC-SECRET',
			'nonce'     => $valid_nonce ? wp_create_nonce( 'bfa-pro' ) : 'invalid',
		);
		$_REQUEST = $_POST;
		$handler  = static function () {
			return static function () {
				throw new RuntimeException( 'terminated' );
			};
		};
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', $handler );
		ob_start();
		try {
			$this->pro->ajax();
			$this->fail( 'Expected rejection' ); } catch ( RuntimeException $e ) {
			$output = ob_get_clean(); } finally {
				remove_filter( 'wp_die_ajax_handler', $handler );
				remove_filter( 'wp_doing_ajax', '__return_true' ); }
			$this->assertStringContainsString( 'not allowed', $output );
			$this->assertSame( 0, $this->api->requests );
			$this->assertEmpty( Better_Font_Awesome_Pro::state() );
	}
	public static function authorization_cases() {
		return array( array( 'subscriber', true ), array( 'administrator', false ) ); }
	public function test_interrupted_step_lease_can_expire_and_resume_without_partial_activation() {
		$this->pro->start( 'KIT_ID', 'SYNTHETIC-TOKEN' );
		$id                           = $this->pro->status()['operation'];
		$s                            = Better_Font_Awesome_Pro::state();
		$s['candidate']['busy_until'] = time() + 600;
		update_option( Better_Font_Awesome_Pro::OPTION, $s, false );
		$this->pro->step( $id );
		$this->assertSame( 1, $this->api->requests );
		$s['candidate']['busy_until'] = time() - 1;
		update_option( Better_Font_Awesome_Pro::OPTION, $s, false );
		$this->pro->step( $id );
		$this->assertSame( 2, $this->api->requests );
		$this->assertFalse( $this->pro->status()['connected'] );
		$this->complete();
	}
	public function test_cancellation_during_cron_publication_cannot_restore_event() {
		$this->pro->start( 'KIT_ID', 'SYNTHETIC-TOKEN' );
		wp_unschedule_hook( Better_Font_Awesome_Pro::HOOK );
		$cancel = static function ( $event ) {
			if ( Better_Font_Awesome_Pro::HOOK === $event->hook ) {
				Better_Font_Awesome_Pro::cancel( true );
			} return $event;
		};
		add_filter( 'schedule_event', $cancel );
		$this->pro->schedule();
		remove_filter( 'schedule_event', $cancel );
		foreach ( _get_cron_array() as $hooks ) {
			$this->assertArrayNotHasKey( Better_Font_Awesome_Pro::HOOK, $hooks ); }
	}
	public function test_multisite_does_not_reuse_another_sites_owner_or_credentials() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite-only isolation' ); }
		$this->pro->start( 'KIT_ID', 'SYNTHETIC-TOKEN' );
		$original = Better_Font_Awesome_Pro::state();
		switch_to_blog( self::factory()->blog->create() );
		try {
			$this->assertEmpty( Better_Font_Awesome_Pro::state() );
			$this->assertWPError( $this->pro->start( 'KIT_ID', 'SYNTHETIC-TOKEN' ) ); } finally {
			restore_current_blog(); }
			$this->assertSame( $original, Better_Font_Awesome_Pro::state() );
	}

	public function test_deactivation_suspends_old_workers_and_reactivation_preserves_choice() {
		$this->pro->start( 'KIT_ID', 'SYNTHETIC-TOKEN' );
		$active = $this->complete();
		$this->pro->start();
		$id = $this->pro->status()['operation'];
		Better_Font_Awesome_Plugin::deactivate_metadata();
		$count = $this->api->requests;
		$this->pro->worker( $id );
		$this->assertWPError( $this->pro->start() );
		$this->assertSame( $count, $this->api->requests );
		$this->assertSame( $active, Better_Font_Awesome_Pro::state()['active'] );
		Better_Font_Awesome_Pro::reactivate();
		$this->assertTrue( Better_Font_Awesome_Pro::state()['enabled'] );
		$this->assertSame( $count, $this->api->requests );
	}
	public function test_light_thin_defaults_are_offered_only_when_available_but_saved_values_survive() {
		$plugin = Better_Font_Awesome_Plugin::get_instance();
		$this->assertSame( 'solid', $plugin->sanitize( array( 'default_block_icon_style' => 'thin' ) )['default_block_icon_style'] );
		update_option( 'better-font-awesome_options', array( 'default_block_icon_style' => 'thin' ) );
		$this->assertSame( 'thin', $plugin->sanitize( array( 'default_block_icon_style' => 'thin' ) )['default_block_icon_style'] );
		ob_start();
		$plugin->default_block_icon_style_callback();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'saved, currently unavailable', $html );
	}

	public function test_expiring_access_token_renews_without_losing_pagination() {
		$this->pro->start( 'KIT_ID', 'SYNTHETIC-TOKEN' );
		$id = $this->pro->status()['operation'];
		$this->pro->step( $id );
		$this->pro->step( $id );
		$before                         = Better_Font_Awesome_Pro::state();
		$before['candidate']['expires'] = time() - 1;
		update_option( Better_Font_Awesome_Pro::OPTION, $before, false );
		$count = $this->api->requests;
		$this->pro->step( $id );
		$after = Better_Font_Awesome_Pro::state();
		$this->assertSame( $count + 1, $this->api->requests );
		$this->assertSame( $before['candidate']['icons'], $after['candidate']['icons'] );
		$this->assertSame( $before['candidate']['page'], $after['candidate']['page'] );
		$this->complete();
	}
	public function test_admin_ajax_connect_starts_immediately_with_safe_status() {
		$account = $this->pro->find_kits( 'SYNTHETIC-TOKEN' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST    = array(
			'operation' => 'connect',
			'kit'       => 'KIT_ID',
			'id'        => $account['id'],
			'nonce'     => wp_create_nonce( 'bfa-pro' ),
		);
		$_REQUEST = $_POST;
		$handler  = static function () {
			return static function () {
				throw new RuntimeException( 'done' );
			};
		};
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', $handler );
		ob_start();
		try {
			$this->pro->ajax();
			$this->fail( 'Expected JSON termination' );
		} catch ( RuntimeException $e ) {
			$output = ob_get_clean();
		} finally {
			remove_filter( 'wp_doing_ajax', '__return_true' );
				remove_filter( 'wp_die_ajax_handler', $handler );}
		$this->assertSame( 3, $this->api->requests );
		$this->assertTrue( json_decode( $output, true )['data']['pending'] );
		$this->assertStringNotContainsString( 'SYNTHETIC-TOKEN', $output );
		$this->assertStringNotContainsString( 'credential', $output );
	}

	public function test_filter_overridden_delivery_never_claims_connected_or_loops_activation() {
		$this->pro->start( 'KIT_ID', 'SYNTHETIC-TOKEN' );
		$this->complete();
		$controller = new Better_Font_Awesome_Pro();
		$controller->initialization_args( array() );
		$library = new class(){public function get_asset_delivery() {
				return 'automatic';
		} public function get_release_channel() {
			return '7.x';
		}};
		$controller->boot( $library, true );
		$status = $controller->status();
		$this->assertFalse( $status['connected'] );
		$this->assertFalse( $status['activationRequired'] );
		$this->assertSame( 'ownership', $status['error'] );
		$this->assertWPError( $controller->start() );
	}

	private function render_icon( $block, $attributes ) {
		$type                  = $block->register();
		$type->render_callback = array( $block, 'render' );
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
	public function test_real_earlier_singleton_with_matching_kit_keeps_its_catalog() {
		$this->pro->start( 'KIT_ID', 'SYNTHETIC-TOKEN' );
		$this->complete();
		foreach ( array( Better_Font_Awesome_Plugin::class, Better_Font_Awesome_Library::class ) as $class ) {
			$p = new ReflectionProperty( $class, 'instance' );
			$p->setAccessible( true );
			$p->setValue( null, null );}
		$earlier = Better_Font_Awesome_Library::get_instance(
			array(
				'asset_delivery'  => 'kit-css',
				'kit_css_url'     => 'https://kit.fontawesome.com/KIT_ID.css',
				'release_channel' => '7.x',
			)
		);
		$plugin  = Better_Font_Awesome_Plugin::get_instance();
		$this->assertSame( $earlier, $plugin->get( 'bfa_lib' ) );
		$this->assertFalse( $plugin->get( 'pro' )->status()['allowed'] );
		$this->assertFalse( $plugin->get( 'pro' )->effective() );
		$this->assertNotContains( 'pro-fixture', array_column( $earlier->get_icons(), 'slug' ) );
	}
	public function test_active_service_failure_retries_next_day_without_ordinary_http() {
		$this->pro->start( 'KIT_ID', 'SYNTHETIC-TOKEN' );
		$active = $this->complete();
		$this->api->fault = 'service';
		$this->pro->start();
		$id = $this->pro->status()['operation'];
		for ( $i = 0; $i < 4; $i++ ) {
			$state = Better_Font_Awesome_Pro::state();
			$state['candidate']['retry_at'] = 0;
			update_option( Better_Font_Awesome_Pro::OPTION, $state, false );
			$this->pro->worker( $id );
		}
		$count = $this->api->requests;
		$this->pro->schedule();
		$this->assertSame( $count, $this->api->requests );
		$this->assertFalse( $this->pro->status()['pending'] );
		$event = wp_next_scheduled( Better_Font_Awesome_Pro::HOOK, array( 'refresh-' . $active['generation'] ) );
		$this->assertGreaterThanOrEqual( time() + DAY_IN_SECONDS - 5, $event );
		$state = Better_Font_Awesome_Pro::state();
		$state['candidate']['retry_at'] = 0;
		update_option( Better_Font_Awesome_Pro::OPTION, $state, false );
		$this->api->fault = '';
		$this->pro->worker( 'refresh-' . $active['generation'] );
		$this->assertSame( $count + 1, $this->api->requests );
		$this->assertTrue( $this->pro->status()['pending'] );
		$this->assertSame( $active, Better_Font_Awesome_Pro::state()['active'] );
	}

	public function test_corrupted_saved_credential_fails_closed_without_http() {
		$this->pro->start( 'KIT_ID', 'SYNTHETIC-TOKEN' );
		$this->complete();
		$state = Better_Font_Awesome_Pro::state();
		$state['active']['credential'] = 'not-valid-authenticated-ciphertext';
		update_option( Better_Font_Awesome_Pro::OPTION, $state, false );
		$count = $this->api->requests;
		$this->pro->start();
		$this->assertSame( $count, $this->api->requests );
		$this->assertSame( 'storage', $this->pro->status()['error'] );
		$this->assertFalse( $this->pro->status()['pending'] );
	}

	public function test_malformed_revision_cannot_interrupt_error_handling() {
		$this->pro->start( 'KIT_ID', 'SYNTHETIC-TOKEN' );
		$id = $this->pro->status()['operation'];
		$this->pro->step( $id );
		$this->api->revision = array( 'malformed' );
		$this->pro->step( $id );
		$this->assertSame( 'revision', $this->pro->status()['error'] );
		$this->assertFalse( $this->pro->status()['pending'] );
	}

	public function test_network_lifecycle_leaves_other_network_connections_untouched() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite-only lifecycle' );
		}
		$other_network = self::factory()->network->create();
		$other_site = self::factory()->blog->create( array( 'site_id' => $other_network ) );
		$state = array( 'enabled' => true, 'active' => array( 'kit' => 'KIT_ID' ) );
		update_option( Better_Font_Awesome_Pro::OPTION, $state, false );
		update_blog_option( $other_site, Better_Font_Awesome_Pro::OPTION, $state );
		Better_Font_Awesome_Plugin::deactivate_metadata( true );
		$this->assertTrue( Better_Font_Awesome_Pro::state()['suspended'] );
		$this->assertSame( $state, get_blog_option( $other_site, Better_Font_Awesome_Pro::OPTION ) );
		Better_Font_Awesome_Plugin::activate( true );
		$this->assertTrue( Better_Font_Awesome_Pro::state()['enabled'] );
		$this->assertSame( $state, get_blog_option( $other_site, Better_Font_Awesome_Pro::OPTION ) );
		$this->assertSame( 0, $this->api->requests );
	}

	public function test_cancellation_sees_a_connection_created_after_cached_absence() {
		$this->assertSame( array(), Better_Font_Awesome_Pro::state() );
		// Simulate another request creating the record after this request cached absence.
		global $wpdb;
		$wpdb->insert( $wpdb->options, array(
			'option_name' => Better_Font_Awesome_Pro::OPTION,
			'option_value' => maybe_serialize( array( 'enabled' => true, 'active' => array( 'kit' => 'EXISTING' ), 'candidate' => array( 'id' => 'concurrent' ) ) ),
			'autoload' => 'no',
		) );
		$this->assertTrue( Better_Font_Awesome_Pro::cancel() );
		$this->assertFalse( Better_Font_Awesome_Pro::state()['enabled'] );
		$this->assertEmpty( Better_Font_Awesome_Pro::state()['candidate'] );
		$this->assertSame( array( 'kit' => 'EXISTING' ), Better_Font_Awesome_Pro::state()['active'] ?? null );
	}

	public function test_initial_insert_cannot_overwrite_a_concurrent_connection() {
		$inserted = false;
		$concurrent = array( 'enabled' => true, 'active' => array( 'kit' => 'EXISTING' ) );
		$interleave = static function ( $query ) use ( &$inserted, $concurrent ) {
			if ( ! $inserted && 0 === strpos( ltrim( $query ), 'INSERT' ) && false !== strpos( $query, Better_Font_Awesome_Pro::OPTION ) ) {
				$inserted = true;
				global $wpdb;
				$wpdb->insert( $wpdb->options, array( 'option_name' => Better_Font_Awesome_Pro::OPTION, 'option_value' => maybe_serialize( $concurrent ), 'autoload' => 'no' ) );
			}
			return $query;
		};
		add_filter( 'query', $interleave );
		try {
			$result = $this->pro->start( 'KIT_ID', 'SYNTHETIC-TOKEN' );
		} finally {
			remove_filter( 'query', $interleave );
		}
		$this->assertTrue( $inserted );
		$this->assertWPError( $result );
		$this->assertSame( $concurrent, Better_Font_Awesome_Pro::state() );
		$this->assertSame( 0, $this->api->requests );
	}

	public function test_discovery_lists_named_unsupported_and_unnamed_kits_without_catalog_or_activation() {
		$account = $this->pro->find_kits( 'SYNTHETIC-TOKEN' );
		$this->assertTrue( $account['authorized'] );
		$this->assertCount( 4, $account['kits'] );
		$this->assertSame( 'BFA staging', $account['kits'][0]['name'] );
		$this->assertSame( $account['kits'][0]['name'], $account['kits'][1]['name'] );
		$this->assertStringContainsString( 'Classic styles:', $account['kits'][0]['summary'] );
		$this->assertStringNotContainsString( 'still need validation', $account['kits'][0]['summary'] );
		$this->assertFalse( $account['kits'][2]['supported'] );
		$this->assertNotEmpty( $account['kits'][2]['summary'] );
		$this->assertSame( '', $account['kits'][3]['name'] );
		$this->assertSame( 2, $this->api->requests );
		$this->assertCount( 1, $this->api->queries );
		$this->assertStringContainsString( 'iconVariantsPaginated(pageSize:1){totalIconVariantCount}', $this->api->queries[0] );
		$this->assertStringNotContainsString( 'iconVariants{', $this->api->queries[0] );
		$this->assertStringNotContainsString( 'icons{', $this->api->queries[0] );
		$this->assertArrayNotHasKey( 'candidate', Better_Font_Awesome_Pro::state() );
		$this->assertFalse( $this->pro->status()['connected'] );
		$this->assertStringNotContainsString( 'SYNTHETIC-', wp_json_encode( Better_Font_Awesome_Pro::state() ) );
		$this->assertStringNotContainsString( 'credential', wp_json_encode( $account ) );
		$count = $this->api->requests;
		$this->pro->account_status();
		$this->pro->status();
		$this->assertSame( $count, $this->api->requests );
		$status = $this->pro->start( 'KIT_ID', '', $account['id'] );
		$this->assertSame( 'metadata', $status['phase'] );
		$active = $this->complete();
		$this->assertSame( 'BFA staging', $active['name'] );
	}
	public function test_discovery_empty_list_is_authorized_but_does_not_connect() {
		$this->api->kits = array();
		$account = $this->pro->find_kits( 'SYNTHETIC-TOKEN' );
		$this->assertTrue( $account['authorized'] );
		$this->assertSame( array(), $account['kits'] );
		$this->assertWPError( $this->pro->start( 'KIT_ID', '', $account['id'] ) );
		$this->assertSame( 2, $this->api->requests );
	}
	public function test_discovery_rejects_forged_unsupported_and_stale_selections_without_http() {
		$first = $this->pro->find_kits( 'SYNTHETIC-TOKEN' );
		$second = $this->pro->find_kits();
		foreach ( array( array( 'KIT_ID', '' ), array( 'FORGED', $second['id'] ), array( 'SVG_KIT', $second['id'] ), array( 'KIT_ID', $first['id'] ) ) as $selection ) {
			$this->assertSame( 'selection', $this->pro->start( $selection[0], '', $selection[1] )->get_error_code() );
		}
		$this->assertSame( 4, $this->api->requests );
	}
	public function test_discovery_failed_replacement_preserves_active_and_saved_authorization() {
		$account = $this->pro->find_kits( 'SYNTHETIC-TOKEN' );
		$this->pro->start( 'KIT_ID', '', $account['id'] );
		$active = $this->complete();
		$saved = Better_Font_Awesome_Pro::state()['account'];
		foreach ( array( 'auth', 'service', 'scope', 'empty-token' ) as $fault ) {
			$this->api->fault = $fault;
			$this->assertWPError( $this->pro->find_kits( 'SYNTHETIC-REPLACEMENT' ) );
			$this->assertSame( $active, Better_Font_Awesome_Pro::state()['active'] );
			$this->assertSame( $saved, Better_Font_Awesome_Pro::state()['account'] );
			$this->assertFalse( $this->pro->account_status()['authorized'] );
		}
		$this->api->fault = '';
		$this->assertTrue( $this->pro->find_kits()['authorized'] );
		$this->assertSame( $saved['credential'], Better_Font_Awesome_Pro::state()['account']['credential'] );
		$this->assertStringNotContainsString( 'DO-NOT-EXPOSE', wp_json_encode( Better_Font_Awesome_Pro::state() ) );
	}
	public function test_discovery_late_response_cannot_replace_newer_authorization() {
		$newer = null;
		$overlap = function ( $response ) use ( &$overlap, &$newer ) {
			remove_filter( 'pre_http_request', $overlap, 30 );
			$this->api->kits = array( array( 'token' => 'NEW_KIT', 'name' => 'New account' ) );
			$newer = $this->pro->find_kits( 'SYNTHETIC-NEW-TOKEN' );
			return $response;
		};
		add_filter( 'pre_http_request', $overlap, 30 );
		$old = $this->pro->find_kits( 'SYNTHETIC-OLD-TOKEN' );
		$this->assertSame( 'changed', $old->get_error_code() );
		$this->assertSame( $newer, $this->pro->account_status() );
		$this->assertSame( 'New account', $newer['kits'][0]['name'] );
	}
	public function test_discovery_selection_is_revalidated_under_current_token_before_catalog() {
		$account = $this->pro->find_kits( 'SYNTHETIC-TOKEN' );
		$this->api->fault = 'unsupported';
		$status = $this->pro->start( 'KIT_ID', '', $account['id'] );
		$this->pro->step( $status['operation'] );
		$this->assertSame( 'unsupported', $this->pro->status()['error'] );
		$this->assertSame( array(), Better_Font_Awesome_Pro::state()['candidate']['icons'] );
		$this->assertArrayNotHasKey( 'active', Better_Font_Awesome_Pro::state() );
	}
	public function test_discovery_disconnect_fences_late_result_and_forgets_authorization() {
		$this->pro->find_kits( 'SYNTHETIC-TOKEN' );
		$cancel = static function ( $response ) { Better_Font_Awesome_Pro::cancel( true ); return $response; };
		add_filter( 'pre_http_request', $cancel, 30 );
		$this->assertWPError( $this->pro->find_kits() );
		remove_filter( 'pre_http_request', $cancel, 30 );
		$this->assertFalse( $this->pro->account_status()['saved'] );
		$this->assertFalse( $this->pro->account_status()['authorized'] );
	}

	/** @dataProvider discovery_configurations */
	public function test_discovery_configuration_checks_explain_unsupported_kits( $configuration ) {
		$this->api->kits = array( array_merge( array( 'token' => 'KIT_ID', 'name' => 'Unsupported Kit' ), $configuration ) );
		$account = $this->pro->find_kits( 'SYNTHETIC-TOKEN' );
		$this->assertTrue( $account['authorized'] );
		$this->assertCount( 1, $account['kits'] );
		$this->assertFalse( $account['kits'][0]['supported'] );
		$this->assertStringContainsString( 'Use a published v7', $account['kits'][0]['summary'] );
		$this->assertSame( 2, $this->api->requests );
	}
	public static function discovery_configurations() {
		return array(
			array( array( 'status' => 'draft' ) ),
			array( array( 'licenseSelected' => 'free' ) ),
			array( array( 'technologySelected' => 'svg' ) ),
			array( array( 'version' => '6.x' ) ),
			array( array( 'subsetType' => 'CUSTOM' ) ),
			array( array( 'shimEnabled' => false ) ),
			array( array( 'familyStylesPaginated' => array( 'totalPageCount' => 2, 'familyStyles' => array() ) ) ),
		);
	}
	public function test_discovery_rejects_invalid_remote_identity_without_persisting_it() {
		$this->api->kits = array( array( 'token' => 'https://untrusted.invalid/style.css' ) );
		$this->assertSame( 'service', $this->pro->find_kits( 'SYNTHETIC-TOKEN' )->get_error_code() );
		$this->assertArrayNotHasKey( 'account', Better_Font_Awesome_Pro::state() );
		$this->assertStringNotContainsString( 'untrusted', wp_json_encode( $this->pro->account_status() ) );
	}

	public function test_discovery_encodes_empty_graphql_variables_as_an_object() {
		$account = $this->pro->find_kits( 'SYNTHETIC-TOKEN' );
		$this->assertNotWPError( $account, 'The service rejects variables:[] before processing a Kit query.' );
		$this->assertTrue( $account['authorized'] );
		$this->assertCount( 4, $account['kits'] );
		$this->assertSame( 2, $this->api->requests );
		$this->assertArrayNotHasKey( 'candidate', Better_Font_Awesome_Pro::state() );
	}

	public function test_full_style_counts_do_not_require_a_curated_only_subset() {
		$account = $this->pro->find_kits( 'SYNTHETIC-TOKEN' );
		$this->assertTrue( $account['kits'][0]['supported'] );
		$this->pro->start( 'KIT_ID', '', $account['id'] );
		$active = $this->complete();
		$this->assertCount( count( $this->api->rows ), $active['icons'] );
	}
	/** @dataProvider provider_choices */
	public function test_provider_save_preserves_saved_authorization_and_hidden_options( $provider, $enabled ) {
		$this->pro->start( 'KIT_ID', 'SYNTHETIC-TOKEN' );
		$active = $this->complete();
		$this->pro->find_kits( 'SYNTHETIC-TOKEN' );
		$credential = Better_Font_Awesome_Pro::state()['account']['credential'];
		$plugin = Better_Font_Awesome_Plugin::get_instance();
		$count = $this->api->requests;
		$options = $plugin->sanitize( array( 'provider_method' => $provider, 'include_v4_shim' => 1, 'remove_existing_fa' => 1, 'hide_admin_notices' => 1 ) );
		$this->assertSame( $enabled, Better_Font_Awesome_Pro::state()['enabled'] );
		$this->assertSame( $active, Better_Font_Awesome_Pro::state()['active'] );
		$this->assertSame( $credential, Better_Font_Awesome_Pro::state()['account']['credential'] );
		$this->assertSame( 1, $options['include_v4_shim'] );
		$this->assertSame( 1, $options['remove_existing_fa'] );
		$this->assertSame( 'bundled-local' === $provider ? 'bundled-local' : 'automatic', $options['asset_delivery'] );
		$this->assertArrayNotHasKey( 'provider_method', $options );
		$this->assertSame( $count, $this->api->requests );
	}
	public static function provider_choices() {
		return array( array( 'automatic', false ), array( 'bundled-local', false ), array( 'kit-css', true ) );
	}

}

<?php
/**
 * One hosted Classic Pro Kit: private acquisition and atomic activation.
 *
 * @package Better_Font_Awesome
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** BFA-owned Pro state, using WordPress options, cron and compare-and-swap leases. */
class Better_Font_Awesome_Pro {
	/** Separate, non-autoloaded state. Never expose this record to a client. */
	const OPTION = 'better_font_awesome_pro';
	/** Bounded continuation hook. */
	const HOOK = 'better_font_awesome_refresh_pro';
	/** Supported, unambiguous Classic identities. */
	const STYLES = array(
		'solid'   => 'fas',
		'regular' => 'far',
		'brands'  => 'fab',
		'light'   => 'fal',
		'thin'    => 'fat',
	);
	/**
	 * Actual initialization ownership, not URL equality.
	 *
	 * @var bool
	 */
	private $owner = false;
	/**
	 * Site where BFAL was initialized.
	 *
	 * @var int
	 */
	private $site;
	/**
	 * Effective library.
	 *
	 * @var object|null
	 */
	private $library;
	/**
	 * Snapshot paired with this request's immutable assets.
	 *
	 * @var array
	 */
	private $active = array();

	/**
	 * Picker rows formatted once from the compact, immutable snapshot.
	 *
	 * @var array|null
	 */
	private $picker = null;

	/** Capture site scope. */
	public function __construct() {
		$this->site = get_current_blog_id();
	}

	/**
	 * Read local state only.
	 *
	 * @return array Private state.
	 */
	public static function state() {
		// This non-autoloaded option is also the cross-request cancellation fence.
		wp_cache_delete( self::OPTION, 'options' );
		$absent = wp_cache_get( 'notoptions', 'options' );
		if ( is_array( $absent ) && isset( $absent[ self::OPTION ] ) ) {
			unset( $absent[ self::OPTION ] );
			wp_cache_set( 'notoptions', $absent, 'options' );
		}
		$state = get_option( self::OPTION, array() );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * Read local delivery independently of immutable BFAL state.
	 *
	 * @return bool Whether the site selected local Free.
	 */
	private static function local() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Read the committed mode so an older worker cannot bypass a concurrent local switch through its request cache.
		$options = maybe_unserialize( $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'better-font-awesome_options' ) ) );
		return is_array( $options ) && 'bundled-local' === ( $options['asset_delivery'] ?? '' );
	}

	/**
	 * Supply the active Kit before the existing first singleton call.
	 *
	 * @param array $args Existing initialization arguments.
	 * @return array Initialization arguments.
	 */
	public function initialization_args( $args ) {
		$state = self::state();
		if ( 'bundled-local' !== ( $args['asset_delivery'] ?? 'automatic' ) && ! self::local() && ! empty( $state['enabled'] ) && ! empty( $state['active']['icons'] ) ) {
			$this->active            = $state['active'];
			$args['asset_delivery']  = 'kit-css';
			$args['kit_css_url']     = 'https://kit.fontawesome.com/' . $this->active['kit'] . '.css';
			$args['release_channel'] = '7.x';
		}
		return $args;
	}

	/**
	 * Attach only after the first-call capture proves ownership.
	 *
	 * @param object $library Effective BFAL.
	 * @param bool   $owner   Whether BFA's initialization ran.
	 */
	public function boot( $library, $owner ) {
		$this->library = $library;
		$this->owner   = $owner;
		add_action( 'wp_ajax_bfa_pro', array( $this, 'ajax' ) );
		add_action( self::HOOK, array( $this, 'worker' ) );
		add_action( 'update_option_better-font-awesome_options', array( $this, 'settings_changed' ), 10, 2 );
		if ( $this->effective() ) {
			add_filter( 'bfa_icon_array', array( $this, 'icons' ), 20 );
		}
		$this->schedule();
	}

	/**
	 * This request must own both assets and metadata on the original site.
	 *
	 * @phpstan-impure
	 * @return bool Whether this controller can acquire Pro data.
	 */
	private function allowed() {
		return $this->owner && empty( self::state()['suspended'] ) && get_current_blog_id() === $this->site && ! self::local() &&
			is_object( $this->library ) && is_callable( array( $this->library, 'get_asset_delivery' ) ) &&
			in_array( $this->library->get_asset_delivery(), array( 'automatic', 'kit-css' ), true ) &&
			'7.x' === $this->library->get_release_channel() &&
			( empty( $this->active ) || ( 'kit-css' === $this->library->get_asset_delivery() && 'https://kit.fontawesome.com/' . $this->active['kit'] . '.css' === $this->library->get_stylesheet_url() ) );
	}

	/**
	 * Whether the captured catalog matches this request's effective assets.
	 *
	 * @return bool Effective hosted Pro.
	 */
	public function effective() {
		return $this->allowed() && ! empty( $this->active['icons'] ) &&
			'kit-css' === $this->library->get_asset_delivery() &&
			'https://kit.fontawesome.com/' . $this->active['kit'] . '.css' === $this->library->get_stylesheet_url();
	}

	/**
	 * Supply existing picker records without touching BFAL Free records.
	 *
	 * @param array $icons BFAL Free icons.
	 * @return array Effective picker data.
	 */
	public function icons( $icons ) {
		if ( ! $this->effective() ) {
			return $icons;
		}
		if ( null === $this->picker ) {
			$this->picker = array();
			foreach ( array_keys( $this->active['icons'] ) as $identity ) {
				list( $name, $style ) = explode( ':', $identity );
				$label                = $this->active['labels'][ $name ] ?? array();
				$this->picker[]       = array(
					'slug'        => $name,
					'style'       => $style,
					'title'       => ( $label[0] ?? ucwords( str_replace( '-', ' ', $name ) ) ) . ' (' . $style . ')',
					'base_class'  => self::STYLES[ $style ] . ' fa-' . $name,
					'searchTerms' => $label[1] ?? $name,
				);
			}
		}
		return $this->picker;
	}

	/**
	 * Safe status projection. No tokens, response bodies or candidate catalogs.
	 *
	 * @return array Public administrator status.
	 */
	public function status() {
		$s       = self::state();
		$c       = $s['candidate'] ?? array();
		$allowed = $this->allowed();
		return array(
			'connected'          => ! empty( $s['enabled'] ) && $this->effective() && ( $s['active'] ?? array() ) === $this->active,
			'activationRequired' => $allowed && ! empty( $s['enabled'] ) && ( $s['active']['generation'] ?? '' ) !== ( $this->active['generation'] ?? '' ),
			'effective'          => $this->effective() && ( $s['active'] ?? array() ) === $this->active,
			'allowed'            => $allowed,
			'pending'            => $allowed && ! empty( $c ) && empty( $c['stopped'] ),
			'operation'          => $c['id'] ?? '',
			'phase'              => $c['phase'] ?? '',
			'page'               => $c['page'] ?? 0,
			'pages'              => $c['pages'] ?? 0,
			'retryAt'            => max( $c['retry_at'] ?? 0, $c['busy_until'] ?? 0 ),
			'error'              => $allowed ? ( $s['error'] ?? '' ) : 'ownership',
			'kit'                => $s['active']['kit'] ?? '',
			'kitName'            => $s['active']['name'] ?? ( $s['active']['kit'] ?? '' ),
			'version'            => $s['active']['version'] ?? '',
			'styles'             => $s['active']['styles'] ?? array(),
			'updated'            => $s['active']['updated'] ?? 0,
		);
	}

	/**
	 * Discover account Kits without acquiring catalogs or changing the active Kit.
	 *
	 * @param string $token Optional replacement account token.
	 * @return array|WP_Error Safe Kit choices or a redacted failure.
	 */
	public function find_kits( $token = '' ) {
		if ( ! $this->allowed() ) {
			return $this->error( 'ownership' );
		}
		if ( '' !== $token && ( strlen( $token ) > 4096 || preg_match( '/\s/', $token ) ) ) {
			return $this->error( 'auth' );
		}
		$old    = self::state();
		$sealed = '' !== $token ? $this->secret( $token ) : ( $old['account']['credential'] ?? ( $old['active']['credential'] ?? $this->error( 'auth' ) ) );
		if ( is_wp_error( $sealed ) ) {
			return $sealed;
		}
		// Fence older responses and selections before doing any remote work.
		$locked              = $old;
		$locked['discovery'] = wp_generate_uuid4();
		if ( ! $this->swap( $old, $locked ) ) {
			return $this->error( 'changed' );
		}
		$auth = $this->authorize( $sealed );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}
		$access = $this->secret( $auth['access'], true );
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		// Account.kits is an unpaginated list. Only configuration/counts, never icons.
		$query = 'query{me{kits{token name ' . $this->meta_fields() . '}}}';
		$data  = $this->request( $access, $query );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$rows = $data['me']['kits'] ?? null;
		if ( ! is_array( $rows ) || array_values( $rows ) !== $rows ) {
			return $this->error( 'service' );
		}
		$kits = array();
		foreach ( $rows as $row ) {
			$id = $row['token'] ?? null;
			if ( ! is_string( $id ) || ! preg_match( '/\A[A-Za-z0-9_-]{1,100}\z/', $id ) || isset( $kits[ $id ] ) ) {
				return $this->error( 'service' );
			}
			$meta        = $this->validate_kit( $row );
			$kits[ $id ] = array(
				'id'        => $id,
				'name'      => is_string( $row['name'] ?? null ) ? sanitize_text_field( $row['name'] ) : '',
				'supported' => ! is_wp_error( $meta ),
				'details'   => is_wp_error( $meta ) ? array() : array(
					'version' => $meta['version'],
					'styles'  => array_keys( $meta['counts'] ),
				),
				'summary'   => is_wp_error( $meta ) ? sprintf(
					/* translators: 1: selected version, 2: Kit license, 3: rendering technology, 4: supported configuration requirements. */
					__( 'Configuration: %1$s, %2$s, %3$s. %4$s', 'better-font-awesome' ),
					is_string( $row['version'] ?? null ) ? sanitize_text_field( $row['version'] ) : '?',
					is_string( $row['licenseSelected'] ?? null ) ? sanitize_text_field( $row['licenseSelected'] ) : '?',
					is_string( $row['technologySelected'] ?? null ) ? sanitize_text_field( $row['technologySelected'] ) : '?',
					self::message( 'unsupported' )
				) : sprintf(
					/* translators: 1: Font Awesome version, 2: supported Classic styles. */
					__( 'Pro %1$s, Web Fonts, By Style, compatibility enabled. Classic styles: %2$s.', 'better-font-awesome' ),
					$meta['version'],
					implode( ', ', array_keys( $meta['counts'] ) )
				),
			);
		}
		$next            = $locked;
		$next['account'] = array(
			'id'         => $locked['discovery'],
			'credential' => $sealed,
			'kits'       => $kits,
		);
		if ( ! $this->allowed() || ! $this->swap( $locked, $next ) ) {
			return $this->error( 'changed' );
		}
		return $this->account_status();
	}

	/**
	 * Local, explicit projection of account authorization, separate from activation.
	 *
	 * @return array Safe discovery generation and choices, never saved tokens.
	 */
	public function account_status() {
		$s       = self::state();
		$a       = $s['account'] ?? array();
		$current = ! empty( $a['id'] ) && ( $s['discovery'] ?? '' ) === $a['id'];
		return array(
			'saved'      => ! empty( $a['credential'] ) || ! empty( $s['active']['credential'] ),
			'authorized' => $current,
			'id'         => $current ? $a['id'] : '',
			'kits'       => $current ? array_values( $a['kits'] ) : array(),
		);
	}

	/**
	 * Encrypt tokens with authenticated encryption and site-specific salt material.
	 *
	 * @param string $value Plaintext or sealed value.
	 * @param bool   $open  Decrypt instead of encrypt.
	 * @return string|WP_Error Sealed/plaintext value or safe error.
	 */
	private function secret( $value, $open = false ) {
		if ( ! function_exists( 'openssl_encrypt' ) || ! function_exists( 'openssl_decrypt' ) ) {
			return $this->error( 'storage' );
		}

		$auth_key  = defined( 'AUTH_KEY' ) ? constant( 'AUTH_KEY' ) : '';
		$auth_salt = defined( 'AUTH_SALT' ) ? constant( 'AUTH_SALT' ) : '';
		if ( ! is_string( $auth_key ) || ! is_string( $auth_salt ) || 32 > strlen( $auth_key ) || 32 > strlen( $auth_salt ) ) {
			return $this->error( 'storage' );
		}
		$key = hash( 'sha256', $auth_key . $auth_salt . ':bfa-pro:' . get_current_blog_id(), true );
		if ( $open ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decode authenticated ciphertext, never executable data.
			$raw = base64_decode( $value, true );
			if ( false === $raw || strlen( $raw ) < 29 ) {
				return $this->error( 'storage' );
			}
			$result = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
			return false === $result ? $this->error( 'storage' ) : $result;
		}
		$iv        = random_bytes( 12 );
		$tag       = '';
		$encrypted = openssl_encrypt( $value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		if ( false === $encrypted ) {
			return $this->error( 'storage' );
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Store authenticated ciphertext in a WordPress option.
		return base64_encode( $iv . $tag . $encrypted );
	}

	/**
	 * Start preparation without activating any partial data.
	 *
	 * @param string      $kit   Kit identifier.
	 * @param string      $token Account read-Kits token, used only server-side.
	 * @param string|null $selection Required discovery generation for browser connections.
	 * @return array|WP_Error Safe status or validation failure.
	 */
	public function start( $kit = '', $token = '', $selection = null ) {
		if ( ! $this->allowed() ) {
			return $this->error( 'ownership' );
		}
		$old = self::state();
		if ( null !== $selection && ( '' === $selection || ( $old['discovery'] ?? '' ) !== $selection ||
			( $old['account']['id'] ?? '' ) !== $selection || empty( $old['account']['kits'][ $kit ]['supported'] ) ) ) {
			return $this->error( 'selection' );
		}
		if ( '' === $kit ) {
			$kit = $old['active']['kit'] ?? '';
		}
		if ( ! preg_match( '/\A[A-Za-z0-9_-]{1,100}\z/', $kit ) ) {
			return $this->error( 'kit' );
		}
		if ( '' !== $token && ( strlen( $token ) > 4096 || preg_match( '/\s/', $token ) ) ) {
			return $this->error( 'auth' );
		}
		$sealed = '' !== $token ? $this->secret( $token ) : ( $old['active']['credential'] ?? $this->error( 'auth' ) );
		if ( null !== $selection ) {
			$sealed = $old['account']['credential'];
		}
		if ( is_wp_error( $sealed ) ) {
			return $sealed;
		}
		$next              = $old;
		$next['candidate'] = array(
			'id'         => wp_generate_uuid4(),
			'kit'        => $kit,
			'credential' => $sealed,
			'phase'      => 'auth',
			'page'       => 1,
			'icons'      => array(),
			'counts'     => array(),
			'created'    => time(),
			'failures'   => 0,
		);
		$next['error']     = '';
		if ( ! $this->swap( $old, $next ) ) {
			return $this->error( 'changed' );
		}
		$this->schedule();
		return $this->step( $next['candidate']['id'] );
	}

	/**
	 * Run one bounded stage, shared by AJAX and cron, with an ownership lease.
	 *
	 * @param string $id Candidate generation.
	 * @return array Safe status.
	 */
	public function step( $id ) {
		$old = self::state();
		$c   = $old['candidate'] ?? array();
		if ( ! $this->allowed() || empty( $c ) || $id !== $c['id'] || ! empty( $c['stopped'] ) ||
			( $c['busy_until'] ?? 0 ) > time() || ( $c['retry_at'] ?? 0 ) > time() ) {
			return $this->status();
		}
		$locked                            = $old;
		$locked['candidate']['busy_until'] = time() + Better_Font_Awesome_Metadata_Manager::LOCK_TTL;
		$locked['candidate']['lease']      = wp_generate_uuid4();
		if ( ! $this->swap( $old, $locked ) ) {
			return $this->status();
		}
		$result = time() - $c['created'] > DAY_IN_SECONDS ? $this->error( 'expired' ) : $this->acquire( $c );
		$next   = $locked;
		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();
			++$c['failures'];
			$c['stopped']      = 'service' !== $code || $c['failures'] >= 5;
			$c['retry_at']     = time() + ( $c['stopped'] ? DAY_IN_SECONDS : min( HOUR_IN_SECONDS, 30 * ( 2 ** $c['failures'] ) ) );
			$next['error']     = $code;
			$next['candidate'] = $c;
		} elseif ( isset( $result['complete'] ) ) {
			$next['active']    = $result['complete'];
			$next['enabled']   = true;
			$next['candidate'] = array();
			$next['error']     = '';
		} else {
			$next['candidate'] = $result;
			$next['error']     = '';
		}
		if ( $this->allowed() && $this->swap( $locked, $next ) ) {
			$this->schedule();
		}
		return $this->status();
	}

	/**
	 * Fixed vendor request; raw remote errors are deliberately discarded.
	 *
	 * @param string $token    Bearer token.
	 * @param string $query    Fixed query, or empty for token exchange.
	 * @param array  $variables Validated query variables.
	 * @return array|WP_Error Decoded response or safe failure.
	 */
	private function request( $token, $query = '', $variables = array() ) {
		$url      = 'https://api.fontawesome.com' . ( '' === $query ? '/token' : '/' );
		$response = wp_remote_post(
			$url,
			array(
				'timeout'             => 10,
				'redirection'         => 0,
				'sslverify'           => true,
				'limit_response_size' => 4 * MB_IN_BYTES,
				'headers'             => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'                => '' === $query ? '{}' : wp_json_encode(
					array(
						'query'     => $query,
						'variables' => (object) $variables,
					)
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return $this->error( 'service' );
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( in_array( $code, array( 401, 403 ), true ) ) {
			return $this->error( 'auth' );
		}
		if ( 200 !== $code ) {
			return $this->error( 'service' );
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return $this->error( 'service' );
		}
		if ( ! empty( $data['errors'] ) ) {
			// Classify without saving or returning vendor messages that may echo inputs.
			$text = wp_json_encode( $data['errors'] );
			return $this->error( preg_match( '/scope|authoriz|permission|token/i', $text ) ? 'auth' : 'service' );
		}
		return '' === $query ? $data : ( is_array( $data['data'] ?? null ) ? $data['data'] : $this->error( 'service' ) );
	}

	/**
	 * Selected Kit configuration and display name, rechecked before activation.
	 *
	 * @return string Fixed query.
	 */
	private function meta_query() {
		return 'query($kit:String!){me{kit(token:$kit){name ' . $this->meta_fields() . '}}}';
	}

	/**
	 * Configuration fields shared by discovery and selected-Kit validation.
	 *
	 * @return string Fixed projection, without icon catalogs or domains.
	 */
	private function meta_fields() {
		return 'status licenseSelected technologySelected version release{version} kitRevision subsetType shimEnabled familyStylesPaginated(pageSize:50){totalPageCount familyStyles{familyStyle{family style prefix} iconVariantsPaginated(pageSize:1){totalIconVariantCount}}}';
	}

	/**
	 * Validate selected configuration and entitlement-filtered style inventory.
	 *
	 * @param mixed $kit Vendor Kit metadata.
	 * @return array|WP_Error Canonical selected configuration.
	 */
	private function validate_kit( $kit ) {
		if ( ! is_array( $kit ) || 'published' !== ( $kit['status'] ?? '' ) || 'pro' !== ( $kit['licenseSelected'] ?? '' ) ||
			'webfonts' !== ( $kit['technologySelected'] ?? '' ) || 'AUTO' !== ( $kit['subsetType'] ?? '' ) ||
			true !== ( $kit['shimEnabled'] ?? false ) || ! is_string( $kit['version'] ?? null ) || ! preg_match( '/\A7\.(?:x|[0-9]+\.[0-9]+)\z/', $kit['version'] ) ||
			! is_string( $kit['release']['version'] ?? null ) || ! preg_match( '/\A7\.[0-9]+\.[0-9]+\z/', $kit['release']['version'] ) ||
			( ! is_string( $kit['kitRevision'] ?? null ) && ! is_int( $kit['kitRevision'] ?? null ) ) || '' === (string) $kit['kitRevision'] ||
			! is_array( $kit['familyStylesPaginated']['familyStyles'] ?? null ) || 1 !== ( $kit['familyStylesPaginated']['totalPageCount'] ?? 0 ) ) {
			return $this->error( 'unsupported' );
		}
		$counts = array();
		foreach ( $kit['familyStylesPaginated']['familyStyles'] ?? array() as $row ) {
			$f     = $row['familyStyle'] ?? array();
			$style = $f['style'] ?? '';
			$count = $row['iconVariantsPaginated']['totalIconVariantCount'] ?? 0;
			if ( ! is_string( $style ) || 'classic' !== ( $f['family'] ?? '' ) || ! isset( self::STYLES[ $style ] ) ||
				( $f['prefix'] ?? '' ) !== self::STYLES[ $style ] || isset( $counts[ $style ] ) || ! is_int( $count ) || 1 > $count ) {
				return $this->error( 'unsupported' );
			}
			$counts[ $style ] = $count;
		}
		if ( ! isset( $counts['solid'], $counts['regular'], $counts['brands'] ) || 40000 < array_sum( $counts ) ) {
			return $this->error( 'unsupported' );
		}
		ksort( $counts );
		return array(
			'revision' => (string) $kit['kitRevision'],
			'version'  => $kit['release']['version'],
			'selected' => $kit['version'],
			'counts'   => $counts,
		);
	}

	/**
	 * Exchange a sealed account token using the existing minimum read scopes.
	 *
	 * @param string $credential Encrypted account token.
	 * @return array|WP_Error Encrypted access token and expiry, or safe failure.
	 */
	private function authorize( $credential ) {
		$token = $this->secret( $credential, true );
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$data = $this->request( $token );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( ! is_string( $data['access_token'] ?? null ) || '' === $data['access_token'] || strlen( $data['access_token'] ) > 16384 || preg_match( '/\s/', $data['access_token'] ) || ! is_array( $data['scopes'] ?? null ) || count( array_filter( $data['scopes'], 'is_string' ) ) !== count( $data['scopes'] ) ||
			array_diff( array( 'public', 'kits_read' ), $data['scopes'] ) || ! is_numeric( $data['expires_in'] ?? null ) || (int) $data['expires_in'] < 120 ) {
			return $this->error( 'auth' );
		}
		$auth['access'] = $this->secret( $data['access_token'] );
		if ( is_wp_error( $auth['access'] ) ) {
			return $auth['access'];
		}
		$auth['expires'] = time() + min( DAY_IN_SECONDS, (int) $data['expires_in'] );
		return $auth;
	}

	/**
	 * Acquire exactly one remote stage. Never called by getters or rendering.
	 *
	 * @param array $c Candidate copy.
	 * @return array|WP_Error Next candidate or completed snapshot.
	 */
	private function acquire( $c ) {
		if ( 'auth' === $c['phase'] || ( $c['expires'] ?? 0 ) < time() + 60 ) {
			$auth = $this->authorize( $c['credential'] );
			if ( is_wp_error( $auth ) ) {
				return $auth;
			}
			$c          = array_merge( $c, $auth );
			$c['phase'] = 'auth' === $c['phase'] ? 'metadata' : $c['phase'];
			return $c;
		}
		$token = $this->secret( $c['access'], true );
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		if ( in_array( $c['phase'], array( 'metadata', 'verify' ), true ) ) {
			$data = $this->request( $token, $this->meta_query(), array( 'kit' => $c['kit'] ) );
			$meta = is_wp_error( $data ) ? $data : $this->validate_kit( $data['me']['kit'] ?? null );
			if ( is_wp_error( $meta ) ) {
				return $meta;
			}
			if ( 'metadata' === $c['phase'] ) {
				$c['meta']  = $meta;
				$c['phase'] = 'icons';
				return $c;
			}
			if ( $meta !== $c['meta'] ) {
				return $this->error( 'revision' );
			}
			return array(
				'complete' => array(
					'generation' => $c['id'],
					'kit'        => $c['kit'],
					'name'       => is_string( $data['me']['kit']['name'] ?? null ) ? sanitize_text_field( $data['me']['kit']['name'] ) : $c['kit'],
					'credential' => $c['credential'],
					'version'    => $meta['version'],
					'revision'   => $meta['revision'],
					'styles'     => array_keys( $meta['counts'] ),
					'icons'      => $c['icons'],
					'labels'     => $c['labels'],
					'updated'    => time(),
				),
			);
		}
		if ( 'icons' === $c['phase'] ) {
			$total = array_sum( $c['meta']['counts'] );
			$pages = (int) ceil( $total / 500 );
			// Keep each 500-variant page, but share transport across at most 16,000 variants.
			// This bounds query complexity and response size without 32 WordPress round trips.
			$last   = min( $pages, $c['page'] + 31 );
			$fields = '';
			for ( $page = $c['page']; $page <= $last; ++$page ) {
				$fields .= 'p' . $page . ':iconVariantsPaginated(page:' . $page . ',pageSize:500){page totalPageCount totalIconVariantCount iconVariants{name familyStyle{family style prefix}}}';
			}
			$query = 'query($kit:String!){me{kit(token:$kit){kitRevision release{version} ' . $fields . '}}}';
			$data  = $this->request( $token, $query, array( 'kit' => $c['kit'] ) );
			if ( is_wp_error( $data ) ) {
				return $data;
			}
			$kit = $data['me']['kit'] ?? array();
			if ( ( ! is_string( $kit['kitRevision'] ?? null ) && ! is_int( $kit['kitRevision'] ?? null ) ) || (string) $kit['kitRevision'] !== $c['meta']['revision'] || ( $kit['release']['version'] ?? '' ) !== $c['meta']['version'] ) {
				return $this->error( 'revision' );
			}
			for ( $page = $c['page']; $page <= $last; ++$page ) {
				$p = $kit[ 'p' . $page ] ?? array();
				if ( ( $p['page'] ?? 0 ) !== $page || ( $p['totalPageCount'] ?? 0 ) !== $pages ||
					( $p['totalIconVariantCount'] ?? 0 ) !== $total || ! is_array( $p['iconVariants'] ?? null ) ||
					count( $p['iconVariants'] ) !== min( 500, $total - 500 * ( $page - 1 ) ) ) {
					return $this->error( 'incomplete' );
				}
				foreach ( $p['iconVariants'] as $row ) {
					$name  = $row['name'] ?? '';
					$f     = $row['familyStyle'] ?? array();
					$style = $f['style'] ?? '';
					if ( ! is_string( $name ) || ! preg_match( '/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $name ) || 150 < strlen( $name ) ||
						! is_string( $style ) || 'classic' !== ( $f['family'] ?? '' ) || ! isset( $c['meta']['counts'][ $style ] ) ||
						( $f['prefix'] ?? '' ) !== self::STYLES[ $style ] || isset( $c['icons'][ $name . ':' . $style ] ) ) {
						return $this->error( 'incomplete' );
					}
					$c['icons'][ $name . ':' . $style ] = true;
					$c['counts'][ $style ]              = ( $c['counts'][ $style ] ?? 0 ) + 1;
				}
			}
			$c['pages'] = $pages;
			if ( $last === $pages ) {
				ksort( $c['counts'] );
				if ( $c['counts'] !== $c['meta']['counts'] ) {
					return $this->error( 'incomplete' );
				}
				$c['page']  = $last;
				$c['phase'] = 'free-coverage';
			} else {
				$c['page'] = $last + 1;
			}
			return $c;
		}
		$query = 'query($version:String!){release(version:$version){icons(license:"free"){id label aliases{names} familyStylesByLicense{free{family style}}}}}';
		$data  = $this->request( $token, $query, array( 'version' => $c['meta']['version'] ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$free = $data['release']['icons'] ?? null;
		if ( ! is_array( $free ) || empty( $free ) || 10000 < count( $free ) ) {
			return $this->error( 'incomplete' );
		}
		$c['labels'] = array();
		foreach ( $free as $row ) {
			if ( ! is_string( $row['id'] ?? null ) || ! is_string( $row['label'] ?? null ) || ! is_array( $row['familyStylesByLicense']['free'] ?? null ) || empty( $row['familyStylesByLicense']['free'] ) ) {
				return $this->error( 'incomplete' );
			}
			foreach ( $row['familyStylesByLicense']['free'] as $f ) {
				$style = $f['style'] ?? '';
				$key   = $row['id'] . ':' . ( is_string( $style ) ? $style : '' );
				if ( ! is_string( $style ) || 'classic' !== ( $f['family'] ?? '' ) || ! isset( $c['icons'][ $key ] ) ) {
					return $this->error( 'coverage' );
				}
				$aliases = $row['aliases']['names'] ?? array();
				if ( ! is_array( $aliases ) || count( array_filter( $aliases, 'is_string' ) ) !== count( $aliases ) ) {
					return $this->error( 'incomplete' );
				}
				$c['labels'][ $row['id'] ] = array( sanitize_text_field( $row['label'] ), sanitize_text_field( implode( ' ', array_merge( array( $row['id'] ), $aliases ) ) ) );
			}
		}
		foreach ( $this->library->get_release_icons() as $icon ) {
			foreach ( $icon['styles'] as $style ) {
				if ( ! isset( $c['icons'][ $icon['id'] . ':' . $style ] ) ) {
					return $this->error( 'coverage' );
				}
			}
		}
		$c['phase'] = 'verify';
		return $c;
	}

	/**
	 * Schedule one continuation or daily refresh; never acquire metadata here.
	 */
	public function schedule() {
		if ( ! $this->allowed() ) {
			return;
		}
		$s = self::state();
		$c = $s['candidate'] ?? array();
		if ( ( ! empty( $c['stopped'] ) && ( 'service' !== ( $s['error'] ?? '' ) || empty( $s['enabled'] ) || empty( $s['active'] ) ) ) || ( empty( $c ) && ( empty( $s['enabled'] ) || empty( $s['active'] ) ) ) ) {
			return;
		}
		$id   = ! empty( $c['stopped'] ) ? 'refresh-' . $s['active']['generation'] : ( $c['id'] ?? ( 'refresh-' . $s['active']['generation'] ) );
		$at   = ! empty( $c ) ? max( time() + 5, $c['retry_at'] ?? 0, $c['busy_until'] ?? 0 ) : max( time() + 5, $s['active']['updated'] + DAY_IN_SECONDS );
		$args = array( $id );
		if ( ! wp_next_scheduled( self::HOOK, $args ) ) {
			wp_schedule_single_event( $at, self::HOOK, $args );
			// A cancellation concurrent with event publication must win.
			if ( self::state() !== $s || ! $this->allowed() ) {
				wp_clear_scheduled_hook( self::HOOK, $args );
			}
		}
	}

	/**
	 * Dispatch one existing WordPress scheduled event.
	 *
	 * @param string $id Generation of candidate or active snapshot.
	 */
	public function worker( $id ) {
		$s = self::state();
		if ( ! $this->allowed() ) {
			return;
		}
		if ( ! empty( $s['candidate']['stopped'] ) && ( 'service' !== ( $s['error'] ?? '' ) || time() < ( $s['candidate']['retry_at'] ?? 0 ) ) ) {
			return;
		}
		if ( ! empty( $s['candidate'] ) && empty( $s['candidate']['stopped'] ) ) {
			$this->step( $id );
		} elseif ( ! empty( $s['enabled'] ) && 'refresh-' . ( $s['active']['generation'] ?? '' ) === $id ) {
			$this->start();
		}
	}

	/**
	 * Pause or disconnect without changing saved content.
	 *
	 * @param bool $disconnect Remove credentials and catalog as well as pending work.
	 * @param bool $suspend Preserve the delivery choice for explicit reactivation.
	 * @return bool Whether cancellation was persisted.
	 */
	public static function cancel( $disconnect = false, $suspend = false ) {
		$controller = new self();
		for ( $attempt = 0; $attempt < 5; $attempt++ ) {
			$old  = self::state();
			$next = array(
				'enabled'   => false,
				'candidate' => array(),
				'error'     => '',
			);
			if ( $suspend ) {
				$next['suspended'] = true;
				$next['resume']    = ! empty( $old['enabled'] ) || ! empty( $old['resume'] );
			}
			if ( ! $disconnect && isset( $old['active'] ) ) {
				$next['active'] = $old['active'];
			}
			if ( ! $disconnect && isset( $old['account'] ) ) {
				$next['account'] = $old['account'];
			}
			if ( $old === $next || $controller->swap( $old, $next ) ) {
				wp_unschedule_hook( self::HOOK );
				return true;
			}
		}
		return false;
	}
	/** Restore only an explicitly suspended site's delivery choice, without HTTP. */
	public static function reactivate() {
		$old = self::state();
		if ( empty( $old['suspended'] ) ) {
			return;
		}
		$next            = $old;
		$next['enabled'] = ! empty( $old['resume'] ) && ! empty( $old['active'] );
		unset( $next['suspended'], $next['resume'] );
		$controller = new self();
		$controller->swap( $old, $next );
	}

	/**
	 * Local selection invalidates in-flight work, while retaining a saved connection.
	 *
	 * @param mixed $old Previous options.
	 * @param mixed $replacement New options.
	 */
	public function settings_changed( $old, $replacement ) {
		if ( is_array( $replacement ) && 'bundled-local' === ( $replacement['asset_delivery'] ?? '' ) && $old !== $replacement ) {
			self::cancel();
		}
	}

	/**
	 * Exact compare-and-swap, following the existing Free manager's ownership pattern.
	 *
	 * @param array $old Expected state.
	 * @param array $replacement Replacement state.
	 * @return bool Whether the replacement won.
	 */
	private function swap( $old, $replacement ) {
		global $wpdb;
		if ( array() === $old && false === get_option( self::OPTION, false ) ) {
			// add_option() can upsert after a concurrent insert; the initial lease must not.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Insert only if absent; never overwrite a concurrently created connection.
			$changed = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)", self::OPTION, maybe_serialize( $replacement ), 'no' ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- An atomic comparison prevents older workers from activating or resurrecting canceled work.
			$changed = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", maybe_serialize( $replacement ), self::OPTION, maybe_serialize( $old ) ) );
		}
		wp_cache_delete( self::OPTION, 'options' );
		return 1 === $changed;
	}

	/**
	 * Constant, translated errors never include credentials or remote response text.
	 *
	 * @param string $code Error category.
	 * @return WP_Error Safe error.
	 */
	private function error( $code ) {
		return new WP_Error( $code, self::message( $code ) );
	}

	/**
	 * Status text safe to include in a UI or report.
	 *
	 * @param string $code Error category.
	 * @return string Translated description.
	 */
	public static function message( $code ) {
		$messages = array(
			'ownership'   => __( 'Pro connection is unavailable. Use automatic Free first and ensure BFA owns Font Awesome 7 initialization.', 'better-font-awesome' ),
			'kit'         => __( 'Choose a kit from the current account list.', 'better-font-awesome' ),
			'selection'   => __( 'Kit selection expired or is unsupported. Refresh the kit list and choose a supported kit.', 'better-font-awesome' ),
			'auth'        => __( 'Authorization failed. Check your account token and Read Kits Data permission, then reconnect.', 'better-font-awesome' ),
			'storage'     => __( 'Secure token storage is unavailable. Check OpenSSL and the WordPress authentication salts, then reconnect.', 'better-font-awesome' ),
			'service'     => __( 'Font Awesome is temporarily unavailable. The working catalog is unchanged.', 'better-font-awesome' ),
			'unsupported' => __( 'Use a published v7 Pro By Style Web Fonts kit with compatibility and Classic Solid, Regular and Brands. Only Classic Light and Thin may also be selected.', 'better-font-awesome' ),
			'revision'    => __( 'The kit changed during preparation. The working catalog is unchanged. Refresh kit to try again.', 'better-font-awesome' ),
			'incomplete'  => __( 'The kit catalog was incomplete or inconsistent. The working catalog is unchanged.', 'better-font-awesome' ),
			'coverage'    => __( 'The kit is missing required Free icons. Check the included styles before reconnecting.', 'better-font-awesome' ),
			'expired'     => __( 'Preparation expired. Connect or Refresh kit to start again.', 'better-font-awesome' ),
			'changed'     => __( 'Another connection action won. Reload this page to see its status.', 'better-font-awesome' ),
		);
		return $messages[ $code ] ?? '';
	}

	/** Capability and nonce protected, fixed connection operations only. */
	public function ajax() {
		if ( ! current_user_can( 'manage_options' ) || false === check_ajax_referer( 'bfa-pro', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to change this connection.', 'better-font-awesome' ) ), 403 );
		}
		$operation = isset( $_POST['operation'] ) && is_string( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
		$result    = null;
		if ( 'find' === $operation ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- find_kits() validates opaque tokens before encryption; never transform a credential.
			$token  = isset( $_POST['token'] ) && is_string( $_POST['token'] ) ? wp_unslash( $_POST['token'] ) : '';
			$result = $this->find_kits( $token );
		} elseif ( 'connect' === $operation ) {
			$kit    = isset( $_POST['kit'] ) && is_string( $_POST['kit'] ) ? sanitize_text_field( wp_unslash( $_POST['kit'] ) ) : '';
			$id     = isset( $_POST['id'] ) && is_string( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
			$result = $this->start( $kit, '', $id );
		} elseif ( 'refresh' === $operation ) {
			$result = $this->start();
		} elseif ( 'step' === $operation ) {
			$id     = isset( $_POST['id'] ) && is_string( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
			$result = $this->step( $id );
		} elseif ( in_array( $operation, array( 'disconnect', 'pause' ), true ) ) {
			if ( ! self::cancel( 'disconnect' === $operation ) ) {
				wp_send_json_error( array( 'message' => self::message( 'changed' ) ), 409 );
			}
			if ( 'pause' === $operation ) {
				$options                   = get_option( 'better-font-awesome_options', array() );
				$options['asset_delivery'] = 'automatic';
				update_option( 'better-font-awesome_options', $options );
			}
		}
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}
		$status            = $this->status();
		$status['message'] = self::message( $status['error'] );
		if ( in_array( $operation, array( 'find', 'status' ), true ) ) {
			$status['account'] = $this->account_status();
		}
		wp_send_json_success( $status );
	}
}

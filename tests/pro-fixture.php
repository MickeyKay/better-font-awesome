<?php
/** Synthetic Kit service shared by deterministic PHP and browser tests. No Pro assets. */
class Better_Font_Awesome_Pro_Fixture {
	public $fault    = '';
	public $requests = 0;
	public $queries = array();
	public $kits = null;
	public $revision = 'synthetic-r1';
	public $rows     = array();
	public $free     = array();
	public function __construct() {
		$record     = json_decode( file_get_contents( dirname( __DIR__ ) . '/vendor/mickey-kay/better-font-awesome-library/inc/font-awesome-7-fallback/metadata.json' ), true );
		$this->free = $record['release']['icons'];
		foreach ( $this->free as $icon ) {
			foreach ( $icon['familyStylesByLicense']['free'] as $style ) {
				$this->rows[] = array(
					'name'        => $icon['id'],
					'familyStyle' => array_merge( $style, array( 'prefix' => Better_Font_Awesome_Pro::STYLES[ $style['style'] ] ) ),
				);
			}
		}
		foreach ( array( 'solid', 'regular', 'light', 'thin' ) as $style ) {
			$this->rows[] = array(
				'name'        => 'pro-fixture',
				'familyStyle' => array(
					'family' => 'classic',
					'style'  => $style,
					'prefix' => Better_Font_Awesome_Pro::STYLES[ $style ],
				),
			);
		}
	}
	public function expand() {
		$names  = array();
		$brands = array();
		foreach ( $this->rows as $row ) {
			if ( 'brands' === $row['familyStyle']['style'] ) {
				$brands[] = $row; } else {
				$names[ $row['name'] ] = true; }
		}
		for ( $i = count( $names ); $i < 3781; $i++ ) {
			$names[ 'synthetic-' . $i ] = true; }
		$this->rows = $brands;
		foreach ( array_keys( $names ) as $name ) {
			foreach ( array( 'solid', 'regular', 'light', 'thin' ) as $style ) {
				$this->rows[] = array(
					'name'        => (string) $name,
					'familyStyle' => array(
						'family' => 'classic',
						'style'  => $style,
						'prefix' => Better_Font_Awesome_Pro::STYLES[ $style ],
					),
				);
			}
		}
	}
	public function response( $preempt, $args, $url ) {
		if ( ! preg_match( '#^https://api\.fontawesome\.com(?:/|$)#', $url ) ) {
			return $preempt; }
		++$this->requests;
		if ( 'service' === $this->fault ) {
			return new WP_Error( 'remote', 'DO-NOT-EXPOSE-REMOTE-SECRET' ); }
		if ( 'auth' === $this->fault ) {
			return array(
				'response' => array( 'code' => 401 ),
				'body'     => 'DO-NOT-EXPOSE-REMOTE-SECRET',
			); }
		if ( '/token' === substr( $url, -6 ) ) {
			$body = array(
				'access_token' => 'empty-token' === $this->fault ? '' : 'SYNTHETIC-ACCESS-NOT-A-CREDENTIAL',
				'expires_in'   => 3600,
				'scopes'       => 'scope' === $this->fault ? array( 'public' ) : array( 'public', 'kits_read' ),
			);
		} else {
			// The real GraphQL endpoint rejects an empty JSON array for variables.
			$envelope = json_decode( $args['body'] );
			if ( isset( $envelope->variables ) && ! is_object( $envelope->variables ) ) {
				return array(
					'response' => array( 'code' => 400 ),
					'body' => wp_json_encode( array( 'errors' => array( array( 'message' => 'Variables must be a map.' ) ) ) ),
				);
			}
			$request = json_decode( $args['body'], true );
			$q       = $request['query'];
			$this->queries[] = $q;
			$vars    = $request['variables'];
			$counts  = array_count_values( array_column( array_column( $this->rows, 'familyStyle' ), 'style' ) );
			$meta    = array(
				'name'              => 'BFA staging',
				'kitRevision'        => $this->revision,
				'release'            => array( 'version' => '7.3.1' ),
				'version'            => '7.x',
				'status'             => 'published',
				'technologySelected' => 'webfonts',
				'licenseSelected'    => 'pro',
				'subsetType'         => 'unsupported' === $this->fault ? 'CUSTOM' : 'AUTO',
				'shimEnabled'        => true,
			);
			if ( false !== strpos( $q, 'familyStylesPaginated' ) ) {
				$styles = array();
				foreach ( $counts as $style => $count ) {
					$styles[] = array(
						'familyStyle' => array(
							'family' => 'classic',
							'style'  => $style,
							'prefix' => Better_Font_Awesome_Pro::STYLES[ $style ],
						),
						'only'        => array( 'totalIconVariantCount' => $count ),
					); }
				$meta['familyStylesPaginated'] = array(
					'totalPageCount' => 1,
					'familyStyles'   => $styles,
				);
				$body                          = array( 'data' => array( 'me' => array( 'kit' => $meta ) ) );
				if ( false !== strpos( $q, 'kits{' ) ) {
					$kits = $this->kits ?? array(
						array( 'token' => 'KIT_ID', 'name' => 'BFA staging' ),
						array( 'token' => 'SECOND', 'name' => 'BFA staging' ),
						array( 'token' => 'SVG_KIT', 'name' => 'SVG Kit', 'technologySelected' => 'svg' ),
						array( 'token' => 'UNNAMED', 'name' => '' ),
					);
					$body = array( 'data' => array( 'me' => array( 'kits' => 'empty-account' === $this->fault ? array() : array_map( static function ( $kit ) use ( $meta ) { return array_merge( $meta, $kit ); }, $kits ) ) ) );
				}

			} elseif ( false !== strpos( $q, 'iconVariantsPaginated' ) ) {
				$rows = array_slice( $this->rows, ( $vars['page'] - 1 ) * 500, 500 );
				if ( 'partial' === $this->fault ) {
					array_pop( $rows ); }
				if ( 'duplicate' === $this->fault ) {
					$rows[1] = $rows[0]; }
				$meta['iconVariantsPaginated'] = array(
					'page'                  => $vars['page'],
					'totalPageCount'        => (int) ceil( count( $this->rows ) / 500 ),
					'totalIconVariantCount' => count( $this->rows ),
					'iconVariants'          => $rows,
				);
				$body                          = array( 'data' => array( 'me' => array( 'kit' => $meta ) ) );
			} else {
				$free = $this->free;
				if ( 'coverage' === $this->fault ) {
					$free[0]['id'] = 'absent-required-free-icon'; }
				$body = array( 'data' => array( 'release' => array( 'icons' => $free ) ) );
			}
		}
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode( $body ),
		);
	}
}

<?php

class Better_Font_Awesome_Release_Data_Validator {
	public const SCHEMA_VERSION = 1;

	public const RELEASE_CHANNEL = '5.x';

	public const EDITION = 'free';

	public const MAX_RESPONSE_BYTES = 2097152;

	/**
	 * @param mixed  $release
	 * @param string $source
	 * @return array{valid: bool, record: array<string, mixed>|null, error: array{code: string, message: string}|null}
	 */
	public static function validate_release( $release, $source = 'unknown' ) {}

	/**
	 * @param mixed $record
	 * @return array{valid: bool, record: array<string, mixed>|null, error: array{code: string, message: string}|null}
	 */
	public static function validate_record( $record ) {}
}

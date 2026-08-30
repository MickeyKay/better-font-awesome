#!/bin/sh

set -eu

release_root=${1:-svn/trunk}
bfal_root="$release_root/vendor/mickey-kay/better-font-awesome-library"
installed_bfal_root="vendor/mickey-kay/better-font-awesome-library"
expected_version="2.1.0-rc.1"
expected_reference="a05508043ea885fa611f559ab59cff73161b37d2"

php -r '
$lock = json_decode( file_get_contents( "composer.lock" ), true );
$packages = array_filter( $lock["packages"], static function ( $package ) {
	return "mickey-kay/better-font-awesome-library" === $package["name"];
} );
$package = reset( $packages );
if ( ! $package || "2.1.0-rc.1" !== $package["version"] || "a05508043ea885fa611f559ab59cff73161b37d2" !== $package["source"]["reference"] || "a05508043ea885fa611f559ab59cff73161b37d2" !== $package["dist"]["reference"] ) {
	fwrite( STDERR, "composer.lock does not contain the exact public BFAL release candidate.\n" );
	exit( 1 );
}
'

php -r '
require "vendor/autoload.php";
$package = "mickey-kay/better-font-awesome-library";
if ( "2.1.0-rc.1" !== Composer\InstalledVersions::getPrettyVersion( $package ) || "a05508043ea885fa611f559ab59cff73161b37d2" !== Composer\InstalledVersions::getReference( $package ) ) {
	fwrite( STDERR, "The installed BFAL package does not match the exact public release candidate.\n" );
	exit( 1 );
}
'

required_files='better-font-awesome-library.php
composer.json
css/admin-styles.css
css/admin-styles.min.css
inc/class-bfa-release-data-validator.php
inc/fallback-release-data.json
inc/fallback-release-data.sha256
js/admin.js
js/admin.min.js
lib/fontawesome-iconpicker/LICENSE
lib/fontawesome-iconpicker/dist/css/fontawesome-iconpicker.css
lib/fontawesome-iconpicker/dist/css/fontawesome-iconpicker.min.css
lib/fontawesome-iconpicker/dist/js/fontawesome-iconpicker.js
lib/fontawesome-iconpicker/dist/js/fontawesome-iconpicker.min.js
LICENSE
THIRD-PARTY-NOTICES.md'

printf '%s\n' "$required_files" | while IFS= read -r file; do
	test -f "$bfal_root/$file"
	cmp "$installed_bfal_root/$file" "$bfal_root/$file"
done

grep -Fq "const VERSION = '$expected_version';" "$bfal_root/better-font-awesome-library.php"
(
	cd "$bfal_root/inc"
	shasum -a 256 -c fallback-release-data.sha256
)

test -f "$release_root/better-font-awesome.php"
test -f "$release_root/composer.json"
test ! -e "$release_root/.phpunit.result.cache"
test ! -d "$release_root/tests"
test ! -d "$release_root/.codex"
test ! -d "$release_root/.conductor"
test ! -d "$release_root/.context"
test ! -d "$release_root/.github"
test ! -d "$release_root/docs"
test ! -d "$release_root/node_modules"
test ! -d "$release_root/vendor/phpunit"
test ! -e "$bfal_root/CHANGELOG.md"
test ! -e "$bfal_root/Gruntfile.js"
test ! -e "$bfal_root/package-lock.json"
test ! -e "$bfal_root/package.json"
test ! -e "$bfal_root/README.md"
test ! -d "$bfal_root/tests"
test ! -e "$release_root/AGENTS.md"
test ! -e "$release_root/CONTRIBUTING.md"
test ! -e "$release_root/README.md"
test ! -e "$release_root/package-lock.json"
test ! -e "$release_root/phpcs.xml.dist"
test ! -e "$release_root/phpstan.neon.dist"
test -z "$(find "$release_root" -type f -name 'phpunit*.xml*' -print -quit)"

printf 'Packaged BFAL %s at %s passed the production release-tree audit.\n' "$expected_version" "$expected_reference"

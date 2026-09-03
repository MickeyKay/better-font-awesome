#!/bin/sh

set -eu

release_root=${1:-svn/trunk}
bfal_root="$release_root/vendor/mickey-kay/better-font-awesome-library"
installed_bfal_root="vendor/mickey-kay/better-font-awesome-library"
expected_version="3.0.0"
expected_reference="4632345f50efb0e7942b00576c14901e64370124"

php -r '
$lock = json_decode( file_get_contents( "composer.lock" ), true );
$packages = array_filter( $lock["packages"], static function ( $package ) {
	return "mickey-kay/better-font-awesome-library" === $package["name"];
} );
$package = reset( $packages );
if ( ! $package || "3.0.0" !== $package["version"] || "4632345f50efb0e7942b00576c14901e64370124" !== $package["source"]["reference"] || "4632345f50efb0e7942b00576c14901e64370124" !== $package["dist"]["reference"] ) {
	fwrite( STDERR, "composer.lock does not contain the exact public stable BFAL release.\n" );
	exit( 1 );
}
'

php -r '
require "vendor/autoload.php";
$package = "mickey-kay/better-font-awesome-library";
if ( "3.0.0" !== Composer\InstalledVersions::getPrettyVersion( $package ) || "4632345f50efb0e7942b00576c14901e64370124" !== Composer\InstalledVersions::getReference( $package ) ) {
	fwrite( STDERR, "The installed BFAL package does not match the exact public stable release.\n" );
	exit( 1 );
}
'

required_files='CHANGELOG.md
LICENSE
README.md
THIRD-PARTY-NOTICES.md
better-font-awesome-library.php
composer.json
css/admin-styles.css
css/admin-styles.min.css
inc/class-bfa-release-channel.php
inc/class-bfa-release-data-v2-adapter.php
inc/class-bfa-release-data-v2-refresher.php
inc/class-bfa-release-data-v2-validator.php
inc/class-bfa-release-data-validator.php
inc/fallback-release-data.json
inc/fallback-release-data.sha256
inc/font-awesome-7-fallback/ATTRIBUTION.md
inc/font-awesome-7-fallback/LICENSE.txt
inc/font-awesome-7-fallback/css/all.min.css
inc/font-awesome-7-fallback/css/v4-font-face.min.css
inc/font-awesome-7-fallback/css/v4-shims.min.css
inc/font-awesome-7-fallback/css/v5-font-face.min.css
inc/font-awesome-7-fallback/metadata.json
inc/font-awesome-7-fallback/provenance.json
inc/font-awesome-7-fallback/provenance.sha256
inc/font-awesome-7-fallback/webfonts/fa-brands-400.woff2
inc/font-awesome-7-fallback/webfonts/fa-regular-400.woff2
inc/font-awesome-7-fallback/webfonts/fa-solid-900.woff2
inc/font-awesome-7-fallback/webfonts/fa-v4compatibility.woff2
js/admin.js
js/admin.min.js
lib/fontawesome-iconpicker/LICENSE
lib/fontawesome-iconpicker/dist/css/fontawesome-iconpicker.css
lib/fontawesome-iconpicker/dist/css/fontawesome-iconpicker.min.css
lib/fontawesome-iconpicker/dist/js/fontawesome-iconpicker.js
lib/fontawesome-iconpicker/dist/js/fontawesome-iconpicker.min.js'

printf '%s\n' "$required_files" | while IFS= read -r file; do
	test -f "$bfal_root/$file"
	cmp "$installed_bfal_root/$file" "$bfal_root/$file"
done

actual_files=$( cd "$bfal_root" && find . -type f | sed 's#^\./##' | sort )
test "$( printf '%s\n' "$required_files" | sort )" = "$actual_files"
test "$( printf '%s\n' "$actual_files" | wc -l | tr -d ' ' )" -eq 35

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
test ! -e "$bfal_root/Gruntfile.js"
test ! -e "$bfal_root/package-lock.json"
test ! -e "$bfal_root/package.json"
test ! -d "$bfal_root/tests"
test ! -e "$release_root/AGENTS.md"
test ! -e "$release_root/CONTRIBUTING.md"
test ! -e "$release_root/README.md"
test ! -e "$release_root/package-lock.json"
test ! -e "$release_root/phpcs.xml.dist"
test ! -e "$release_root/phpstan.neon.dist"
test ! -e "$release_root/playwright-report"
test ! -e "$release_root/test-results"
test -z "$(find "$release_root" -type f -name 'phpunit*.xml*' -print -quit)"

printf 'Packaged BFAL %s at %s passed the production release-tree audit.\n' "$expected_version" "$expected_reference"

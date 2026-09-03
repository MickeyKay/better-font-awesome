#!/bin/sh

set -eu

plugin_directory=$(basename "$PWD")

sh bin/wp-env-run.sh wp plugin activate "$plugin_directory" bfa-editor-fixture
if sh bin/wp-env-run.sh wp theme is-installed twentytwentyfive; then
	test_theme=twentytwentyfive
else
	test_theme=twentytwentyfour
fi
exec sh bin/wp-env-run.sh wp theme activate "$test_theme"

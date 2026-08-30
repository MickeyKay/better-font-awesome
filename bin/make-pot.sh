#!/bin/sh

set -eu

library_pot=/tmp/better-font-awesome-library.pot

sh bin/wp-env-run.sh wp -- i18n make-pot \
	vendor/mickey-kay/better-font-awesome-library \
	"$library_pot" \
	--domain=better-font-awesome \
	--package-name="Better Font Awesome Library"

sh bin/wp-env-run.sh wp -- i18n make-pot \
	. \
	languages/better-font-awesome.pot \
	--slug=better-font-awesome \
	--domain=better-font-awesome \
	--exclude=.codex,.context,.conductor,.github,node_modules,svn,tests \
	--merge="$library_pot" \
	--headers='{"Report-Msgid-Bugs-To":"https://wordpress.org/support/plugin/better-font-awesome/"}'

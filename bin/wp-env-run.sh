#!/bin/sh

set -eu

plugin_directory=$(basename "$PWD")

exec npx wp-env run cli \
	--env-cwd="wp-content/plugins/$plugin_directory" \
	"$@"

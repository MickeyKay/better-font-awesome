#!/bin/sh

set -eu

plugin_directory=$(basename "$PWD")

sh bin/wp-env-run.sh wp plugin activate "$plugin_directory"
exec sh bin/wp-env-run.sh wp theme activate twentytwentyfive

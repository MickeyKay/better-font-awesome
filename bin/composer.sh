#!/bin/sh

set -eu

if command -v composer >/dev/null 2>&1; then
	exec composer "$@"
fi

if ! command -v docker >/dev/null 2>&1; then
	echo "Composer or Docker is required." >&2
	exit 1
fi

exec docker run --rm \
	--user "$(id -u):$(id -g)" \
	--volume "$PWD:/app" \
	--workdir /app \
	composer:2 "$@"

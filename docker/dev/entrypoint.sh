#!/bin/sh
set -eu

# Docker creates the bind mount's parent as root before Nextcloud initializes.
# Only adjust the parent directory; never recurse into the mounted repository.
mkdir -p /var/www/html/custom_apps
chown www-data:www-data /var/www/html/custom_apps

exec /entrypoint.sh "$@"

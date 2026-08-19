#!/bin/sh
set -e

# A Railway volume mounted at /app/storage/app/public starts empty, and the
# framework directories are not in the image (storage/ is gitignored), so
# recreate the tree on every boot.
mkdir -p \
	storage/app/public \
	storage/framework/cache/data \
	storage/framework/sessions \
	storage/framework/views \
	storage/logs \
	bootstrap/cache

chmod -R 775 storage bootstrap/cache

# public/storage lives in the ephemeral layer, so ensure the symlink into the
# volume exists without treating an already-correct link as a boot error.
if [ ! -e public/storage ]; then
	php artisan storage:link --force
fi

# Cache at runtime, not build time: Railway only injects environment
# variables when the container runs, so a build-time cache would bake in
# the sqlite fallback and an empty APP_KEY.
php artisan config:cache
php artisan route:cache

# Honour a custom start command (e.g. the queue worker service).
if [ "$#" -gt 0 ]; then
	exec "$@"
fi

exec frankenphp run --config /etc/caddy/Caddyfile

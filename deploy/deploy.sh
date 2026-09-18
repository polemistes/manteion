#!/bin/sh
# Full release on the shared host: pull, install PHP dependencies, run
# migrations, rebuild the framework caches and sync the web root.
#
# Assets are committed, so there is no build step here. Run from anywhere:
#
#   sh deploy/deploy.sh
#
# Pass --no-pull to release what is already checked out.

set -eu

APP_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
cd "$APP_DIR"

if [ "${1:-}" != "--no-pull" ]; then
    git pull --ff-only
fi

if command -v composer >/dev/null 2>&1; then
    composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
else
    echo "composer not found; skipping dependency install (vendor/ must already be current)." >&2
fi

php artisan down --retry=30 || true

php artisan migrate --force --no-interaction
php artisan optimize:clear
php artisan optimize

php artisan up

sh "$APP_DIR/deploy/sync-webroot.sh"

# Notifications, mail and link previews are queued. Without a worker
# process the deferred driver is the one that actually runs them.
if ! grep -q '^QUEUE_CONNECTION=deferred' "$APP_DIR/.env"; then
    echo "WARNING: QUEUE_CONNECTION is not 'deferred' in .env — queued notifications and mail will not be sent." >&2
fi

echo "Released $(git -C "$APP_DIR" rev-parse --short HEAD)"

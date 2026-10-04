#!/bin/sh
# Full release: pull, install PHP dependencies, run migrations, rebuild the
# framework caches, and restart the queue worker and the Reverb server.
#
# Assets are committed, so there is no build step here. Run from anywhere:
#
#   sh deploy/deploy.sh
#
# Pass --no-pull to release what is already checked out.

set -eu

APP_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
cd "$APP_DIR"

# The example Reverb credentials are public: with them anyone could sign
# private-channel subscriptions and read every user's live updates.
if grep -Eq '^REVERB_APP_(KEY|SECRET)=manteion-local' "$APP_DIR/.env"; then
    echo "ERROR: .env still has the example REVERB_APP_KEY / REVERB_APP_SECRET. Set your own (e.g. openssl rand -hex 20) first." >&2
    exit 1
fi

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

# The long-running processes pick up the new code: the queue worker (push
# notifications, mail, link previews) and the Reverb WebSocket server
# (live messages, badges and activity). Each is kept running by the
# server's process manager (systemd or Supervisor), which restarts it.
php artisan queue:restart
php artisan reverb:restart

# A host whose web root can't point at public/ gets a copy of it instead.
if [ -d "$HOME/www" ]; then
    sh "$APP_DIR/deploy/sync-webroot.sh"
fi

# Notifications and mail wait in the queue for a worker
# (`php artisan queue:work`); with sync they would slow every request,
# with deferred they would be lost on a crash.
if grep -Eq '^QUEUE_CONNECTION=(sync|deferred)' "$APP_DIR/.env"; then
    echo "WARNING: QUEUE_CONNECTION should be 'database' (or redis) with a running queue worker." >&2
fi

echo "Released $(git -C "$APP_DIR" rev-parse --short HEAD)"

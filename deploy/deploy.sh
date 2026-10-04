#!/usr/bin/env bash
# Build a release and make it the current one. Runs on the server as the
# manteion user, from inside the release that push.sh has just copied over:
# links the shared settings and storage, installs the PHP dependencies,
# backs up the database, migrates it, builds Laravel's caches, then points
# /home/manteion/app at this release. Reloading PHP and restarting the
# services is push.sh's job.
set -euo pipefail

BASE="${MANTEION_BASE:-/home/manteion}"
RELEASE="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="$BASE/manteion.env"

if [ ! -f "$ENV_FILE" ]; then
  echo "No settings file at $ENV_FILE: copy deploy/manteion.env.example there and fill it in." >&2
  exit 1
fi

# The example Reverb credentials are public: with them anyone could sign
# private-channel subscriptions and read every user's live updates.
if grep -Eq '^REVERB_APP_(KEY|SECRET)=("?manteion-local[^"]*"?)?$' "$ENV_FILE"; then
  echo "$ENV_FILE has no REVERB_APP_KEY / REVERB_APP_SECRET of its own (see deploy/manteion.env.example)." >&2
  exit 1
fi

# Notifications and mail wait in the queue for the worker; with sync they
# would slow every request, with deferred they would be lost on a crash.
if grep -Eq '^QUEUE_CONNECTION=(sync|deferred)' "$ENV_FILE"; then
  echo "WARNING: QUEUE_CONNECTION should be 'database' (with the manteion-queue service running)." >&2
fi

DB_FILE="$(sed -n 's/^DB_DATABASE=//p' "$ENV_FILE" | tr -d '"')"
DB_FILE="${DB_FILE:-$BASE/database/manteion.sqlite}"

cd "$RELEASE"

# Every release shares one settings file and one storage folder (uploads,
# logs, Laravel's own caches), kept outside the releases.
ln -sfn "$ENV_FILE" .env
rm -rf storage
ln -sfn "$BASE/storage" storage

composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress

# public/storage serves the uploads (avatars, event and leaflet pictures).
php artisan storage:link --force

# While the database is migrated, both releases show the maintenance page.
php artisan down --retry=15
trap 'echo "The deploy stopped with an error, and the site is in maintenance mode. Fix it and deploy again, or bring the old release back up: sudo -u manteion php $BASE/app/artisan up" >&2' ERR

# A copy of the database before every migration (the ten newest are kept).
if [ -s "$DB_FILE" ]; then
  mkdir -p "$BASE/backups/before-deploy"
  sqlite3 "$DB_FILE" ".backup '$BASE/backups/before-deploy/$(basename "$RELEASE").sqlite'"
  find "$BASE/backups/before-deploy" -maxdepth 1 -name '*.sqlite' | sort | head -n -10 | xargs -r rm --
fi

php artisan migrate --force --no-interaction
php artisan optimize

# Switch in one step, so that a request never sees a half-made link.
ln -sfn "$RELEASE" "$BASE/app.new"
mv -T "$BASE/app.new" "$BASE/app"

trap - ERR
php artisan up

# Keep the five newest releases (their names begin with the time of the deploy).
find "$BASE/releases" -mindepth 1 -maxdepth 1 -type d | sort | head -n -5 | while read -r old; do
  [ "$old" != "$RELEASE" ] && rm -rf "$old"
done
echo "Release $(basename "$RELEASE") is current."

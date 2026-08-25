#!/bin/sh
# Syncs this app's public/ folder into the fixed web root ($HOME/www on
# domene.shop), since the web root can't be pointed at public/ directly here.
#
# Run this after every `git pull` (and, on hosts with Node available,
# after `npm run build`; on this host assets are committed instead).
#
# Usage: sh deploy/sync-webroot.sh

set -eu

APP_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
WWW_DIR="$HOME/www"

if [ ! -d "$WWW_DIR" ]; then
    echo "Expected web root at $WWW_DIR, but it doesn't exist. Aborting." >&2
    exit 1
fi

rsync -a --delete \
    --exclude=index.php \
    --exclude=storage \
    "$APP_DIR/public/" "$WWW_DIR/"

cp "$APP_DIR/deploy/webroot-index.php" "$WWW_DIR/index.php"

ln -sfn "$APP_DIR/storage/app/public" "$WWW_DIR/storage"

echo "Synced $APP_DIR/public/ -> $WWW_DIR"

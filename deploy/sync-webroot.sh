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

# The web root is SHARED: other sites on this account are served from their own
# folders inside it (Varians is $WWW_DIR/varians, a symlink to ~/varians/public).
# `rsync --delete` mirrors, so on its own it treats every entry it does not
# recognise as stale and removes it — which is how Varians's document root was
# deleted once already. So every top-level entry this app's public/ does not
# itself ship is protected by name. Stale files *inside* the folders this app
# does own are still pruned, and build/ — where hashed assets pile up — is the
# one that actually matters.
#
# Nothing has to be added here when another site appears: it is protected
# because public/ has no such entry. The names are printed so that a leftover
# of this app's own, dropped from public/ but lingering in the web root, stays
# visible instead of being silently kept.
set --
protected=''
for entry in "$WWW_DIR"/* "$WWW_DIR"/.*; do
    name="${entry##*/}"
    case "$name" in
        '.' | '..' | '*' | '.*') continue ;;
    esac
    if [ ! -e "$APP_DIR/public/$name" ] && [ ! -L "$APP_DIR/public/$name" ]; then
        set -- "$@" --filter="protect /$name"
        protected="${protected:+$protected }$name"
    fi
done

rsync -a --delete \
    "$@" \
    --exclude=index.php \
    --exclude=storage \
    "$APP_DIR/public/" "$WWW_DIR/"

cp "$APP_DIR/deploy/webroot-index.php" "$WWW_DIR/index.php"

ln -sfn "$APP_DIR/storage/app/public" "$WWW_DIR/storage"

echo "Synced $APP_DIR/public/ -> $WWW_DIR"
if [ -n "$protected" ]; then
    echo "Left alone in $WWW_DIR (not this app's): $protected"
fi

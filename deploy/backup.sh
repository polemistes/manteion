#!/usr/bin/env bash
# Copy the database and the uploaded files into /home/manteion/backups/<date>,
# keeping two weeks. Run as the manteion user (the manteion-backup timer does
# it nightly).
set -euo pipefail

BASE="${MANTEION_BASE:-/home/manteion}"
DB_FILE="$(sed -n 's/^DB_DATABASE=//p' "$BASE/manteion.env" | tr -d '"')"
DB_FILE="${DB_FILE:-$BASE/database/manteion.sqlite}"

DEST="$BASE/backups/$(date +%Y-%m-%d)"
mkdir -p "$DEST"

# .backup takes a consistent copy even while the app is writing to it.
sqlite3 "$DB_FILE" ".backup '$DEST/manteion.sqlite'"
rsync -a --delete "$BASE/storage/app/public/" "$DEST/uploads/"

# Keep the last 14 days (the copies taken before deploys are kept apart).
find "$BASE/backups" -maxdepth 1 -mindepth 1 -type d -name '20*' -mtime +14 -exec rm -rf {} +
echo "Backup written to $DEST"

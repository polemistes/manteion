#!/usr/bin/env bash
# Roll out the committed state of this repository to the server. Run on your
# own machine: copies HEAD over as a new release, builds it there as the
# manteion user (deploy/deploy.sh), then reloads PHP and restarts the queue
# worker and Reverb so that everything runs the new release.
#
#   deploy/push.sh            the server is proteus@aldebaran.no
#   MANTEION_HOST=me@host deploy/push.sh
set -euo pipefail

HOST="${MANTEION_HOST:-proteus@aldebaran.no}"
cd "$(dirname "$0")/.."

if [ -n "$(git status --porcelain)" ] && [ "${FORCE:-}" != 1 ]; then
  echo "There are uncommitted changes, and only commits are deployed. Commit them, or deploy HEAD as it is with FORCE=1." >&2
  exit 1
fi

REV="$(git rev-parse --short HEAD)"
NAME="$(date +%Y%m%d-%H%M%S)-$REV"
DIR="/home/manteion/releases/$NAME"

echo "Copying $REV to $HOST:$DIR"
git archive --format=tar HEAD | ssh "$HOST" "sudo -n -u manteion mkdir -p '$DIR' && sudo -n -u manteion tar -x -C '$DIR'"

echo "Building on the server"
ssh "$HOST" "cd / && sudo -n -u manteion -H '$DIR/deploy/deploy.sh'"

if ssh "$HOST" "systemctl list-unit-files manteion-queue.service --no-legend | grep -q manteion"; then
  # PHP keeps the old release's code cached until it reloads.
  echo "Reloading PHP and restarting the services"
  ssh "$HOST" "sudo -n systemctl reload php-fpm && sudo -n systemctl restart manteion-queue manteion-reverb && sleep 2 && systemctl is-active manteion-queue manteion-reverb"
else
  echo "The services are not installed yet (see deploy/README.md); the release is built and current."
fi

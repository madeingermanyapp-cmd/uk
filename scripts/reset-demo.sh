#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
printf 'Delete ALL local Wonham database, uploads and documents? Type DELETE LOCAL DEMO: '
read -r answer
[ "$answer" = 'DELETE LOCAL DEMO' ] || { echo 'Cancelled.'; exit 1; }
docker compose --project-name wonham-local --env-file .local/compose.env down --volumes
echo 'Local data removed. Run START_LOCAL to initialize again.'

#!/bin/sh
set -eu
cd "$(dirname "$0")"
docker compose --project-name wonham-local --env-file .local/compose.env stop

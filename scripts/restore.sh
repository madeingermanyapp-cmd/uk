#!/bin/sh
# Replaces the selected deployment with a verified backup. Take a fresh backup first.
set -eu
[ "$#" -eq 4 ] || { echo 'Usage: restore.sh COMPOSE_PROJECT ENV_FILE ABSOLUTE_BACKUP_DIRECTORY --replace-selected-deployment'; exit 1; }
[ "$4" = '--replace-selected-deployment' ] || { echo 'Explicit replacement flag required.'; exit 1; }
project=$1
config=$2
backup=$3
case "$backup" in /*) ;; *) echo 'Backup source must be absolute.'; exit 1;; esac
[ -s "$backup/database.sql" ] && [ -d "$backup/storage/keys" ] && [ -d "$backup/uploads" ] || { echo 'Incomplete backup: database, storage (including keys) and uploads are required.'; exit 1; }
compose() { docker compose --project-name "$project" --env-file "$config" "$@"; }
python3 scripts/backup-manifest.py verify "$backup"
compose stop app jobs
# Leave services stopped on failure to prevent serving a partial restoration.
compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysql -u "$MYSQL_USER" "$MYSQL_DATABASE"' < "$backup/database.sql"
compose run --rm --no-deps --user root app sh -c 'find storage public/uploads -mindepth 1 -maxdepth 1 -exec rm -rf -- {} +'
compose cp "$backup/storage/." app:/var/www/html/storage/
compose cp "$backup/uploads/." app:/var/www/html/public/uploads/
compose run --rm --no-deps --user root app chown -R www-data:www-data storage public/uploads
compose run --rm --no-deps --user www-data app php scripts/manor-init.php
compose start app jobs
printf 'Restored selected deployment %s. Verify health, private documents and bookings before reopening public access.\n' "$project"

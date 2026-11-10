#!/bin/sh
# Supply the same project/env used for deployment. Writes sensitive data outside Git.
set -eu
[ "$#" -eq 3 ] || { echo 'Usage: backup.sh COMPOSE_PROJECT ENV_FILE ABSOLUTE_BACKUP_DIRECTORY'; exit 1; }
project=$1
config=$2
backup=$3
case "$backup" in /*) ;; *) echo 'Backup destination must be absolute.'; exit 1;; esac
umask 077
mkdir -p "$backup"
[ ! -e "$backup/database.sql" ] || { echo 'Destination already contains a backup; choose a new directory.'; exit 1; }
compose() { docker compose --project-name "$project" --env-file "$config" "$@"; }
compose stop app
trap 'compose start app' EXIT HUP INT TERM
compose exec -T db sh -c 'MYSQL_PWD="$MYSQL_PASSWORD" mysqldump -u "$MYSQL_USER" --single-transaction --no-tablespaces --routines --triggers "$MYSQL_DATABASE"' > "$backup/database.sql"
compose cp app:/var/www/html/storage "$backup/storage"
compose cp app:/var/www/html/public/uploads "$backup/uploads"
git rev-parse HEAD > "$backup/commit.txt"
printf 'Backup completed at %s. Store encrypted off-server and verify restoration.\n' "$backup"

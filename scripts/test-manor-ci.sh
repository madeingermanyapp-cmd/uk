#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
php tests/Manor/rules.php
run_id=$(php -r 'echo bin2hex(random_bytes(8));')
container="wonham-core-test-$run_id"
secret=$(php -r 'echo bin2hex(random_bytes(24));')
export MANOR_TEST_PASSWORD="$secret"
export MANOR_TEST_USER=root
run_dir=$(mktemp -d)
chmod 700 "$run_dir"
trap 'docker rm -f -v "$container" >/dev/null 2>&1 || true; rm -rf "$run_dir"' EXIT HUP INT TERM
printf 'MYSQL_ROOT_PASSWORD=%s\n' "$secret" > "$run_dir/mysql.env"
docker run -d --name "$container" --env-file "$run_dir/mysql.env" -p 127.0.0.1::3306 mysql:8.4 >/dev/null
MANOR_TEST_PORT=$(docker port "$container" 3306/tcp | sed 's/.*://')
export MANOR_TEST_PORT
attempt=0
until docker exec "$container" sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -e "SELECT 1"' >/dev/null 2>&1; do
 attempt=$((attempt+1)); [ "$attempt" -lt 90 ] || { echo 'Disposable MySQL failed to become ready.'; exit 1; }; sleep 1
done
php tests/Manor/integration.php

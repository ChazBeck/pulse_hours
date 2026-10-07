#!/bin/sh
# Integration test for the machine API (api/) and draft confirmation
# (includes/hours_drafts.php) against a THROWAWAY MariaDB in Docker.
# Nothing here can reach a real database: the DB is a fresh tmpfs container
# and config/db_config.php's constants are pre-defined by prepend.php.
#
#   sh tests/integration/run.sh
set -e
cd "$(dirname "$0")/../.."
APP_IMAGE="${PULSE_TEST_IMAGE:-pulse_hours-app:latest}"   # needs php + pdo_mysql
NET="pulse-test-$$"
DB="pulse-test-db-$$"
APP="pulse-test-app-$$"
cleanup() { docker rm -f "$DB" "$APP" >/dev/null 2>&1 || true; docker network rm "$NET" >/dev/null 2>&1 || true; }
trap cleanup EXIT

docker network create "$NET" >/dev/null
docker run -d --name "$DB" --network "$NET" --tmpfs /var/lib/mysql \
  -e MARIADB_ROOT_PASSWORD=test -e MARIADB_DATABASE=plusehours mariadb:11.4 >/dev/null
docker run -d --name "$APP" --network "$NET" -e TEST_DB_HOST="$DB" "$APP_IMAGE" sleep 600 >/dev/null
docker exec "$APP" mkdir -p /t
docker cp . "$APP":/t/app

for i in $(seq 1 60); do
  docker exec "$DB" mariadb -uroot -ptest -e 'select 1' plusehours >/dev/null 2>&1 && break
  sleep 1
done

# The schema as an EXISTING install has it: the base file without this
# change's own section, then the earlier migrations, then this one — twice.
docker exec "$APP" sh -c "sed '/Draft hours from other apps/,\$d' /t/app/database/setup_database.sql | sed '\$d' > /t/base.sql"
# setup_database.sql creates projects before project_templates, which it
# references, so it only loads with FK checks off (as a dump restore would).
docker exec -i "$APP" sh -c "echo 'SET FOREIGN_KEY_CHECKS=0;'; cat /t/base.sql" | docker exec -i "$DB" mariadb -uroot -ptest
P="php -d auto_prepend_file=/t/app/tests/integration/prepend.php"
docker exec "$APP" sh -c "$P /t/app/database/migrate_add_is_internal.php && $P /t/app/database/migrate_add_task_sort_order.php" >/dev/null
echo "--- migration, first run"
docker exec "$APP" sh -c "$P /t/app/database/migrate_add_hours_drafts.php"
echo "--- migration, second run (must change nothing)"
docker exec "$APP" sh -c "$P /t/app/database/migrate_add_hours_drafts.php"
docker exec -i "$DB" mariadb -uroot -ptest plusehours < tests/integration/seed.sql

# Tokens: the file holds only hashes.
docker exec "$APP" sh -c "printf 'cos-168 %s\ncos-168 %s charlie@veerless.com\n' \
  \$(printf %s test-token | sha256sum | cut -d' ' -f1) \$(printf %s scoped-token | sha256sum | cut -d' ' -f1) > /t/tokens"
# The hours page needs app_config.php (not in git) and an SSO user: the
# test-only stub logs in as charlie@veerless.com, who has a check-in for the week.
docker exec "$APP" sh -c "sed \"s#define('BASE_URL', '/pulse/')#define('BASE_URL', '/')#\" /t/app/config/app_config.example.php > /t/app/config/app_config.php"
docker exec "$DB" mariadb -uroot -ptest plusehours -e "INSERT INTO pulse (user_id, year_week, pulse, work_load) VALUES (101, '2026-16', 3, 5)"
docker exec -d "$APP" sh -c "cd /t/app && PULSE_API_TOKENS_FILE=/t/tokens SSO_JWT_INCLUDE=/t/app/tests/integration/sso_stub.php LOCAL_DEV_USER_EMAIL=charlie@veerless.com $P -S 127.0.0.1:8099 >/t/server.log 2>&1"
sleep 1
echo "--- drafts flow"
docker exec "$APP" sh -c "TEST_BASE=http://127.0.0.1:8099 TEST_TOKEN=test-token $P /t/app/tests/integration/drafts_flow_test.php" || {
  echo "--- server log"; docker exec "$APP" cat /t/server.log; exit 1; }
echo "--- hours page"
docker exec "$APP" sh -c "TEST_BASE=http://127.0.0.1:8099 TEST_TOKEN=test-token $P /t/app/tests/integration/hours_page_test.php" || {
  echo "--- server log"; docker exec "$APP" tail -40 /t/server.log; exit 1; }
echo "--- confirm vs. a concurrent hand edit"
docker exec "$APP" sh -c "$P /t/app/tests/integration/confirm_race_test.php" || exit 1

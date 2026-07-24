#!/usr/bin/env sh
set -e

# Waits for the database to accept connections, then runs migrations, before
# handing off to the container's real command (apache2-foreground). In
# Compose/Kubernetes the DB container is frequently still starting when this
# container boots, and `php spark migrate` has no built-in retry — without
# this wait the app crash-loops until the DB happens to win the race.
#
# Reads the same UPPERCASE env vars that app/Config/Database.php falls back
# to (DB_HOST, DB_PORT, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE), since
# those are what a container orchestrator actually injects.

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

echo "Waiting for database at ${DB_HOST}:${DB_PORT}..."

attempt=0
max_attempts=30
until php -r '
    $host = getenv("DB_HOST") ?: "127.0.0.1";
    $port = (int) (getenv("DB_PORT") ?: 3306);
    $user = getenv("MYSQL_USER") ?: "root";
    $pass = getenv("MYSQL_PASSWORD") ?: "";
    $conn = @mysqli_connect($host, $user, $pass, "", $port);
    exit($conn ? 0 : 1);
'; do
    attempt=$((attempt + 1))
    if [ "${attempt}" -ge "${max_attempts}" ]; then
        echo "Database still unreachable after ${max_attempts} attempts — continuing without it. Migrations will likely fail." >&2
        break
    fi
    sleep 2
done

echo "Running migrations..."
php spark migrate --all || echo "Migration step failed — continuing startup, check logs." >&2

exec "$@"

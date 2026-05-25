#!/usr/bin/env bash
# CodeIgniter 4 Domain Starter — environment initializer.
# Run from the project root after cloning.
# Usage: ./init.sh [--skip-deps] [--skip-db] [--skip-sync] [--skip-server]

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=scripts/setup.sh
source "$SCRIPT_DIR/scripts/setup.sh"

SKIP_DEPS=false
SKIP_DB=false
SKIP_SYNC=false
SKIP_SERVER=false
DOCKER_CONTAINER_ARG=""
ADMIN_TOKEN_ARG=""

while [ $# -gt 0 ]; do
  case $1 in
    --skip-deps)   SKIP_DEPS=true; shift ;;
    --skip-db)     SKIP_DB=true; shift ;;
    --skip-sync)   SKIP_SYNC=true; shift ;;
    --skip-server) SKIP_SERVER=true; shift ;;
    --docker-container)
      DOCKER_CONTAINER_ARG="$2"
      shift 2
      ;;
    --admin-token)
      ADMIN_TOKEN_ARG="$2"
      shift 2
      ;;
    --help)
      printf "Usage: ./init.sh [OPTIONS]\n\n"
      printf "Options:\n"
      printf "  --skip-deps           Skip composer install\n"
      printf "  --skip-db             Skip database creation and migrations\n"
      printf "  --skip-sync           Skip permission sync against the hub\n"
      printf "  --skip-server         Do not offer to start the development server\n"
      printf "  --docker-container    Specify Docker container name for MySQL\n"
      printf "  --admin-token         Superadmin JWT for domain:sync-permissions (non-interactive)\n"
      printf "  --help                Show this help message\n"
      exit 0
      ;;
    *)
      print_error "Unknown option: $1"
      exit 1
      ;;
  esac
done

# If Docker container was passed as an argument, use it
if [ -n "$DOCKER_CONTAINER_ARG" ]; then
  export CI4_DOCKER_CONTAINER="$DOCKER_CONTAINER_ARG"
fi

LOG_FILE="$(pwd)/init.log"
exec > >(tee -a "$LOG_FILE") 2>&1
printf "Init log: %s\n" "$LOG_FILE"

print_header "CI4 Domain Starter — Environment Setup"

# ---------------------------------------------------------------------------
# Requirements
# ---------------------------------------------------------------------------

print_header "Checking requirements"
require_cmd php
require_cmd composer
detect_mysql_mode

if ! php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);'; then
  print_error "PHP 8.2+ is required (found: $(php -r 'echo PHP_VERSION;'))."
  exit 1
fi
print_ok "Dependencies found (php, composer)"

# ---------------------------------------------------------------------------
# Hub coordinates
# ---------------------------------------------------------------------------

print_header "Hub coordinates"
printf "This domain app delegates auth to a running ci4-api-starter (the hub).\n"
printf "You need: hub URL, an X-App-Key bound to a registered application,\n"
printf "and the application code.\n\n"

HUB_URL="$(ask_with_default 'Hub URL' "${CI4_DOMAIN_HUB_URL:-http://localhost:8080}")"
HUB_APP_CODE="$(ask_with_default 'Application code (registered in hub)' "${CI4_DOMAIN_APP_CODE:-example-domain}")"

# Allow ci4-kickstart (and CI) to pre-supply the X-App-Key via env var so this
# script runs non-interactively. Falls back to a prompt for standalone runs.
HUB_API_KEY="${CI4_DOMAIN_API_KEY:-}"
if [ -z "$HUB_API_KEY" ]; then
  read -r -p "X-App-Key: " HUB_API_KEY
fi

# ---------------------------------------------------------------------------
# Database
# ---------------------------------------------------------------------------

DB_HOST="${CI4_DOMAIN_DB_HOST:-127.0.0.1}"
DB_PORT="${CI4_DOMAIN_DB_PORT:-3306}"
DB_USER="${CI4_DOMAIN_DB_USER:-root}"
DB_PASS="${CI4_DOMAIN_DB_PASS-}"
DB_NAME="${CI4_DOMAIN_DB_NAME:-ci4_domain}"
TEST_DB_NAME="${CI4_DOMAIN_TEST_DB_NAME:-ci4_domain_test}"

[ -n "$DETECTED_DOCKER_PORT" ] && [ -z "${CI4_DOMAIN_DB_PORT:-}" ] && DB_PORT="$DETECTED_DOCKER_PORT"

if [ "$SKIP_DB" = false ]; then
  print_header "Database configuration"
  DB_HOST="$(ask_with_default 'MySQL host' "$DB_HOST")"
  DB_PORT="$(ask_with_default 'MySQL port' "$DB_PORT")"
  DB_USER="$(ask_with_default 'MySQL user' "$DB_USER")"
  if [ -z "${CI4_DOMAIN_DB_PASS+x}" ]; then
    read -r -s -p "MySQL password (can be empty): " DB_PASS
    printf "\n"
  fi
  DB_NAME="$(ask_with_default 'Database name' "$DB_NAME")"
  TEST_DB_NAME="$(ask_with_default 'Test database name' "$TEST_DB_NAME")"
fi

# ---------------------------------------------------------------------------
# Steps
# ---------------------------------------------------------------------------

if [ "$SKIP_DEPS" = false ]; then
  print_header "Installing dependencies"
  composer install --no-interaction --no-progress
fi

print_header "Writing .env"
if [ -f ".env" ]; then
  print_warn ".env exists. Backing up to .env.bak.$(date +%s)"
  cp .env ".env.bak.$(date +%s)"
fi

cp .env.example .env
php scripts/bootstrap_env.php \
  --file .env \
  --set "database.default.hostname=${DB_HOST}" \
  --set "database.default.port=${DB_PORT}" \
  --set "database.default.username=${DB_USER}" \
  --set "database.default.password=${DB_PASS}" \
  --set "database.default.database=${DB_NAME}" \
  --set "database.tests.database=${TEST_DB_NAME}" \
  --set "hub.url=${HUB_URL}" \
  --set "hub.appCode=${HUB_APP_CODE}" \
  --set "hub.apiKey=${HUB_API_KEY}" \
  --set "hub.adminToken=${CI4_DOMAIN_ADMIN_TOKEN:-}" \
  --generate-jwt

php spark key:generate --force >/dev/null
print_ok ".env written"

if [ "$SKIP_DB" = false ]; then
  print_header "Preparing database"
  php spark db:create "$DB_NAME" || print_warn "Could not create database (may already exist)."
  php spark db:create "$TEST_DB_NAME" || true
  php spark migrate
  print_ok "Migrations applied"
fi

print_header "Validating ci4-api-core service wiring"
php spark core:check || { print_error "Service wiring incomplete. Fix app/Config/Services.php before continuing."; exit 1; }

# ---------------------------------------------------------------------------
# Sync permissions to hub
# ---------------------------------------------------------------------------

if [ "$SKIP_SYNC" = false ]; then
  print_header "Registering permissions in the hub"

  # Priority: --admin-token CLI arg > CI4_DOMAIN_ADMIN_TOKEN env var > interactive prompt.
  # The CLI arg path is used by ci4-kickstart orchestration; the env var is kept for
  # backward compatibility; the interactive path is for standalone human-driven runs.
  ADMIN_TOKEN="${ADMIN_TOKEN_ARG:-${CI4_DOMAIN_ADMIN_TOKEN:-}}"

  if [ -z "$ADMIN_TOKEN" ] && [ -t 0 ]; then
    printf "domain:sync-permissions registers this app's permissions in the hub.\n"
    printf "It needs a superadmin JWT (one-time setup — service tokens lack iam.superadmin-access).\n"
    printf "Obtain one via: curl -X POST %s/api/v1/auth/login \\\n" "$HUB_URL"
    printf '    -H "Content-Type: application/json" -d '"'"'{"email":"...","password":"..."}'"'"'\n\n'
    read -r -p "Paste superadmin JWT (or press Enter to skip): " ADMIN_TOKEN
  fi

  if [ -n "${ADMIN_TOKEN_ARG:-}" ] || [ -n "${CI4_DOMAIN_ADMIN_TOKEN:-}" ]; then
    # Token was machine-supplied (CLI arg or env var from orchestrator) — treat failure
    # as a hard error so the checkpoint fails cleanly instead of silently continuing.
    _sync_args="--admin-token=${ADMIN_TOKEN}"
    [ -n "$ASSIGN_TO_ROLE_ARG" ] && _sync_args="${_sync_args} --assign-to-role=${ASSIGN_TO_ROLE_ARG}"

    php spark domain:sync-permissions ${_sync_args} \
        || { print_error "Permission sync failed (token was machine-supplied). Check hub connectivity."; exit 1; }
    print_ok "Permissions synced"
  elif [ -n "$ADMIN_TOKEN" ]; then
    # Token came from interactive prompt — soft failure is acceptable
    _sync_args="--admin-token=${ADMIN_TOKEN}"
    [ -n "$ASSIGN_TO_ROLE_ARG" ] && _sync_args="${_sync_args} --assign-to-role=${ASSIGN_TO_ROLE_ARG}"

    if php spark domain:sync-permissions ${_sync_args}; then
      print_ok "Permissions synced"
    else
      print_warn "Permission sync failed — re-run later with:"
      printf "  php spark domain:sync-permissions --admin-token=<jwt>\n"
    fi
  else
    print_warn "Skipped. Run later with:"
    printf "  php spark domain:sync-permissions --admin-token=<jwt>\n"
  fi
fi

# ---------------------------------------------------------------------------
# Done
# ---------------------------------------------------------------------------

print_header "Done"
printf "Domain app ready at: %s\n" "$(pwd)"
printf "Hub: %s (app=%s)\n" "$HUB_URL" "$HUB_APP_CODE"

if [ "$SKIP_SERVER" = false ]; then
  read -r -p "Start development server now? (y/N): " START_SERVER
  case "$(echo "$START_SERVER" | tr '[:upper:]' '[:lower:]')" in
    y|yes)
      print_header "Starting development server"
      printf "Server at http://localhost:8090 — press Ctrl+C to stop.\n\n"
      php spark serve --port 8090
      ;;
    *)
      printf "Start server: php spark serve --port 8090\n"
      ;;
  esac
fi

#!/usr/bin/env bash
# Development only. The scan directory is inherited by Artisan's child PHP server.
# Passing `php -d ... artisan serve` alone does NOT configure that child process.
set -euo pipefail
cd "$(dirname "$0")/.."
export PHP_INI_SCAN_DIR="${PHP_INI_SCAN_DIR:-}:$PWD/scripts/php"
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"
exec php artisan serve --host=0.0.0.0 --port="${SPACEKIND_WEB_PORT:-8000}" --no-reload

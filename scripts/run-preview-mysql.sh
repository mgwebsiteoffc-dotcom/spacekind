#!/usr/bin/env bash
set -euo pipefail
BASE="${SPACEKIND_MYSQL_BASE:-/tmp/spacekind-mysql}"
DATA="${SPACEKIND_MYSQL_DATA:-/tmp/spacekind-mysql-data}"
LIB="${SPACEKIND_MYSQL_LIB:-/tmp/spacekind-mysql-lib}"
if [[ ! -x "$BASE/bin/mysqld" || ! -f "$DATA/auto.cnf" ]]; then echo "Run scripts/prepare-preview.sh first." >&2; exit 1; fi
# Hold this in a managed server process. Loopback-only actual MySQL, tuned for a small dev sandbox.
exec env LD_LIBRARY_PATH="$LIB${LD_LIBRARY_PATH:+:$LD_LIBRARY_PATH}" "$BASE/bin/mysqld" --no-defaults  --basedir="$BASE" --datadir="$DATA" --socket=/tmp/spacekind-mysql.sock  --port=3306 --bind-address=127.0.0.1 --mysqlx=OFF --performance-schema=OFF  --innodb-buffer-pool-size=32M --innodb-log-buffer-size=4M --innodb-redo-log-capacity=16M  --max-connections=20 --table-open-cache=128 --table-definition-cache=400 --thread-cache-size=4  --tmp-table-size=8M --max-heap-table-size=8M --temptable-max-ram=16M --temptable-max-mmap=16M  --skip-log-bin --pid-file=/tmp/spacekind-mysql.pid --log-error=/tmp/spacekind-mysql-server.log --user="$(id -un)"

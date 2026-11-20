#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
if ! command -v php >/dev/null || ! php -r 'exit(version_compare(PHP_VERSION,"8.3",">=")?0:1);'; then
 sudo -n env DEBIAN_FRONTEND=noninteractive apt-get update -qq
 sudo -n env DEBIAN_FRONTEND=noninteractive apt-get install -y -qq php-cli php-mysql php-mbstring php-xml php-curl php-zip php-bcmath php-intl unzip curl git libaio1t64 libnuma1
fi
if ! command -v ffprobe >/dev/null; then
 sudo -n apt-get update -qq
 sudo -n env DEBIAN_FRONTEND=noninteractive apt-get install -y -qq --no-install-recommends ffmpeg
fi
php -r 'if(version_compare(PHP_VERSION,"8.3","<")){fwrite(STDERR,"PHP 8.3+ is required. Use a compatible Debian environment.\n");exit(1);}'
if ! command -v composer >/dev/null; then
 curl -fsSL https://getcomposer.org/installer -o /tmp/spacekind-composer-setup.php
 expected="$(curl -fsSL https://composer.github.io/installer.sig)"
 actual="$(php -r 'echo hash_file("sha384","/tmp/spacekind-composer-setup.php");')"
 [[ "$expected" == "$actual" ]] || { echo "Composer installer checksum failed" >&2; exit 1; }
 php /tmp/spacekind-composer-setup.php --install-dir=/tmp --filename=spacekind-composer
 sudo -n install -m755 /tmp/spacekind-composer /usr/local/bin/composer
fi
[[ -f .env ]] || cp .env.example .env
composer install --prefer-dist --no-interaction
if grep -Eq '^APP_KEY=$' .env; then php artisan key:generate; fi
BASE="${SPACEKIND_MYSQL_BASE:-/tmp/spacekind-mysql}"
DATA="${SPACEKIND_MYSQL_DATA:-/tmp/spacekind-mysql-data}"
LIB="${SPACEKIND_MYSQL_LIB:-/tmp/spacekind-mysql-lib}"
mkdir -p "$BASE" "$DATA" "$LIB"
if [[ ! -x "$BASE/bin/mysqld" ]]; then
 curl -fL --retry 2 https://cdn.mysql.com/Downloads/MySQL-8.4/mysql-8.4.9-linux-glibc2.28-x86_64-minimal.tar.xz -o /tmp/spacekind-mysql.tar.xz
 echo 'd0142fc30e04bae7f15a46567238adf8956acccd35c5bca6e448322644536e3d  /tmp/spacekind-mysql.tar.xz' | sha256sum --check --status
 tar -xJf /tmp/spacekind-mysql.tar.xz --strip-components=1 -C "$BASE"
fi
if [[ -e /usr/lib/x86_64-linux-gnu/libaio.so.1t64 ]]; then ln -sf /usr/lib/x86_64-linux-gnu/libaio.so.1t64 "$LIB/libaio.so.1"; fi
if [[ ! -f "$DATA/auto.cnf" ]]; then
 LD_LIBRARY_PATH="$LIB" "$BASE/bin/mysqld" --no-defaults --initialize-insecure --basedir="$BASE" --datadir="$DATA" --user="$(id -un)" --mysqlx=OFF --innodb-buffer-pool-size=32M --log-error=/tmp/spacekind-mysql-init.log
fi
printf '\nPrepared only; no server was launched.\n1. Start scripts/run-preview-mysql.sh in a managed process.\n2. A freshly initialised server has an EMPTY local root password. Secure it immediately; create restricted app/test users and databases matching .env.\n3. php artisan migrate\n4. Restore private preview state OR explicitly enable demo mode and seed fictional content.\n5. Start the Laravel website as described in README.md.\n'

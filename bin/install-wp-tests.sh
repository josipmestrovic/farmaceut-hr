#!/usr/bin/env bash
#
# Priprema izoliranog WordPress testnog okruženja za Farmaceut.hr.
# Dozvoljena je isključivo baza: farmaceut_test
#
# Upotreba:
#   bash bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]
#

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
YELLOW='\033[0;33m'
RESET='\033[0m'

if [ $# -lt 3 ]; then
	echo -e "${YELLOW}Upotreba:${RESET} $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-7.1.5}
SKIP_DB_CREATE=${6-false}

ALLOWED_DB_NAME='farmaceut_test'

if [ "$DB_NAME" != "$ALLOWED_DB_NAME" ]; then
	echo -e "${RED}Zaštita:${RESET} testna baza mora biti točno '${ALLOWED_DB_NAME}' (dobiveno: '${DB_NAME}')."
	echo "Ova skripta ne smije dirati LocalWP, staging ni produkcijsku bazu."
	exit 1
fi

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo "$TMPDIR" | sed -e "s/\/$//")
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}
WP_CORE_FILE="$WP_CORE_DIR/wp-settings.php"
WP_TESTS_CONFIG_DIR=${WP_TESTS_CONFIG_DIR-$TMPDIR/wordpress-tests-lib}
WP_TESTS_CONFIG="$WP_TESTS_CONFIG_DIR/wp-tests-config.php"

SCRIPT_DIR=$(cd "$(dirname "$0")" && pwd)
REPO_ROOT=$(cd "$SCRIPT_DIR/.." && pwd)
WP_PHPUNIT_DIR=${WP_PHPUNIT_DIR:-$REPO_ROOT/vendor/wp-phpunit/wp-phpunit}

download() {
	if command -v curl > /dev/null 2>&1; then
		curl -L -s "$1" > "$2"
		return $?
	elif command -v wget > /dev/null 2>&1; then
		wget -nv -O "$2" "$1"
		return $?
	fi
	echo -e "${RED}Greška:${RESET} potrebni su curl ili wget."
	exit 1
}

install_wp() {
	if [ -f "$WP_CORE_FILE" ]; then
		echo -e "${CYAN}WordPress je već instaliran u ${WP_CORE_DIR}.${RESET}"
		return
	fi

	echo -e "${CYAN}Preuzimam WordPress ${WP_VERSION}...${RESET}"
	rm -rf "$WP_CORE_DIR"
	mkdir -p "$WP_CORE_DIR"

	local archive_name="wordpress-${WP_VERSION}"
	download "https://wordpress.org/${archive_name}.tar.gz" "$TMPDIR/wordpress.tar.gz"

	if [ ! -s "$TMPDIR/wordpress.tar.gz" ]; then
		echo -e "${RED}Greška:${RESET} preuzimanje WordPress arhive nije uspjelo."
		exit 1
	fi

	tar --strip-components=1 -zxmf "$TMPDIR/wordpress.tar.gz" -C "$WP_CORE_DIR"
	rm -f "$TMPDIR/wordpress.tar.gz"
	echo -e "${GREEN}WordPress ${WP_VERSION} instaliran.${RESET}"
}

install_config() {
	mkdir -p "$WP_TESTS_CONFIG_DIR"

	if [ ! -d "$WP_PHPUNIT_DIR/includes" ]; then
		echo -e "${RED}Greška:${RESET} nedostaje wp-phpunit u ${WP_PHPUNIT_DIR}."
		echo "Pokreni najprije: composer install"
		exit 1
	fi

	echo -e "${CYAN}Pišem wp-tests-config.php...${RESET}"

	WP_CORE_DIR_TRIM=$(echo "$WP_CORE_DIR" | sed "s:/\+$::")

	cat > "$WP_TESTS_CONFIG" <<EOF
<?php
/**
 * Konfiguracija izoliranog WordPress PHPUnit okruženja (Farmaceut.hr).
 * Generira bin/install-wp-tests.sh — ne koristiti produkcijske podatke.
 */

define( 'DB_NAME', '${DB_NAME}' );
define( 'DB_USER', '${DB_USER}' );
define( 'DB_PASSWORD', '${DB_PASS}' );
define( 'DB_HOST', '${DB_HOST}' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

\$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Farmaceut Test' );

define( 'WP_PHP_BINARY', 'php' );
define( 'ABSPATH', '${WP_CORE_DIR_TRIM}/' );
EOF

	# Dodatna zaštita: config mora sadržavati točan naziv baze.
	if ! grep -q "define( 'DB_NAME', '${ALLOWED_DB_NAME}' );" "$WP_TESTS_CONFIG"; then
		echo -e "${RED}Zaštita:${RESET} wp-tests-config.php nema očekivani DB_NAME."
		exit 1
	fi

	echo -e "${GREEN}Konfiguracija spremljena u ${WP_TESTS_CONFIG}.${RESET}"
}

create_db() {
	local extra=""
	local parts=(${DB_HOST//:/ })
	local db_hostname=${parts[0]-}
	local db_sock_or_port=${parts[1]-}

	if [ -n "$db_hostname" ]; then
		if echo "${db_sock_or_port:-}" | grep -Eq '^[0-9]+$'; then
			extra=" --host=$db_hostname --port=$db_sock_or_port --protocol=tcp"
		elif [ -n "${db_sock_or_port:-}" ]; then
			extra=" --socket=$db_sock_or_port"
		else
			extra=" --host=$db_hostname --protocol=tcp"
		fi
	fi

	local db_client='mysql'
	if command -v mariadb > /dev/null 2>&1; then
		db_client='mariadb'
	fi

	# shellcheck disable=SC2086
	if $db_client --user="$DB_USER" --password="$DB_PASS"$extra --execute='SHOW DATABASES;' | grep -q "^${DB_NAME}$"; then
		echo -e "${CYAN}Brišem postojeću testnu bazu ${DB_NAME}...${RESET}"
		# shellcheck disable=SC2086
		$db_client --user="$DB_USER" --password="$DB_PASS"$extra --execute="DROP DATABASE \`${DB_NAME}\`;"
	fi

	echo -e "${CYAN}Stvaram testnu bazu ${DB_NAME}...${RESET}"
	# shellcheck disable=SC2086
	$db_client --user="$DB_USER" --password="$DB_PASS"$extra --execute="CREATE DATABASE \`${DB_NAME}\`;"
	echo -e "${GREEN}Baza ${DB_NAME} spremna.${RESET}"
}

install_db() {
	if [ "$SKIP_DB_CREATE" = "true" ]; then
		echo -e "${YELLOW}Preskačem stvaranje baze.${RESET}"
		return 0
	fi
	create_db
}

povezi_projekt() {
	echo -e "${CYAN}Povezujem temu i farmaceut-* plugine u testni WordPress...${RESET}"

	mkdir -p "$WP_CORE_DIR/wp-content/themes"
	mkdir -p "$WP_CORE_DIR/wp-content/plugins"

	local tema_izvor="$REPO_ROOT/wp-content/themes/farmaceut"
	local tema_cilj="$WP_CORE_DIR/wp-content/themes/farmaceut"

	if [ ! -d "$tema_izvor" ]; then
		echo -e "${RED}Greška:${RESET} nedostaje tema u ${tema_izvor}."
		exit 1
	fi

	rm -rf "$tema_cilj"
	ln -s "$tema_izvor" "$tema_cilj"

	# Ukloni stare farmaceut-* linkove, zatim poveži postojeće.
	find "$WP_CORE_DIR/wp-content/plugins" -maxdepth 1 -type l -name 'farmaceut-*' -delete 2>/dev/null || true

	shopt -s nullglob
	for plugin_dir in "$REPO_ROOT"/wp-content/plugins/farmaceut-*; do
		if [ -d "$plugin_dir" ]; then
			local slug
			slug=$(basename "$plugin_dir")
			ln -s "$plugin_dir" "$WP_CORE_DIR/wp-content/plugins/$slug"
			echo -e "${CYAN}Povezan plugin: ${slug}${RESET}"
		fi
	done
	shopt -u nullglob

	echo -e "${GREEN}Tema i pluginovi povezani.${RESET}"
}

install_wp
povezi_projekt
install_config
install_db

echo ""
echo -e "${GREEN}Testno okruženje spremno.${RESET}"
echo "Eksportiraj prije composer test:"
echo "  export WP_CORE_DIR=${WP_CORE_DIR}"
echo "  export WP_PHPUNIT__DIR=${WP_PHPUNIT_DIR}"
echo "  export WP_PHPUNIT__TESTS_CONFIG=${WP_TESTS_CONFIG}"
echo "  export WP_TESTS_DIR=${WP_PHPUNIT_DIR}"

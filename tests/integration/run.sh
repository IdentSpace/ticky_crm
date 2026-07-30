#!/usr/bin/env bash
#
# Integrationstest für Ticky CRM.
#
# Startet eine echte Nextcloud-Instanz in Docker, installiert die App und prüft,
# dass (a) die Migrationen durchlaufen und (b) die API danach antwortet.
#
# Beispiele:
#   ./run.sh                                   # NC 33 + MariaDB, Frischinstallation
#   NC_VERSION=34 DB=postgres ./run.sh         # andere Kombination
#   UPGRADE_FROM=v0.2.1 ./run.sh               # Migrationspfad von 0.2.1 auf HEAD
#   TABLE_PREFIX='' ./run.sh                   # ohne Tabellenpräfix
#
set -euo pipefail

NC_VERSION="${NC_VERSION:-33}"
DB="${DB:-mariadb}"
TABLE_PREFIX="${TABLE_PREFIX:-oc_}"
UPGRADE_FROM="${UPGRADE_FROM:-}"
PORT="${PORT:-8081}"
ADMIN_USER="admin"
ADMIN_PASS="admin-integration-pw"

APP_ID="ticky_crm"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
APP_SOURCE="${APP_SOURCE:-$REPO_ROOT}"

RUN_ID="ticky-test-$$"
NETWORK="$RUN_ID-net"
APP_CT="$RUN_ID-nc"
DB_CT="$RUN_ID-db"
WORKDIR="$(mktemp -d)"

log()  { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
fail() { printf '\n\033[1;31m!! %s\033[0m\n' "$*"; exit 1; }

cleanup() {
    if [ -n "${KEEP_CONTAINERS:-}" ]; then
        echo "Container bleiben stehen: $APP_CT / $DB_CT"
        return
    fi
    docker rm -f "$APP_CT" "$DB_CT" >/dev/null 2>&1 || true
    docker network rm "$NETWORK" >/dev/null 2>&1 || true
    rm -rf "$WORKDIR"
}
trap cleanup EXIT

occ() { docker exec -u www-data "$APP_CT" php occ "$@"; }

wait_for() {
    local desc="$1"; shift
    local i
    for i in $(seq 1 60); do
        if "$@" >/dev/null 2>&1; then return 0; fi
        sleep 2
    done
    fail "Timeout beim Warten auf: $desc"
}

# App-Verzeichnis in den Container spiegeln. Bewusst über tar statt docker cp,
# damit node_modules & Co. draußen bleiben.
install_app_source() {
    local src="$1"
    docker exec "$APP_CT" rm -rf "/var/www/html/custom_apps/$APP_ID"
    docker exec "$APP_CT" mkdir -p "/var/www/html/custom_apps/$APP_ID"
    tar -C "$src" \
        --exclude=node_modules --exclude=.git --exclude=build --exclude=tests \
        -cf - . \
        | docker exec -i "$APP_CT" tar -C "/var/www/html/custom_apps/$APP_ID" -xf -
    docker exec "$APP_CT" chown -R www-data:www-data "/var/www/html/custom_apps/$APP_ID"
}

api_get() {
    curl -sS -o "$WORKDIR/body.json" -w '%{http_code}' \
        -u "$ADMIN_USER:$ADMIN_PASS" \
        -H 'Accept: application/json' \
        -H 'OCS-APIRequest: true' \
        "http://localhost:$PORT/apps/$APP_ID$1"
}

expect_ok() {
    local path="$1" status
    status="$(api_get "$path")"
    if [ "$status" != "200" ]; then
        echo "--- Response ---"; cat "$WORKDIR/body.json"; echo
        fail "GET $path lieferte HTTP $status (erwartet 200)"
    fi
    # Die Controller fangen \Throwable ab und antworten teils mit 200 + error-Key,
    # deshalb reicht der Statuscode allein nicht aus.
    if grep -q '"error"' "$WORKDIR/body.json"; then
        echo "--- Response ---"; cat "$WORKDIR/body.json"; echo
        fail "GET $path enthält einen error-Key"
    fi
    echo "  ok: GET $path"
}

# ---------------------------------------------------------------- Datenbank --

log "Starte Umgebung (Nextcloud $NC_VERSION, DB $DB, Präfix '${TABLE_PREFIX}')"
docker network create "$NETWORK" >/dev/null

case "$DB" in
    mariadb)
        docker run -d --name "$DB_CT" --network "$NETWORK" \
            -e MARIADB_ROOT_PASSWORD=root \
            -e MARIADB_DATABASE=nextcloud \
            -e MARIADB_USER=nextcloud \
            -e MARIADB_PASSWORD=nextcloud \
            mariadb:11.8 \
            --transaction-isolation=READ-COMMITTED --log-bin --binlog-format=ROW >/dev/null
        wait_for "MariaDB" docker exec "$DB_CT" mariadb-admin ping -h localhost --silent
        INSTALL_ARGS=(--database mysql --database-host "$DB_CT"
                      --database-name nextcloud --database-user nextcloud
                      --database-pass nextcloud)
        ;;
    postgres)
        docker run -d --name "$DB_CT" --network "$NETWORK" \
            -e POSTGRES_DB=nextcloud \
            -e POSTGRES_USER=nextcloud \
            -e POSTGRES_PASSWORD=nextcloud \
            postgres:16 >/dev/null
        wait_for "PostgreSQL" docker exec "$DB_CT" pg_isready -U nextcloud
        INSTALL_ARGS=(--database pgsql --database-host "$DB_CT"
                      --database-name nextcloud --database-user nextcloud
                      --database-pass nextcloud)
        ;;
    sqlite)
        INSTALL_ARGS=(--database sqlite)
        ;;
    *)
        fail "Unbekannte DB: $DB (mariadb|postgres|sqlite)"
        ;;
esac

# --------------------------------------------------------------- Nextcloud --

docker run -d --name "$APP_CT" --network "$NETWORK" \
    -p "127.0.0.1:$PORT:80" "nextcloud:$NC_VERSION" >/dev/null
wait_for "Nextcloud-Quellen" docker exec "$APP_CT" test -f /var/www/html/occ

log "Installiere Nextcloud"
occ maintenance:install \
    --admin-user "$ADMIN_USER" --admin-pass "$ADMIN_PASS" \
    --database-table-prefix "$TABLE_PREFIX" \
    "${INSTALL_ARGS[@]}"
occ config:system:set trusted_domains 1 --value="localhost:$PORT"
occ config:system:set loglevel --value=1 --type=integer

# Die App hängt laut info.xml an der Contacts-App.
log "Installiere Abhängigkeit: contacts"
occ app:install contacts || fail "contacts-App ließ sich nicht installieren"

# ------------------------------------------------- Optional: Upgrade-Pfad --

if [ -n "$UPGRADE_FROM" ]; then
    log "Baseline: installiere $UPGRADE_FROM"
    OLD_SRC="$WORKDIR/old"
    mkdir -p "$OLD_SRC"
    git -C "$REPO_ROOT" archive "$UPGRADE_FROM" | tar -x -C "$OLD_SRC" \
        || fail "Tag/Ref '$UPGRADE_FROM' existiert nicht"

    OLD_VER="$(grep -oP '(?<=<version>)[^<]+' "$OLD_SRC/appinfo/info.xml")"
    NEW_VER="$(grep -oP '(?<=<version>)[^<]+' "$APP_SOURCE/appinfo/info.xml")"
    [ "$OLD_VER" != "$NEW_VER" ] \
        || fail "Version in info.xml ist unverändert ($NEW_VER) – occ upgrade würde nichts tun"

    install_app_source "$OLD_SRC"
    occ app:enable "$APP_ID" || fail "Baseline $UPGRADE_FROM ließ sich nicht aktivieren"

    log "Spiele HEAD ein und führe Upgrade $OLD_VER -> $NEW_VER aus"
    install_app_source "$APP_SOURCE"
    occ upgrade || fail "occ upgrade fehlgeschlagen (Migration defekt)"
else
    log "Frischinstallation der App"
    install_app_source "$APP_SOURCE"
    occ app:enable "$APP_ID" || fail "app:enable fehlgeschlagen (Migration defekt)"
fi

# ------------------------------------------------------------- Prüfungen --

log "Prüfe Schema-Konsistenz"
occ db:add-missing-primary-keys
occ db:add-missing-columns
occ db:add-missing-indices
occ status

log "Smoke-Test der API"
expect_ok "/api/v1/clients"
expect_ok "/api/v1/settings"

log "Prüfe Log auf Fehler"
LOGFILE="$WORKDIR/nextcloud.log"
docker exec "$APP_CT" sh -c 'cat /var/www/html/data/nextcloud.log 2>/dev/null || true' > "$LOGFILE"
if grep -E '"level":[34]' "$LOGFILE" | grep -q "$APP_ID"; then
    echo "--- Relevante Log-Zeilen ---"
    grep -E '"level":[34]' "$LOGFILE" | grep "$APP_ID"
    fail "Fehler-Level-Einträge der App im nextcloud.log"
fi

log "Erfolgreich: NC $NC_VERSION / $DB / Präfix '${TABLE_PREFIX}'"
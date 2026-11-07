#!/usr/bin/env bash
#
# Integrationstest für Ticky CRM.
#
# Startet eine echte Nextcloud-Instanz in Docker, installiert die App und prüft,
# dass die Migrationen durchlaufen, der Foreign Key tatsächlich gesetzt ist und
# die API danach antwortet.
#
# Beispiele:
#   ./run.sh                              # NC 33 + MariaDB, Frischinstallation
#   NC_VERSION=34 DB=postgres ./run.sh    # andere Kombination
#   UPGRADE_FROM=v0.1.1 ./run.sh          # Migrationspfad von 0.1.1 auf HEAD
#   TABLE_PREFIX='' ./run.sh              # Gegenprobe ohne Tabellenpräfix
#   KEEP_CONTAINERS=1 ./run.sh            # Container zum Nachsehen stehen lassen
#
set -euo pipefail

NC_VERSION="${NC_VERSION:-33}"
DB="${DB:-mariadb}"
TABLE_PREFIX="${TABLE_PREFIX-oc_}"
UPGRADE_FROM="${UPGRADE_FROM:-}"
PORT="${PORT:-8081}"
OUTPUT_SEVERITY="${OUTPUT_SEVERITY:-debug}"
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

fail() { printf '\n\033[1;31m!! %s\033[0m\n' "$*"; exit 1; }

OUTPUT_SEVERITY_DEBUG=0
OUTPUT_SEVERITY_INFO=1
OUTPUT_SEVERITY_WARNING=2
OUTPUT_SEVERITY_ERROR=3
case "$OUTPUT_SEVERITY" in
    debug) OUTPUT_SEVERITY_NUM=$OUTPUT_SEVERITY_DEBUG ;;
    info)  OUTPUT_SEVERITY_NUM=$OUTPUT_SEVERITY_INFO ;;
    warning)  OUTPUT_SEVERITY_NUM=$OUTPUT_SEVERITY_WARNING ;;
    error)  OUTPUT_SEVERITY_NUM=$OUTPUT_SEVERITY_ERROR ;;
    *)     fail "Unbekannter OUTPUT_TYPE: $OUTPUT_TYPE (debug|info|warning|error)" ;;
esac

log_titel()  {
    SEVERITY="$1"; shift
    if [ "$OUTPUT_SEVERITY_NUM" -gt "$SEVERITY" ]; then return; fi
    printf '\n\033[1;34m==> %s\033[0m\n' "$*";
}
log_text()  {
    SEVERITY="$1"; shift
    if [ "$OUTPUT_SEVERITY_NUM" -gt "$SEVERITY" ]; then return; fi
    printf '%s' "$*";
}

cleanup() {
    local code=$?

    # Bei einem Fehlschlag ist das Container-Log meist aufschlussreicher als
    # die Meldung, an der das Skript abgebrochen ist.
    if [ "$code" -ne 0 ]; then
        log_text $OUTPUT_SEVERITY_ERROR ""
        log_text $OUTPUT_SEVERITY_ERROR "--- Letzte Zeilen aus dem Nextcloud-Container ---"
        docker logs --tail 40 "$APP_CT" 2>&1 || true
    fi

    if [ -n "${KEEP_CONTAINERS:-}" ]; then
        log_text $OUTPUT_SEVERITY_DEBUG ""
        log_text $OUTPUT_SEVERITY_DEBUG "Container bleiben stehen: $APP_CT / $DB_CT"
        log_text $OUTPUT_SEVERITY_DEBUG "Oberfläche: http://localhost:$PORT  ($ADMIN_USER / $ADMIN_PASS)"
        log_text $OUTPUT_SEVERITY_DEBUG "Aufräumen:  docker rm -f $APP_CT $DB_CT && docker network rm $NETWORK"
        return
    fi

    docker rm -f "$APP_CT" "$DB_CT" >/dev/null 2>&1 || true
    docker network rm "$NETWORK" >/dev/null 2>&1 || true
    rm -rf "$WORKDIR"
}
trap cleanup EXIT

occ_pure() { docker exec -u www-data "$APP_CT" php occ "$@"; }

occ() {
    if [ "$OUTPUT_SEVERITY_NUM" -gt "$OUTPUT_SEVERITY_DEBUG" ]; then
        occ_pure "$@" >/dev/null 2>&1
    else
        occ_pure "$@"
    fi
}

wait_for() {
    local desc="$1"; shift
    local i
    for i in $(seq 1 90); do
        if "$@" >/dev/null 2>&1; then return 0; fi
        sleep 2
    done
    fail "Timeout beim Warten auf: $desc"
}

# App-Verzeichnis in den Container spiegeln. Bewusst über tar statt docker cp,
# damit node_modules & Co. draußen bleiben. vendor/, js/ und css/ müssen mit.
install_app_source() {
    local src="$1"
    docker exec "$APP_CT" rm -rf "/var/www/html/custom_apps/$APP_ID"
    docker exec "$APP_CT" mkdir -p "/var/www/html/custom_apps/$APP_ID"
    tar -C "$src" \
        --exclude=node_modules --exclude=.git --exclude=.github \
        --exclude=build --exclude=tests \
        -cf - . \
        | docker exec -i "$APP_CT" tar -C "/var/www/html/custom_apps/$APP_ID" -xf -
    docker exec "$APP_CT" chown -R www-data:www-data "/var/www/html/custom_apps/$APP_ID"
}

expect_ok() {
    local path="$1" status
    status="$(curl -sS -o "$WORKDIR/body.json" -w '%{http_code}' \
        -u "$ADMIN_USER:$ADMIN_PASS" \
        -H 'Accept: application/json' \
        "http://localhost:$PORT/apps/$APP_ID$path" || echo 000)"

    if [ "$status" != "200" ]; then
        log_text $OUTPUT_SEVERITY_ERROR "--- Response ---"
        cat "$WORKDIR/body.json" 2>/dev/null
        log_text $OUTPUT_SEVERITY_ERROR ""
        fail "GET $path lieferte HTTP $status (erwartet 200)"
    fi

    # Die Controller fangen \Throwable ab und antworten teils mit HTTP 200 plus
    # error-Key, deshalb reicht der Statuscode allein nicht aus.
    if grep -q '"error"' "$WORKDIR/body.json"; then
        log_text $OUTPUT_SEVERITY_ERROR "--- Response ---"
        cat "$WORKDIR/body.json" 2>/dev/null
        log_text $OUTPUT_SEVERITY_ERROR ""
        fail "GET $path enthält einen error-Key"
    fi
    log_text $OUTPUT_SEVERITY_DEBUG "  ok: GET $path"
}

# Die eigentliche Regressionsprüfung für Issue #2: existiert der Constraint
# wirklich in der Datenbank? Das sagt einem weder app:enable noch die API.
assert_foreign_key() {
    local table="${TABLE_PREFIX}ticky_client_contacts"
    local count

    case "$DB" in
        mariadb)
            count="$(docker exec "$DB_CT" mariadb -unextcloud -pnextcloud nextcloud -N -B -e \
                "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = 'nextcloud'
                   AND TABLE_NAME = '$table'
                   AND REFERENCED_TABLE_NAME IS NOT NULL;" 2>/dev/null || echo 0)"
            ;;
        postgres)
            count="$(docker exec "$DB_CT" psql -U nextcloud -d nextcloud -tAc \
                "SELECT count(*) FROM information_schema.table_constraints
                 WHERE table_name = '$table' AND constraint_type = 'FOREIGN KEY';" \
                2>/dev/null || echo 0)"
            ;;
        sqlite)
            count="$(docker exec -u www-data "$APP_CT" php -r \
                "\$p = new PDO('sqlite:/var/www/html/data/owncloud.db');
                 echo count(\$p->query('PRAGMA foreign_key_list(\"$table\")')->fetchAll());" \
                2>/dev/null || echo 0)"
            ;;
    esac

    count="$(printf '%s' "$count" | tr -cd '0-9')"
    if [ "${count:-0}" -lt 1 ]; then
        fail "Kein Foreign Key auf $table – Issue #2 ist nicht behoben"
    fi
    log_text $OUTPUT_SEVERITY_DEBUG "  ok: Foreign Key auf $table vorhanden"
}

# ---------------------------------------------------------------- Datenbank --

log_titel $OUTPUT_SEVERITY_DEBUG "Starte Umgebung (Nextcloud $NC_VERSION, DB $DB, Präfix '${TABLE_PREFIX}')"
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

# Auf status.php warten, nicht auf einzelne Dateien: Der Entrypoint kopiert
# ~30.000 Dateien nach /var/www/html und startet Apache erst danach.
wait_for "Nextcloud-Quellen" curl -sf -o /dev/null "http://localhost:$PORT/status.php"

# occ maintenance:install kennt keine Option für das Tabellenpräfix; der Wert
# wird aus der Konfiguration gelesen und fällt sonst auf oc_ zurück. Der
# zz-Präfix im Dateinamen sorgt dafür, dass unsere Datei zuletzt greift.
if [ "$TABLE_PREFIX" != "oc_" ]; then
    log_titel $OUTPUT_SEVERITY_DEBUG "Setze abweichendes Tabellenpräfix: '${TABLE_PREFIX}'"
    docker exec -u www-data -i "$APP_CT" \
        tee /var/www/html/config/zz-prefix.config.php >/dev/null <<EOF
<?php
\$CONFIG = ['dbtableprefix' => '${TABLE_PREFIX}'];
EOF
fi

log_titel $OUTPUT_SEVERITY_DEBUG "Installiere Nextcloud"
occ maintenance:install \
    --admin-user "$ADMIN_USER" --admin-pass "$ADMIN_PASS" \
    "${INSTALL_ARGS[@]}"
occ config:system:set trusted_domains 1 --value="localhost:$PORT"
occ config:system:set loglevel --value=1 --type=integer

log_titel $OUTPUT_SEVERITY_DEBUG "Installiere Abhängigkeit: contacts"
occ app:install contacts || fail "contacts-App ließ sich nicht installieren"

# ------------------------------------------------- Optional: Upgrade-Pfad --

if [ -n "$UPGRADE_FROM" ]; then
    log_titel $OUTPUT_SEVERITY_DEBUG "Baseline: installiere $UPGRADE_FROM"
    OLD_SRC="$WORKDIR/old"
    mkdir -p "$OLD_SRC"
    git -C "$REPO_ROOT" archive "$UPGRADE_FROM" | tar -x -C "$OLD_SRC" \
        || fail "Tag/Ref '$UPGRADE_FROM' existiert nicht"

    OLD_VER="$(grep -oP '(?<=<version>)[^<]+' "$OLD_SRC/appinfo/info.xml")"
    NEW_VER="$(grep -oP '(?<=<version>)[^<]+' "$APP_SOURCE/appinfo/info.xml")"
    [ "$OLD_VER" != "$NEW_VER" ] \
        || fail "Version in info.xml unverändert ($NEW_VER) – occ upgrade täte nichts"

    install_app_source "$OLD_SRC"
    occ app:enable "$APP_ID" || fail "Baseline $UPGRADE_FROM ließ sich nicht aktivieren"

    log_titel $OUTPUT_SEVERITY_DEBUG "Spiele HEAD ein und führe Upgrade $OLD_VER -> $NEW_VER aus"
    install_app_source "$APP_SOURCE"
    occ upgrade || fail "occ upgrade fehlgeschlagen (Migration defekt)"
else
    log_titel $OUTPUT_SEVERITY_DEBUG "Frischinstallation der App"
    install_app_source "$APP_SOURCE"
    occ app:enable "$APP_ID" || fail "app:enable fehlgeschlagen"
fi

# ------------------------------------------------------------- Prüfungen --

log_titel $OUTPUT_SEVERITY_DEBUG "Prüfe App-Status"
APP_STATE="$(occ_pure config:app:get "$APP_ID" enabled --default-value=no | tr -d '[:space:]')"
[ "$APP_STATE" = "yes" ] || fail "App ist nicht aktiviert (Status: '$APP_STATE')"
log_text $OUTPUT_SEVERITY_DEBUG "  ok: App ist aktiviert"

log_titel $OUTPUT_SEVERITY_DEBUG "Prüfe Schema"
assert_foreign_key
occ db:add-missing-primary-keys
occ db:add-missing-columns
occ db:add-missing-indices

log_titel $OUTPUT_SEVERITY_DEBUG "Smoke-Test der API"
expect_ok "/api/v1/clients"

log_titel $OUTPUT_SEVERITY_DEBUG "Product persistence checks"
docker exec -i -u www-data "$APP_CT" php < "$REPO_ROOT/tests/integration/products.php"
docker exec -i -u www-data "$APP_CT" php < "$REPO_ROOT/tests/integration/client-counts.php"
docker exec -i -u www-data "$APP_CT" php < "$REPO_ROOT/tests/integration/follow-up.php"
docker exec -i -u www-data -e "API_USER=$ADMIN_USER" -e "API_PASSWORD=$ADMIN_PASS" \
    "$APP_CT" php < "$REPO_ROOT/tests/integration/products-api.php"
docker exec -i -u www-data -e "API_USER=$ADMIN_USER" -e "API_PASSWORD=$ADMIN_PASS" \
    "$APP_CT" php < "$REPO_ROOT/tests/integration/clients-api.php"

docker exec -i -u www-data -e "API_USER=$ADMIN_USER" -e "API_PASSWORD=$ADMIN_PASS" \
    "$APP_CT" php < "$REPO_ROOT/tests/integration/note-contact-api.php"
docker exec -i -u www-data -e "API_USER=$ADMIN_USER" -e "API_PASSWORD=$ADMIN_PASS" \
    "$APP_CT" php < "$REPO_ROOT/tests/integration/client-products-api.php"
docker exec -i -u www-data -e "API_USER=$ADMIN_USER" -e "API_PASSWORD=$ADMIN_PASS" \
    "$APP_CT" php < "$REPO_ROOT/tests/integration/product-options.php"

log_titel $OUTPUT_SEVERITY_DEBUG "Prüfe Log auf Fehler"
LOGFILE="$WORKDIR/nextcloud.log"
docker exec "$APP_CT" sh -c 'cat /var/www/html/data/nextcloud.log 2>/dev/null || true' > "$LOGFILE"
if grep -E '"level":[34]' "$LOGFILE" 2>/dev/null | grep -q "$APP_ID"; then
    log_text $OUTPUT_SEVERITY_ERROR "--- Relevante Log-Zeilen ---"
    grep -E '"level":[34]' "$LOGFILE" | grep "$APP_ID"
    fail "Fehler-Level-Einträge der App im nextcloud.log"
fi
log_text $OUTPUT_SEVERITY_DEBUG "  ok: keine Fehler im Log"

if [ -n "$UPGRADE_FROM" ]; then
    log_titel $OUTPUT_SEVERITY_INFO "Erfolgreich Upgrade: NC $NC_VERSION / $DB / Präfix '${TABLE_PREFIX}'"
else
    log_titel $OUTPUT_SEVERITY_INFO "Erfolgreich: NC $NC_VERSION / $DB / Präfix '${TABLE_PREFIX}'"
fi
cleanup

#!/usr/bin/env bash
#
# Run all integration tests locally for all supported Nextcloud versions and databases.

# Fresh installation
CURRENT_DIR=$(dirname "$0")
for NC_VERSION in 35 34 33 32; do
    for DB in postgres mariadb sqlite; do
        NC_VERSION=${NC_VERSION} DB=${DB} OUTPUT_SEVERITY=info ${CURRENT_DIR}/run.sh
    done
done

# Upgrade from previous version (doesn't support Nextcloud 34 and above)
for NC_VERSION in 33 32; do
    for DB in postgres mariadb sqlite; do
        NC_VERSION=${NC_VERSION} DB=${DB} UPGRADE_FROM=v0.1.1 OUTPUT_SEVERITY=info ${CURRENT_DIR}/run.sh upgrade
    done
done
#!/bin/sh
set -eu

occ() {
    php /var/www/html/occ "$@"
}

if [ ! -f /var/www/html/custom_apps/ticky_crm/js/ticky_crm-main.mjs ]; then
    echo 'Ticky CRM: build frontend assets before opening the app (npm ci && npm run build).'
fi

occ config:system:set debug --type=boolean --value=true
occ config:system:set loglevel --type=integer --value=0
occ config:system:set appstoreenabled --type=boolean --value=true

# app:enable is idempotent; install Contacts only when it is missing.
if [ ! -f /var/www/html/custom_apps/contacts/appinfo/info.xml ] &&
   [ ! -f /var/www/html/apps/contacts/appinfo/info.xml ]; then
    occ app:install contacts
fi
occ app:enable contacts
occ app:enable ticky_crm

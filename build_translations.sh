#!/bin/bash
# scripts/build-translations.sh

for lang in de en; do
  JSON="/var/www/html/custom_apps/ticky_crm/l10n/${lang}.json"
  JS="/var/www/html/custom_apps/ticky_crm/l10n/${lang}.js"

  TRANSLATIONS=$(php -r "
    \$data = json_decode(file_get_contents('${JSON}'), true);
    echo json_encode(\$data['translations'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  ")

  PLURAL=$(php -r "
    \$data = json_decode(file_get_contents('${JSON}'), true);
    echo \$data['pluralForm'];
  ")

  echo "OC.L10N.register(" > "$JS"
  echo "    \"ticky_crm\"," >> "$JS"
  echo "    ${TRANSLATIONS}," >> "$JS"
  echo "\"${PLURAL}\");" >> "$JS"

  echo "${lang}.js generiert"
done
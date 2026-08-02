# TODO
Points found to be changed:

## Build
- Move build-release.js into scripts folder and change in package.json scripts/release
- Rename package.json scripts/build -> scripts/build:code
- Add package.json scripts/build -> npm run build:code && npm run build:translations
- PHPStan:
    - Add symbolic link from nextcloud dir to "nextcloud"
    - Change phpstan.neon from ../.. to ./nextcloud
    - Add nextcloud to .gitignore
    - Document this in DEVELOPER.md

## Database
- why ticky_client_contacts has no cascaded delete constraint to oc_card?
- Fatabase: Common id (PK & FK) definition: autoincrement, unsigned, length=11

## Generic notes
- Rename table ticky_client_notes to Table: ticky_notes
- Rename field client_id to table_id
- Add column table_name string
- Update all empty table_name to 'ticky_clients'
- Change index client_id -> table_name, table_id
- Change constraint client_id -> table_id table_name='ticky_clients'
- Change add, list of notes (add table_name)

## Extend generic notes
- Rename field type to content_mimetype
- content_mimetype -> type using translations
- Change add/update/list
- Support different displays for mimetypes (plain text, markdown, html, email, e.g.)
- Support download for unsupported mimetypes
- Support different editors for mimetypes (plain text, markdown, html, e.g.)
- Add column ref_type string mail, call
- Add column ref_value string email-addr, phone-number

# Developer documentation

## Docker development environment

Requires Docker Compose (Linux containers), Node.js and npm. Run from the repo root.

1. Copy `.env.example` to `.env` (`Copy-Item` on PowerShell, `cp` on Linux/macOS).
2. Set `NC_VERSION` (default: `33`, supported: `32`-`35`) and `NC_PORT` (default: `8080`).
3. Build and start:

```sh
npm ci
npm run build
docker compose up -d --build --wait
```

Open http://localhost:8080. Login: `admin` / `admin-dev-password` (local development only).
Contacts and Ticky CRM are activated automatically; the first start requires internet.
The repo is mounted directly; Nextcloud and MariaDB data persist in Docker volumes.

Use `npm run dev` to rebuild frontend changes, then reload the browser.
For translations, run `npm run build:translations`.

```sh
docker compose logs -f nextcloud
docker compose exec --user www-data nextcloud php occ status
docker compose down
```

`down` preserves data; `down --volumes` permanently deletes the stack's data.
Nextcloud downgrades require fresh volumes. For parallel versions, use a separate
project and port via `.env.nc32` (`NC_VERSION=32`, `NC_PORT=8082`):

```sh
docker compose --env-file .env.nc32 -p ticky-crm-nc32 up -d --build --wait
```

Keep the same `--env-file` and `-p` options for subsequent commands.

## Seed development data

Create sample customers, catalog products and customer-product links:

```sh
docker compose exec --user www-data nextcloud php occ ticky_crm:dev:seed
```

The defaults create **24 customers, 12 products and 36 product assignments**.
All numbers and names are marked with the `DEV` prefix. The command is intended
for development and demo installations; it creates records in the selected instance.

Preview or customize the dataset:

```sh
docker compose exec --user www-data nextcloud php occ ticky_crm:dev:seed --dry-run
docker compose exec --user www-data nextcloud php occ ticky_crm:dev:seed --clients=80 --products=20 --prefix=DEMO --user=admin
```

On a regular Nextcloud installation, run `php occ ticky_crm:dev:seed` as the web
server user from the Nextcloud directory. `--user` specifies an existing Nextcloud
account used to attribute activity entries (default: `admin`). Counts accept 1-1000;
prefixes accept up to 24 uppercase letters, digits, underscores and hyphens,
starting with a letter.

The dataset includes companies and individuals, leads, active and inactive customers,
automatic green/yellow/red and unknown contact status, manual overrides, missing
contact details and multiple addresses. Products vary in configured types, units,
categories, procurement, currencies, taxes and purchase/sales prices; every fifth
product is archived. Customers receive zero to three active catalog products,
including fractional quantities and independently agreed prices.

Contact dates follow the current global intervals, and product values use the
current configured choices. Global settings are preserved. All records are saved
in one transaction. Existing numbers abort the command before any data is created;
use another `--prefix` for a separate dataset. `--dry-run` validates options and
number availability without creating records.

## IDE
Use an IDE you like, but check if it can handle Markdown and Mermaid correctly.

## Commands

### NPM
```
npm run dev
npm run lint
npm run lint:fix
npm run build
npm run build:code
npm run build:translations
```

### PHPStan (PHP TEsting framework)
```
vendor/phpstan/phpstan/phpstan
```

### Nextclous
```
php occ files:cleanup
```

## Requirements

- **NodeJS:** v24.x
- **NPM:** v10.x to 11.x
- Ev. **Docker:** (Integration-Tests)
    - [https://docs.docker.com/engine/install/debian/]

## On boarding

- Clone repository from gitlab ```git clone https://github.com/IdentSpace/ticky_crm.git```
- Do an ```npm i``` to install all packages - use ```npm i --force``` if you get some dependency errors
- Try ```npm run build:code``` and ```npm run build:translations```
- If you have a local Nextcloud instance running (Linux):
    - Add symbolic link ```ln -s <source dir> <target dir>``` where source dir is your cloned repository and target dir is in your www/nextcloud/apps folder e.g. ```ln -s /home/user/ticky_crm /var/www/nextcloud/apps/ticky_crm```
    - Ensure that www-data has write access to this directory e.g. /home/user/ticky_crm
    - Now you should be able to activate the app ticky_crm in the Nextcloud
    - Download and install composer (PHP) from https://getcomposer.org/
    - Execute ```composer i```
- To run integration tests or release you need docker to be installed:
    - Execute ```./tests/integration/run.sh``` (be sure your local acoount is member of the docker group or run the command as root)
    - Execute ```./tests/integration/local_run_all.sh``` to run all integration test of Github locally

## Testing

Persistence and API checks (run `occ upgrade` first):
```sh
docker compose exec --user www-data nextcloud php occ upgrade
docker compose exec --user www-data nextcloud php custom_apps/ticky_crm/tests/integration/products.php
docker compose exec --user www-data nextcloud php custom_apps/ticky_crm/tests/integration/products-api.php
docker compose exec --user www-data nextcloud php custom_apps/ticky_crm/tests/integration/clients-api.php
docker compose exec --user www-data nextcloud php custom_apps/ticky_crm/tests/integration/client-products-api.php
docker compose exec --user www-data nextcloud php custom_apps/ticky_crm/tests/integration/product-options.php
```

### Local
* ```npm run lint``` -> Runs linting test on src directory
* ```npm run test:phpstan``` -> Runs PHPStan on src directory
* ```npm run test:integration``` -> Runs integration tests with different variations on docker
* ```npm run test``` -> Runs build and ALL tests
### Github
* build -> Test building
* lint -> Runs linting test on src directory
* PHPStan -> Runs PHPStan on src directory
* integration -> Runs integration tests with different variations on docker

# Developer documentation

## Docker development environment

Requires Docker Compose (Linux containers), Node.js and npm. Run from the repo root.

1. Copy `.env.example` to `.env` (`Copy-Item` on PowerShell, `cp` on Linux/macOS).
2. Set `NC_VERSION` (default: `33`, supported: `32`?`34`) and `NC_PORT` (default: `8080`).
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

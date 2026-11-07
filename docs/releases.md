# Releases from the console

## One-time setup

Use Node.js 22 or newer, Git, Docker and GitHub CLI (`gh`). Install the GitHub CLI on Windows with `winget install --id GitHub.cli -e`, reopen your terminal and run `gh auth login`.

Keep your existing app certificate and private key in `.nextcloud/ticky_crm.crt` and `.nextcloud/ticky_crm.key`. These files are ignored by Git and never included in the release archive. The Docker user `www-data` must be able to read them and write to `build/artifacts/ticky_crm`.

Start the development Nextcloud container. By default the scripts use the `nextcloud` service from this repository's `compose.yaml`. For your existing external container, set this in PowerShell:

```powershell
$env:NC_CONTAINER = 'nextcloud-dev-nextcloud-1'
```

The app must be mounted at `/var/www/html/custom_apps/ticky_crm`, including its `build/` directory. Override the mount path with `NC_CONTAINER_APP_PATH` if necessary.

Obtain your API token from the [Nextcloud App Store account settings](https://apps.nextcloud.com/settings/user). Load it into `NC_APPSTORE_TOKEN` from your password manager or a hidden prompt (PowerShell 7):

```powershell
$env:NC_APPSTORE_TOKEN = Read-Host 'Nextcloud App Store token' -MaskInput
```

The script reads the process environment. It does not automatically load `.env` files. Never commit a token or private key.

## Prepare a release

1. Update `appinfo/info.xml`, `package.json`, the root versions in `package-lock.json`, and `composer.json` to the same stable `x.y.z` version.
2. Update `CHANGELOG.md`, run the project's checks, and commit all changes. The scripts require a clean working tree so the tag identifies the source used for the build.
3. Install dependencies (`npm ci` and `composer install --no-dev --optimize-autoloader`) and start Docker. Restore development dependencies afterwards with `composer install`.
4. Run:

```powershell
npm run release
```

This builds JavaScript and translations, copies the app into `build/artifacts/ticky_crm`, signs its files with `occ integrity:sign-app`, creates `build/ticky_crm.tar.gz`, and signs the archive with SHA-512. The archive signature is saved as `build/ticky_crm.tar.gz.sig`. `build/release.json` records the source commit, version, archive hash and signature. No tag or remote release is created by this command.

The script copies the existing `vendor/` directory, if present, and refuses a vendor directory containing development dependencies. Any build or signing error stops the command with a nonzero exit status.

## Publish

First validate the prepared archive without network access or Git changes:

```powershell
npm run release:publish -- --dry-run
```

Then publish:

```powershell
npm run release:publish
```

This command performs the following public actions:

1. Creates `v<version>` at the recorded commit and pushes the tag to `origin` (`IdentSpace/ticky_crm`).
2. Creates a public GitHub release with generated notes and uploads `ticky_crm.tar.gz`.
3. Downloads the public archive and checks its SHA-256 hash against the local archive.
4. Submits its HTTPS URL and Base64 SHA-512 signature to the Nextcloud App Store API as a stable release.

Existing tags must identify the same commit. Existing assets are never overwritten. If publishing fails partway through, leave `build/` intact and repeat `npm run release:publish`; an existing release/asset is reused and verified. Rebuilding creates a new archive and may no longer match an already uploaded asset. The App Store endpoint can create or update a release. Remote actions are not rolled back after an error.

Local preparation needs the private key; publishing only needs the certificate, prepared archive/metadata, GitHub authentication and App Store token. Custom local paths can be set with `NC_SIGNING_KEY` and `NC_SIGNING_CERT`. If those files are elsewhere inside Docker, also set `NC_CONTAINER_SIGNING_KEY` and `NC_CONTAINER_SIGNING_CERT` to the matching container paths.

References: [GitHub CLI releases](https://cli.github.com/manual/gh_release_create), [Nextcloud signing](https://nextcloudappstore.readthedocs.io/en/latest/developer.html), [App Store API](https://nextcloudappstore.readthedocs.io/en/stable/api/restapi.html).

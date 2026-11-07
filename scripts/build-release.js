import fs from 'node:fs'
import path from 'node:path'
import { createHash, sign, verify } from 'node:crypto'
import * as tar from 'tar'
import { root, run, version, git, assertClean } from './release-common.js'

const build = path.join(root, 'build')
const staging = path.join(build, 'artifacts', 'ticky_crm')

try {
    const releaseVersion = version()
    assertClean()
    const installedFile = path.join(root, 'vendor/composer/installed.json')
    if (fs.existsSync(path.join(root, 'vendor'))) {
        if (!fs.existsSync(installedFile)) throw new Error('Cannot verify vendor dependencies. Run composer install --no-dev --optimize-autoloader first.')
        const installed = JSON.parse(fs.readFileSync(installedFile, 'utf8'))
        if (installed.dev !== false) throw new Error('vendor contains development dependencies. Run composer install --no-dev --optimize-autoloader before release; restore with composer install afterwards.')
    }
    const commit = git(['rev-parse', 'HEAD'])
    const key = fs.readFileSync(process.env.NC_SIGNING_KEY || path.join(root, '.nextcloud/ticky_crm.key'))
    const certificate = fs.readFileSync(process.env.NC_SIGNING_CERT || path.join(root, '.nextcloud/ticky_crm.crt'))
    fs.mkdirSync(build, { recursive: true })
    fs.rmSync(path.join(build, 'release.json'), { force: true })
    run(process.platform === 'win32' ? 'npm.cmd' : 'npm', ['run', 'build'])
    fs.rmSync(staging, { recursive: true, force: true })
    fs.mkdirSync(staging, { recursive: true })
    for (const name of ['appinfo', 'lib', 'l10n', 'js', 'css', 'templates', 'img', 'docs', 'vendor', 'CHANGELOG.md', 'LICENSE', 'LICENSE.md', 'README.md']) {
        const source = path.join(root, name)
        if (fs.existsSync(source)) fs.cpSync(source, path.join(staging, name), { recursive: true })
    }
    fs.rmSync(path.join(staging, 'appinfo/signature.json'), { force: true })
    const containerRoot = process.env.NC_CONTAINER_APP_PATH || '/var/www/html/custom_apps/ticky_crm'
    const args = ['php', 'occ', 'integrity:sign-app',
        `--privateKey=${process.env.NC_CONTAINER_SIGNING_KEY || `${containerRoot}/.nextcloud/ticky_crm.key`}`,
        `--certificate=${process.env.NC_CONTAINER_SIGNING_CERT || `${containerRoot}/.nextcloud/ticky_crm.crt`}`,
        `--path=${containerRoot}/build/artifacts/ticky_crm`]
    if (process.env.NC_CONTAINER) {
        run('docker', ['exec', '-u', 'www-data', process.env.NC_CONTAINER, ...args])
    } else {
        run('docker', ['compose', 'exec', '-T', '-u', 'www-data', 'nextcloud', ...args])
    }
    if (!fs.existsSync(path.join(staging, 'appinfo/signature.json'))) throw new Error('App signature was not created.')
    const archive = path.join(build, 'ticky_crm.tar.gz')
    await tar.c({ gzip: true, file: archive, cwd: path.dirname(staging) }, ['ticky_crm'])
    const bytes = fs.readFileSync(archive)
    const signature = sign('sha512', bytes, key).toString('base64')
    if (!verify('sha512', bytes, certificate, Buffer.from(signature, 'base64'))) throw new Error('Signing key and certificate do not match.')
    assertClean()
    if (git(['rev-parse', 'HEAD']) !== commit) throw new Error('Git HEAD changed during the build. Run npm run release again.')
    fs.writeFileSync(path.join(build, 'ticky_crm.tar.gz.sig'), signature + '\n')
    fs.writeFileSync(path.join(build, 'release.json'), JSON.stringify({
        version: releaseVersion, tag: `v${releaseVersion}`, commit,
        sha256: createHash('sha256').update(bytes).digest('hex'), signature,
    }, null, 2) + '\n')
    console.log(`Release ${releaseVersion} prepared in build/. Publish with npm run release:publish.`)
} catch (error) {
    console.error(error.message)
    process.exitCode = 1
}

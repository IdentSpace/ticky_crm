import fs from 'node:fs'
import path from 'node:path'
import { createHash, verify } from 'node:crypto'
import { root, run, version, git, assertClean } from './release-common.js'

const hash = (bytes) => createHash('sha256').update(bytes).digest('hex')
const request = async (url, options = {}) => {
    const response = await fetch(url, { ...options, signal: AbortSignal.timeout(120000) })
    if (!response.ok) throw new Error(`HTTP ${response.status} from ${new URL(url).hostname}: ${(await response.text()).slice(0, 1000)}`)
    return response
}

try {
    const dryRun = process.argv.includes('--dry-run')
    if (process.argv.slice(2).some((arg) => arg !== '--dry-run')) throw new Error('Only --dry-run is supported.')
    const releaseVersion = version()
    assertClean()
    const metadata = JSON.parse(fs.readFileSync(path.join(root, 'build/release.json'), 'utf8'))
    const archive = path.join(root, 'build/ticky_crm.tar.gz')
    const bytes = fs.readFileSync(archive)
    if (metadata.version !== releaseVersion || metadata.tag !== `v${releaseVersion}` || metadata.commit !== git(['rev-parse', 'HEAD'])) {
        throw new Error('Release metadata is stale. Run npm run release again.')
    }
    if (hash(bytes) !== metadata.sha256) throw new Error('Archive changed after signing. Run npm run release again.')
    const certificate = fs.readFileSync(process.env.NC_SIGNING_CERT || path.join(root, '.nextcloud/ticky_crm.crt'))
    if (!verify('sha512', bytes, certificate, Buffer.from(metadata.signature, 'base64'))) throw new Error('Archive signature is invalid.')
    if (bytes.length > 20 * 1024 * 1024) throw new Error('Archive exceeds the App Store 20 MiB limit.')
    const repo = 'IdentSpace/ticky_crm'
    const download = `https://github.com/${repo}/releases/download/${metadata.tag}/ticky_crm.tar.gz`
    if (dryRun) {
        console.log(`Validated ${metadata.tag} at ${metadata.commit}.\nWould publish ${download} and register it in the Nextcloud App Store.\nNo remote requests or Git changes made.`)
    } else {
        const token = process.env.NC_APPSTORE_TOKEN
        if (!token) throw new Error('Set NC_APPSTORE_TOKEN to your Nextcloud App Store API token.')
        run('gh', ['auth', 'status'])
        const origin = git(['remote', 'get-url', 'origin'])
        if (!/^(?:git@github\.com:|https:\/\/github\.com\/)IdentSpace\/ticky_crm(?:\.git)?\/?$/i.test(origin)) throw new Error('origin must point to IdentSpace/ticky_crm on GitHub.')
        const localTags = git(['tag', '--list', metadata.tag])
        if (localTags) {
            if (git(['rev-parse', `${metadata.tag}^{commit}`]) !== metadata.commit) throw new Error('Existing local tag points to a different commit.')
        }
        const remoteTags = git(['ls-remote', '--tags', 'origin', `refs/tags/${metadata.tag}`, `refs/tags/${metadata.tag}^{}`])
        if (remoteTags) {
            const lines = remoteTags.split(/\r?\n/)
            const resolved = lines.find((line) => line.endsWith('^{}')) || lines[0]
            if (resolved.split(/\s/)[0] !== metadata.commit) throw new Error('Existing remote tag points to a different commit.')
        }
        // Listing must succeed: authentication/network errors must not be treated as an absent release.
        const releases = JSON.parse(run('gh', ['api', '--paginate', '--slurp', `repos/${repo}/releases?per_page=100`], true)).flat()
        const existing = releases.find((release) => release.tag_name === metadata.tag)
        if (existing?.draft || existing?.prerelease) throw new Error('Existing release is a draft/prerelease; stable publishing stopped.')
        if (!localTags) git(['tag', metadata.tag, metadata.commit])
        git(['push', 'origin', `refs/tags/${metadata.tag}`])
        if (!existing) {
            run('gh', ['release', 'create', metadata.tag, archive, '--repo', repo, '--verify-tag', '--title', `Ticky CRM ${releaseVersion}`, '--generate-notes'])
        } else if (!existing.assets.some((asset) => asset.name === 'ticky_crm.tar.gz')) {
            run('gh', ['release', 'upload', metadata.tag, archive, '--repo', repo])
        }
        // Verify the publicly downloadable bytes before sending their URL to the store.
        const uploaded = Buffer.from(await (await request(download)).arrayBuffer())
        if (hash(uploaded) !== metadata.sha256) throw new Error('GitHub archive differs from the local archive. Nothing submitted to the App Store.')
        const response = await request('https://apps.nextcloud.com/api/v1/apps/releases', {
            method: 'POST', headers: { Authorization: `Token ${token}`, 'Content-Type': 'application/json' },
            body: JSON.stringify({ download, signature: metadata.signature, nightly: false }),
        })
        console.log(`Published ${metadata.tag}: ${download}\nApp Store accepted the release (HTTP ${response.status}).`)
    }
} catch (error) {
    console.error(error.message)
    process.exitCode = 1
}

import test from 'node:test'
import assert from 'node:assert/strict'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { execFileSync, spawnSync } from 'node:child_process'
import { createHash, generateKeyPairSync, sign } from 'node:crypto'

const { privateKey, publicKey } = generateKeyPairSync('rsa', { modulusLength: 2048 })

function fixture(t) {
    const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'ticky-release-test-'))
    t.after(() => fs.rmSync(directory, { recursive: true, force: true }))
    const write = (name, value) => {
        fs.mkdirSync(path.dirname(path.join(directory, name)), { recursive: true })
        fs.writeFileSync(path.join(directory, name), value)
    }
    for (const name of ['release-common.js', 'publish-release.js']) {
        write(`scripts/${name}`, fs.readFileSync(new URL(`../../scripts/${name}`, import.meta.url)))
    }
    write('package.json', JSON.stringify({ type: 'module', version: '0.4.0' }))
    write('composer.json', JSON.stringify({ version: '0.4.0' }))
    write('package-lock.json', JSON.stringify({ version: '0.4.0', packages: { '': { version: '0.4.0' } } }))
    write('appinfo/info.xml', '<info><version>0.4.0</version></info>')
    write('.gitignore', 'build/\n.nextcloud/\n')
    const git = (args) => execFileSync('git', args, { cwd: directory, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim()
    git(['init', '--quiet'])
    git(['add', '.'])
    git(['-c', 'user.name=Release Test', '-c', 'user.email=test@example.com', '-c', 'commit.gpgsign=false', 'commit', '--quiet', '-m', 'Fixture'])
    const bytes = Buffer.from('fixture archive')
    const metadata = { version: '0.4.0', tag: 'v0.4.0', commit: git(['rev-parse', 'HEAD']),
        sha256: createHash('sha256').update(bytes).digest('hex'), signature: sign('sha512', bytes, privateKey).toString('base64') }
    write('build/release.json', JSON.stringify(metadata))
    write('build/ticky_crm.tar.gz', bytes)
    write('.nextcloud/ticky_crm.crt', publicKey.export({ type: 'spki', format: 'pem' }))
    const run = (args = ['--dry-run']) => {
        const env = { ...process.env, NC_APPSTORE_TOKEN: '', NC_SIGNING_CERT: '' }
        const result = spawnSync(process.execPath, ['scripts/publish-release.js', ...args], { cwd: directory, encoding: 'utf8', env })
        return { status: result.status, output: result.stdout + result.stderr }
    }
    return { write, run, git, metadata }
}

test('dry-run validates a signed archive without creating tags', (t) => {
    const f = fixture(t)
    const result = f.run()
    assert.equal(result.status, 0, result.output)
    assert.match(result.output, /No remote requests/)
    assert.equal(f.git(['tag', '--list']), '')
    assert.equal(f.git(['status', '--porcelain']), '')
})

test('modified archive is rejected', (t) => {
    const f = fixture(t)
    f.write('build/ticky_crm.tar.gz', 'tampered archive')
    const result = f.run()
    assert.equal(result.status, 1)
    assert.match(result.output, /Archive changed/)
})

test('invalid signature is rejected even with a matching archive hash', (t) => {
    const f = fixture(t)
    f.write('build/release.json', JSON.stringify({ ...f.metadata, signature: Buffer.alloc(256).toString('base64') }))
    const result = f.run()
    assert.equal(result.status, 1)
    assert.match(result.output, /signature is invalid/)
})

test('metadata from another commit is rejected', (t) => {
    const f = fixture(t)
    f.write('build/release.json', JSON.stringify({ ...f.metadata, commit: '0'.repeat(40) }))
    const result = f.run()
    assert.equal(result.status, 1)
    assert.match(result.output, /metadata is stale/)
})

test('dirty working tree is rejected', (t) => {
    const f = fixture(t)
    f.write('uncommitted.txt', 'pending change')
    const result = f.run()
    assert.equal(result.status, 1)
    assert.match(result.output, /Commit or stash/)
})

test('inconsistent versions are rejected', (t) => {
    const f = fixture(t)
    f.write('composer.json', JSON.stringify({ version: '0.3.0' }))
    const result = f.run()
    assert.equal(result.status, 1)
    assert.match(result.output, /composer.json version must match/)
})

test('missing token stops publishing before Git mutations or network access', (t) => {
    const f = fixture(t)
    const result = f.run([])
    assert.equal(result.status, 1)
    assert.match(result.output, /Set NC_APPSTORE_TOKEN/)
    assert.equal(f.git(['tag', '--list']), '')
})

test('unknown options fail closed', (t) => {
    const f = fixture(t)
    const result = f.run(['--dryrun'])
    assert.equal(result.status, 1)
    assert.match(result.output, /Only --dry-run/)
})

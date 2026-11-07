import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { execFileSync } from 'node:child_process'

export const root = fileURLToPath(new URL('../', import.meta.url))
export function run(command, args, capture = false) {
    return execFileSync(command, args, { cwd: root, encoding: 'utf8', stdio: capture ? ['ignore', 'pipe', 'pipe'] : 'inherit',
        shell: process.platform === 'win32' && command === 'npm.cmd',
    })?.trim()
}
export const git = (args) => run('git', args, true)
export function assertClean() {
    if (git(['status', '--porcelain'])) throw new Error('Commit or stash all changes before preparing/publishing a release.')
}
export function version() {
    const xml = fs.readFileSync(path.join(root, 'appinfo/info.xml'), 'utf8')
    const value = xml.match(/<version>([^<]+)<\/version>/)?.[1]
    if (!value || !/^\d+\.\d+\.\d+$/.test(value)) throw new Error('Expected a stable x.y.z version in appinfo/info.xml.')
    for (const name of ['package.json', 'composer.json']) {
        if (JSON.parse(fs.readFileSync(path.join(root, name), 'utf8')).version !== value) throw new Error(`${name} version must match appinfo/info.xml (${value}).`)
    }
    const lock = JSON.parse(fs.readFileSync(path.join(root, 'package-lock.json'), 'utf8'))
    if (lock.version !== value || lock.packages[''].version !== value) throw new Error('package-lock.json version must match appinfo/info.xml.')
    return value
}

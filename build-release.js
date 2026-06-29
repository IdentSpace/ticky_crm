import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import * as tar from 'tar'
import { execSync } from 'child_process' // Neu für den OCC-Aufruf

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const appName = 'ticky_crm'
const buildDir = path.join(__dirname, 'build')
const artifactDir = path.join(buildDir, 'artifacts', appName)

// WICHTIG: Ein temporärer Ordner parallel zu deiner App, den Nextcloud im Container sehen kann
// Da dein Hauptordner in 'custom_apps/ticky_crm' gemountet ist, liegt dieser hier in 'custom_apps/ticky_crm_release'
const dockerSignDir = path.join(__dirname, '..', `${appName}_release`)
const dockerSignAppinfo = path.join(dockerSignDir, 'appinfo')

async function createRelease() {
    console.log(`🚀 Starte sauberen Build für ${appName}...`)

    // 1. Alte Ordner aufräumen
    if (fs.existsSync(buildDir)) fs.rmSync(buildDir, { recursive: true, force: true })
    if (fs.existsSync(dockerSignDir)) fs.rmSync(dockerSignDir, { recursive: true, force: true })

    fs.mkdirSync(artifactDir, { recursive: true })
    fs.mkdirSync(dockerSignDir, { recursive: true })

    // 2. Nur produktive Dateien in den Docker-Signierordner kopieren (OHNE node_modules/.nextcloud)
    const foldersToCopy = ['appinfo', 'lib', 'l10n', 'js', 'css', 'templates', 'img', 'vendor']
    const filesToCopy = ['CHANGELOG.md', 'LICENSE.md', 'README.md']

    foldersToCopy.forEach((folder) => {
        const src = path.join(__dirname, folder)
        if (fs.existsSync(src)) fs.cpSync(src, path.join(dockerSignDir, folder), { recursive: true })
    })
    filesToCopy.forEach((file) => {
        const src = path.join(__dirname, file)
        if (fs.existsSync(src)) fs.cpSync(src, path.join(dockerSignDir, file))
    })

    console.log('🔒 Rufe Docker auf, um die App im sauberen Ordner zu signieren...')

    // 3. OCC im Container ausführen (Zertifikate liegen in deiner normalen App unter .nextcloud)
    // Wir nutzen die Pfade, wie sie IM CONTAINER (unter custom_apps) ankommen
    try {
        const containerKey = `/var/www/html/custom_apps/${appName}/.nextcloud/${appName}.key`
        const containerCrt = `/var/www/html/custom_apps/${appName}/.nextcloud/${appName}.crt`
        const containerReleasePath = `/var/www/html/custom_apps/${appName}_release`

        // Tausche 'nextcloud-docker-container-name' mit dem echten Namen deines Docker-Containers aus!
        const occCommand = `docker exec -u www-data nextcloud-dev-nextcloud-1 php occ integrity:sign-app --privateKey=${containerKey} --certificate=${containerCrt} --path=${containerReleasePath}`

        execSync(occCommand, { stdio: 'inherit' })
        console.log('✅ Signierung im Container erfolgreich!')
    } catch (error) {
        console.error('❌ Fehler beim Signieren im Container:', error)
        return
    }

    // 4. Jetzt kopieren wir alles aus dem Signierordner (inklusive der neuen signature.json!) in unseren finalen Artefakt-Ordner
    fs.cpSync(dockerSignDir, artifactDir, { recursive: true })

    console.log('📂 Archiv wird gepackt...')

    // 5. Als .tar.gz archivieren
    try {
        await tar.c(
            {
                gzip: true,
                file: path.join(buildDir, `${appName}.tar.gz`),
                cwd: path.join(buildDir, 'artifacts'),
            },
            [appName],
        )
        console.log(`✨ Fertig! Dein Nextcloud-Release liegt unter: build/${appName}.tar.gz`)
    } catch (error) {
        console.error('❌ Fehler beim Packen des Archivs:', error)
    } finally {
        // Aufräumen: Temporären Signierordner wieder löschen
        if (fs.existsSync(dockerSignDir)) {
            fs.rmSync(dockerSignDir, { recursive: true, force: true })
        }
    }
}

createRelease()
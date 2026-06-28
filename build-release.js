import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import * as tar from 'tar'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const appName = 'ticky_crm'
const buildDir = path.join(__dirname, 'build')
const artifactDir = path.join(buildDir, 'artifacts', appName)

async function createRelease() {
    console.log(`🚀 Starte Windows-freundlichen Build für ${appName}...`)

    // 1. Alten Build-Ordner löschen und neu aufbauen
    if (fs.existsSync(buildDir)) {
        fs.rmSync(buildDir, { recursive: true, force: true })
    }
    fs.mkdirSync(artifactDir, { recursive: true })

    // 2. Zu kopierende Ordner und Dateien definieren
    const foldersToCopy = ['appinfo', 'lib', 'l10n', 'js', 'css', 'templates', 'img', 'vendor']
    const filesToCopy = ['CHANGELOG.md', 'LICENSE.md', 'README.md']

    // Ordner kopieren
    foldersToCopy.forEach((folder) => {
        const src = path.join(__dirname, folder)
        if (fs.existsSync(src)) {
            fs.cpSync(src, path.join(artifactDir, folder), { recursive: true })
        }
    })

    // Einzeldateien kopieren
    filesToCopy.forEach((file) => {
        const src = path.join(__dirname, file)
        if (fs.existsSync(src)) {
            fs.cpSync(src, path.join(artifactDir, file))
        }
    })

    console.log('📂 Dateien erfolgreich zusammengestellt. Packe Archiv...')

    // 3. Als .tar.gz archivieren (Wechselt virtuell in den 'artifacts' Ordner)
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
    }
}

createRelease()
import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))

const LANGUAGES = ['de', 'en'];
const L10N_DIR = path.join(__dirname, '..', 'l10n');

LANGUAGES.forEach(lang => {
    const jsonPath = path.join(L10N_DIR, `${lang}.json`);
    const jsPath = path.join(L10N_DIR, `${lang}.js`);

    if (!fs.existsSync(jsonPath)) {
        console.error(`❌ ${lang}.json nicht gefunden unter: ${jsonPath}`);
        return;
    }

    const { translations, pluralForm } = JSON.parse(fs.readFileSync(jsonPath, 'utf8'));

    const jsContent = `OC.L10N.register(
    "ticky_crm",
    ${JSON.stringify(translations, null, 2)},
    "${pluralForm}"
);`;

    fs.writeFileSync(jsPath, jsContent);
    console.log(`✅ ${lang}.js generiert`);
});
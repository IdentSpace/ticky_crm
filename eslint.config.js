import pluginVue from 'eslint-plugin-vue'
import globals from 'globals'

export default [
    // Nutzt die empfohlenen Regeln für Vue 3
    ...pluginVue.configs['flat/recommended'],

    {
        files: ['**/*.js', '**/*.vue'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                ...globals.browser,
                ...globals.node,
                // Nextcloud-spezifische globale Variablen erlauben
                OC: 'readonly',
                OCA: 'readonly',
                t: 'readonly',
                n: 'readonly'
            }
        },
        rules: {
            // Deaktiviert die Pflicht für mehrteilige Komponentennamen (erlaubt z.B. ClientTab.vue)
            'vue/multi-word-component-names': 'off',
            // Erzwingt konsistente Attribute-Struktur in Vue-Templates
            'vue/max-attributes-per-line': ['error', {
                singleline: { max: 3 },
                multiline: { max: 1 }
            }],
            // Warnung bei nicht verwendeten Variablen (hilft beim Aufräumen)
            'no-unused-vars': 'warn'
        }
    }
]
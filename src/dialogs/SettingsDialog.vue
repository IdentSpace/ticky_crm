<template>
  <NcModal name="CRM-Einstellungen" @close="emit('close')">
    <div class="ticky-modal-settings">
      <h3>{{ t('ticky_crm', 'settings_title') }}</h3>
      <p class="settings-description">
        {{ t('ticky_crm', 'settings_description') }}
      </p>

      <div v-if="loading" class="settings-loading">
        {{ t('ticky_crm', 'settings_loading') }}
      </div>

      <template v-else>
        <div class="setting-row">
          <NcSelect
            v-bind="groupSelectConfig"
            v-model="groupSelectConfig.value"
          />
        </div>

        <div class="setting-row">
          <NcSelect
            v-bind="userSelectConfig"
            v-model="userSelectConfig.value"
          />
        </div>

        <div class="settings-actions">
          <NcButton type="primary" :disabled="saving" @click="handleSave">
            <template #icon>
              <IconCheck :size="16" />
            </template>
            {{ saving ? t('ticky_crm', 'settings_saving') : t('ticky_crm', 'settings_save') }}
          </NcButton>
        </div>

        <p v-if="error" class="settings-error">
          {{ error }}
        </p>
      </template>
    </div>
  </NcModal>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n'
import NcModal from '@nextcloud/vue/components/NcModal'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcButton from '@nextcloud/vue/components/NcButton'
import IconCheck from 'vue-material-design-icons/Check.vue'

const emit = defineEmits(['close'])

const loading = ref(false)
const saving  = ref(false)
const error   = ref('')

const groupSelectConfig = reactive({
  inputLabel:    t('ticky_crm', 'settings_groups_label'),
  multiple:      true,
  closeOnSelect: false,
  placeholder:   t('ticky_crm', 'settings_groups_placeholder'),
  options:       [],
  value:         [],
})

const userSelectConfig = reactive({
  inputLabel:    t('ticky_crm', 'settings_users_label'),
  multiple:      true,
  closeOnSelect: false,
  placeholder:   t('ticky_crm', 'settings_users_placeholder'),
  options:       [],
  value:         [],
})

const loadSettings = async () => {
  loading.value = true
  error.value   = ''

  try {
    const url      = generateUrl('/apps/ticky_crm/api/v1/settings')
    const response = await axios.get(url)
    const data     = response.data

    groupSelectConfig.options = Object.values(data.all_groups || [])
    userSelectConfig.options  = Object.values(data.all_users  || [])

    groupSelectConfig.value = groupSelectConfig.options.filter(g => (data.allowed_groups || []).includes(g.id))
    userSelectConfig.value  = userSelectConfig.options.filter(u => (data.allowed_users  || []).includes(u.id))
  } catch (e) {
    error.value = t('ticky_crm', 'settings_error_load')
    console.error(e)
  } finally {
    loading.value = false
  }
}

const handleSave = async () => {
  saving.value = true
  error.value  = ''

  try {
    const url = generateUrl('/apps/ticky_crm/api/v1/settings')
    await axios.post(url, {
      groups: groupSelectConfig.value.map(g => g.id),
      users:  userSelectConfig.value.map(u => u.id),
    })
    emit('close')
  } catch (e) {
    error.value = t('ticky_crm', 'settings_error_save')
    console.error(e)
  } finally {
    saving.value = false
  }
}

onMounted(() => loadSettings())
</script>

<style scoped>
.ticky-modal-settings {
  padding: 24px;
  min-width: 420px;
  max-width: 100%;
  display: flex;
  flex-direction: column;
  gap: 20px;

  h3 {
    font-size: 16px;
    font-weight: 600;
    margin: 0;
    color: var(--color-main-text);
  }

  .settings-description {
    font-size: 13px;
    color: var(--color-text-maxcontrast);
    line-height: 1.5;
    margin: 0;
  }
}

.setting-row {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.settings-actions {
  display: flex;
  justify-content: flex-end;
  padding-top: 4px;
}

.settings-error {
  color: var(--color-error);
  font-size: 13px;
  margin: 0;
}

.settings-loading {
  color: var(--color-text-maxcontrast);
  font-size: 13px;
  padding: 16px 0;
  text-align: center;
}
</style>
<template>
  <div class="sidebar-content">
    <div class="sidebar-actions">
      <NcButton type="primary" :disabled="isLoading" @click="handleSave">
        <template #icon>
          <IconCheck :size="16" />
        </template>
        {{ t('ticky_crm', isLoading ? 'client_saving' : 'client_save') }}
      </NcButton>
    </div>

    <div class="sidebar-form">
      <div v-if="errorMessage" class="error-banner">
        {{ errorMessage }}
      </div>

      <fieldset class="sidebar-fieldset">
        <legend>{{ t('ticky_crm', 'section_master_data') }}</legend>
        <NcTextField v-model="editableClient.name" :label="t('ticky_crm', 'field_name')" :disabled="isLoading" />
        <NcTextField v-model="editableClient.client_number" :label="t('ticky_crm', 'field_client_number')" :disabled="isLoading" />
        <NcSelect
          v-model="editableClient.status"
          :input-label="t('ticky_crm', 'field_status')"
          label="label"
          :options="statusOptions"
          value-key="id"
          label-key="label"
          :disabled="isLoading"
        />
      </fieldset>

      <fieldset class="sidebar-fieldset">
        <legend>{{ t('ticky_crm', 'section_contact') }}</legend>
        <NcTextField
          v-model="editableClient.contact_email"
          :label="t('ticky_crm', 'field_contact_email')"
          type="email"
          :disabled="isLoading"
        />
        <NcTextField
          v-model="editableClient.phone"
          :label="t('ticky_crm', 'field_phone')"
          type="tel"
          :disabled="isLoading"
        />
        <NcTextField
          v-model="editableClient.website"
          :label="t('ticky_crm', 'field_website')"
          type="url"
          :disabled="isLoading"
        />
      </fieldset>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcButton from '@nextcloud/vue/components/NcButton'
import IconCheck from 'vue-material-design-icons/Check.vue'

const props = defineProps({
  client: { type: Object, required: true }
})
const emit = defineEmits(['client-updated'])

const editableClient = ref({ ...props.client })
const isLoading = ref(false)
const errorMessage = ref('')

const statusOptions = computed(() => [
  { id: 'lead', label: t('ticky_crm', 'status_lead') },
  { id: 'active', label: t('ticky_crm', 'status_active') },
  { id: 'inactive', label: t('ticky_crm', 'status_inactive') },
])

// Extrem wichtig: Wenn der Nutzer in der Tabelle auf einen ANDEREN Kunden klickt,
// müssen die Eingabefelder sofort mit den neuen Daten überschrieben werden.
watch(() => props.client, (newClient) => {
  editableClient.value = { ...newClient }
}, { deep: true })

const handleSave = async () => {
  isLoading.value = true
  errorMessage.value = ''
  try {
    // API Call läuft hier durch...
    emit('client-updated', editableClient.value)
  } catch (error) {
    errorMessage.value = error.message || t('ticky_crm', 'error_save')
  } finally {
    isLoading.value = false
  }
}
</script>

<style lang="scss" scoped>
.sidebar-content {
  display: flex;
  flex-direction: column;
  height: 100%;
}

.sidebar-actions {
  padding: 16px 24px;
  border-bottom: 1px solid var(--color-border-light);
  display: flex;
  justify-content: flex-end;
  background-color: var(--color-main-background);
}

.sidebar-form {
  padding: 24px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.sidebar-fieldset {
  border: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 16px;

  legend {
    font-weight: bold;
    font-size: 14px;
    color: var(--color-text-maxcontrast);
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
}

.error-banner {
  color: var(--color-error);
  background-color: var(--color-error-background);
  padding: 12px;
  border-radius: 4px;
}
</style>

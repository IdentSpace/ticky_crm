<template>
  <div class="sidebar-content">
    <div v-if="errorMessage" class="error-banner">
      {{ errorMessage }}
    </div>

    <div class="sidebar-form">
      <fieldset class="sidebar-fieldset">
        <legend>{{ t('ticky_crm', 'section_master_data') }}</legend>
        <NcTextField
          v-model="editableClient.name"
          :label="t('ticky_crm', 'field_name')"
          :disabled="isLoading"
        />
        <NcTextField
          v-model="editableClient.client_number"
          :label="t('ticky_crm', 'field_client_number')"
          :disabled="isLoading"
        />
        <NcSelect
          v-bind="typeSelectConfig"
          v-model="typeSelectConfig.value"
          :disabled="isLoading"
        />
        <NcSelect
          v-bind="statusSelectConfig"
          v-model="statusSelectConfig.value"
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
          v-model="editableClient.invoice_email"
          :label="t('ticky_crm', 'field_invoice_email')"
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

      <fieldset class="sidebar-fieldset">
        <legend>{{ t('ticky_crm', 'section_tax') }}</legend>
        <NcTextField
          v-model="editableClient.vat_id"
          :label="t('ticky_crm', 'field_vat_id')"
          :disabled="isLoading"
        />
        <NcTextField
          v-model="editableClient.tax_number"
          :label="t('ticky_crm', 'field_tax_number')"
          :disabled="isLoading"
        />
        <NcTextField
          v-model="editableClient.register_court"
          :label="t('ticky_crm', 'field_register_court')"
          :disabled="isLoading"
        />
        <NcTextField
          v-model="editableClient.register_number"
          :label="t('ticky_crm', 'field_register_number')"
          :disabled="isLoading"
        />
      </fieldset>
    </div>

    <div class="sidebar-actions">
      <NcButton type="error" :disabled="isLoading" @click="handleDelete">
        <template #icon>
          <IconDelete :size="16" />
        </template>
        {{ t('ticky_crm', 'client_delete') }}
      </NcButton>

      <NcButton type="primary" :disabled="isLoading" @click="handleSave">
        <template #icon>
          <IconCheck :size="16" />
        </template>
        {{ isLoading ? t('ticky_crm', 'client_saving') : t('ticky_crm', 'client_save') }}
      </NcButton>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcButton from '@nextcloud/vue/components/NcButton'
import IconCheck from 'vue-material-design-icons/Check.vue'
import IconDelete from 'vue-material-design-icons/Delete.vue'
import { updateClient, deleteClient } from '../services/clientService'

const props = defineProps({
  client: { type: Object, required: true }
})
const emit = defineEmits(['client-updated', 'client-deleted'])

const toOption = (options, value) => options.find(o => o.id === value) ?? null

const typeSelectConfig = reactive({
  inputLabel: t('ticky_crm', 'field_type'),
  options: [
    { id: 'company', label: t('ticky_crm', 'type_company') },
    { id: 'person',  label: t('ticky_crm', 'type_person') },
  ],
  value: null,
})

const statusSelectConfig = reactive({
  inputLabel: t('ticky_crm', 'field_status'),
  options: [
    { id: 'lead',     label: t('ticky_crm', 'status_lead') },
    { id: 'active',   label: t('ticky_crm', 'status_active') },
    { id: 'inactive', label: t('ticky_crm', 'status_inactive') },
  ],
  value: null,
})

const editableClient = ref({ ...props.client })
const isLoading      = ref(false)
const errorMessage   = ref('')

// Initialwerte aus Client setzen
typeSelectConfig.value   = toOption(typeSelectConfig.options,   props.client.type)
statusSelectConfig.value = toOption(statusSelectConfig.options, props.client.status)

watch(() => props.client, (newClient) => {
  editableClient.value     = { ...newClient }
  typeSelectConfig.value   = toOption(typeSelectConfig.options,   newClient.type)
  statusSelectConfig.value = toOption(statusSelectConfig.options, newClient.status)
  errorMessage.value       = ''
}, { deep: true })

const handleSave = async () => {
  isLoading.value    = true
  errorMessage.value = ''
  try {
    const payload = {
      ...editableClient.value,
      type:   typeSelectConfig.value?.id   ?? null,
      status: statusSelectConfig.value?.id ?? null,
    }
    const updatedData = await updateClient(payload)
    emit('client-updated', updatedData)
  } catch (error) {
    errorMessage.value = error.message || t('ticky_crm', 'error_save')
  } finally {
    isLoading.value = false
  }
}

const handleDelete = async () => {
  const confirmed = confirm(
      t('ticky_crm', 'confirm_delete_client').replace('{name}', props.client.name)
  )
  if (!confirmed) return

  isLoading.value = true
  try {
    await deleteClient(props.client.uuid)
    emit('client-deleted', props.client.uuid)
  } catch (error) {
    errorMessage.value = error.message
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

.sidebar-form {
  flex: 1;
  padding: 24px;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.sidebar-actions {
  padding: 16px 24px;
  border-top: 1px solid var(--color-border-light);
  display: flex;
  justify-content: space-between;
  background-color: var(--color-main-background);
}

.sidebar-fieldset {
  border: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 16px;

  legend {
    font-weight: 600;
    font-size: 12px;
    color: var(--color-text-maxcontrast);
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
}

.error-banner {
  margin: 16px 24px 0;
  color: var(--color-error);
  background-color: var(--color-error-background);
  padding: 12px;
  border-radius: var(--border-radius);
  font-size: 13px;
}
</style>
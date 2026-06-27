<template>
  <NcDialog
    v-model:open="showDialog"
    :name="t('ticky_crm', 'client_create_title')"
    size="large"
    :buttons="buttons"
    :close-on-click-outside="!isLoading"
  >
    <div class="ticky-form-container">
      <div v-if="errorMessage" class="ticky-error-banner">
        {{ errorMessage }}
      </div>

      <fieldset class="ticky-fieldset">
        <legend>{{ t('ticky_crm', 'section_master_data') }}</legend>
        <div class="ticky-grid">
          <NcTextField
            v-model="newClient.client_number"
            :label="t('ticky_crm', 'field_client_number')"
            :disabled="isLoading"
            required
          />

          <NcTextField
            v-model="newClient.name"
            :label="t('ticky_crm', 'field_name')"
            :disabled="isLoading"
            required
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
        </div>
      </fieldset>

      <fieldset class="ticky-fieldset">
        <legend>{{ t('ticky_crm', 'section_contact') }}</legend>
        <div class="ticky-grid">
          <NcTextField
            v-model="newClient.contact_email"
            :label="t('ticky_crm', 'field_contact_email')"
            type="email"
            :disabled="isLoading"
          />

          <NcTextField
            v-model="newClient.invoice_email"
            :label="t('ticky_crm', 'field_invoice_email')"
            type="email"
            :disabled="isLoading"
          />

          <NcTextField
            v-model="newClient.phone"
            :label="t('ticky_crm', 'field_phone')"
            type="tel"
            :disabled="isLoading"
          />

          <NcTextField
            v-model="newClient.website"
            :label="t('ticky_crm', 'field_website')"
            type="url"
            :disabled="isLoading"
          />
        </div>
      </fieldset>

      <fieldset class="ticky-fieldset">
        <legend>{{ t('ticky_crm', 'section_tax') }}</legend>
        <div class="ticky-grid">
          <NcTextField
            v-model="newClient.vat_id"
            :label="t('ticky_crm', 'field_vat_id')"
            :disabled="isLoading"
          />

          <NcTextField
            v-model="newClient.tax_number"
            :label="t('ticky_crm', 'field_tax_number')"
            :disabled="isLoading"
          />

          <NcTextField
            v-model="newClient.register_court"
            :label="t('ticky_crm', 'field_register_court')"
            :disabled="isLoading"
          />

          <NcTextField
            v-model="newClient.register_number"
            :label="t('ticky_crm', 'field_register_number')"
            :disabled="isLoading"
          />
        </div>
      </fieldset>
    </div>
  </NcDialog>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import { NcDialog, NcTextField, NcSelect } from '@nextcloud/vue'
import { createClient } from '../services/clientService'

const props = defineProps({
  open: { type: Boolean, required: true },
})
const emit = defineEmits(['update:open', 'client-created'])

const showDialog = computed({
  get: () => props.open,
  set: (value) => emit('update:open', value),
})

const isLoading    = ref(false)
const errorMessage = ref('')

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

// Defaults setzen
typeSelectConfig.value   = typeSelectConfig.options[0]
statusSelectConfig.value = statusSelectConfig.options[0]

const initialFormState = () => ({
  client_number:   '',
  name:            '',
  contact_email:   '',
  invoice_email:   '',
  phone:           '',
  website:         '',
  vat_id:          '',
  tax_number:      '',
  register_court:  '',
  register_number: '',
})

const newClient = ref(initialFormState())

watch(showDialog, (isOpen) => {
  if (!isOpen) {
    newClient.value          = initialFormState()
    typeSelectConfig.value   = typeSelectConfig.options[0]
    statusSelectConfig.value = statusSelectConfig.options[0]
    errorMessage.value       = ''
  }
})

const handleSubmit = async () => {
  if (!newClient.value.name.trim() || !newClient.value.client_number.trim()) {
    errorMessage.value = t('ticky_crm', 'error_required_fields')
    return
  }

  isLoading.value    = true
  errorMessage.value = ''

  try {
    const payload = {
      ...newClient.value,
      type:   typeSelectConfig.value?.id   ?? 'company',
      status: statusSelectConfig.value?.id ?? 'lead',
    }

    const createdClient = await createClient(payload)
    emit('client-created', createdClient)
    showDialog.value = false
  } catch (error) {
    errorMessage.value = error.message || t('ticky_crm', 'error_save')
  } finally {
    isLoading.value = false
  }
}

const buttons = computed(() => [
  {
    label:    t('ticky_crm', 'client_cancel'),
    type:     'normal',
    callback: () => { showDialog.value = false },
    disabled: isLoading.value,
  },
  {
    label:    isLoading.value ? t('ticky_crm', 'client_create_saving') : t('ticky_crm', 'client_create_submit'),
    type:     'primary',
    callback: handleSubmit,
    disabled: isLoading.value
        || !newClient.value.name.trim()
        || !newClient.value.client_number.trim(),
  },
])
</script>

<style scoped>
.ticky-form-container {
  display: flex;
  flex-direction: column;
  gap: 28px;
  padding: 8px 24px 16px;
  width: 760px;
  max-width: 100%;
  box-sizing: border-box;
  margin: 0 auto;
}

.ticky-error-banner {
  background-color: var(--color-error-background);
  color: var(--color-error);
  border-left: 4px solid var(--color-error);
  padding: 12px 16px;
  border-radius: var(--border-radius);
  font-size: 13px;
}

.ticky-fieldset {
  border: none;
  padding: 0;
  margin: 0;

  legend {
    font-weight: 600;
    font-size: 11px;
    color: var(--color-text-maxcontrast);
    margin-bottom: 12px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    padding: 0;
  }
}

.ticky-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px 20px;
}
</style>
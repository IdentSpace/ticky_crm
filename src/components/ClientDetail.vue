<template>
  <div class="sidebar-content">
    <div class="sidebar-actions">
      <NcButton type="primary" :disabled="isLoading" @click="handleSave">
        <template #icon>
          <IconCheck :size="16" />
        </template>
        {{ isLoading ? 'Wird aktualisiert...' : 'Aktualisieren' }}
      </NcButton>
    </div>

    <div class="sidebar-form">
      <div v-if="errorMessage" class="error-banner">
        {{ errorMessage }}
      </div>

      <fieldset class="sidebar-fieldset">
        <legend>Stammdaten</legend>
        <NcTextField v-model="editableClient.name" label="Firmenname" :disabled="isLoading" />
        <NcTextField v-model="editableClient.client_number" label="Kundennummer" :disabled="isLoading" />
        <NcSelect
          v-model="editableClient.status"
          label="Status"
          :options="statusOptions"
          value-key="id"
          label-key="label"
          :disabled="isLoading"
        />
      </fieldset>

      <fieldset class="sidebar-fieldset">
        <legend>Kontakt</legend>
        <NcTextField
          v-model="editableClient.contact_email"
          label="E-Mail"
          type="email"
          :disabled="isLoading"
        />
        <NcTextField
          v-model="editableClient.phone"
          label="Telefon"
          type="tel"
          :disabled="isLoading"
        />
        <NcTextField
          v-model="editableClient.website"
          label="Website"
          type="url"
          :disabled="isLoading"
        />
      </fieldset>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
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

const statusOptions = [
  { id: 'lead', label: 'Lead' },
  { id: 'active', label: 'Aktiv' },
  { id: 'inactive', label: 'Inaktiv' },
]

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
    errorMessage.value = error.message || 'Fehler beim Aktualisieren.'
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
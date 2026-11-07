<template>
  <details class="contact-status-update">
    <summary>
      <FollowUpStatus v-if="isDetails" :status="currentStatus" />
      <template v-else>
        {{ t('ticky_crm', 'contact_update_optional') }}
      </template>
    </summary>
    <fieldset :disabled="disabled" class="contact-status-update__fields">
      <template v-if="isDetails">
        <p v-if="options.follow_up_mode === 'manual'">
          {{ t('ticky_crm', 'follow_up_manually_set') }}
        </p>
        <NcSelect
          v-model="selectedMode"
          :options="modeOptions"
          :input-label="t('ticky_crm', 'follow_up_mode')"
          :disabled="disabled"
          :clearable="false"
          :searchable="false"
        />
        <NcSelect
          v-if="options.follow_up_mode === 'manual'"
          v-model="selectedManualStatus"
          :options="manualStatusOptions"
          :input-label="t('ticky_crm', 'follow_up_title')"
          :disabled="disabled"
          :clearable="false"
          :searchable="false"
        />
      </template>
      <NcCheckboxRadioSwitch v-if="!isDetails" v-model="options.setDate" :disabled="disabled">
        {{ t('ticky_crm', 'contact_update_set_date') }}
      </NcCheckboxRadioSwitch>
      <NcTextField
        v-if="isDetails || options.setDate"
        v-model="contactDate"
        type="date"
        :label="t('ticky_crm', 'field_last_contact_date')"
        :max="today()"
        :disabled="disabled"
        :required="!isDetails"
      />
      <NcButton v-if="isDetails" :disabled="disabled" @click="contactDate = today()">
        {{ t('ticky_crm', 'follow_up_contacted_today') }}
      </NcButton>
      <NcCheckboxRadioSwitch v-if="!isDetails" v-model="options.setStatus" :disabled="disabled">
        {{ t('ticky_crm', 'contact_update_set_status') }}
      </NcCheckboxRadioSwitch>
      <NcSelect
        v-if="!isDetails && options.setStatus"
        v-model="selectedStatus"
        :options="statusOptions"
        :input-label="t('ticky_crm', 'follow_up_title')"
        :disabled="disabled"
        :clearable="false"
        :searchable="false"
      />
      <p>{{ t('ticky_crm', isDetails ? 'follow_up_save_hint' : 'contact_update_hint') }}</p>
    </fieldset>
  </details>
</template>

<script setup>
import { computed } from 'vue'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import { t } from '@nextcloud/l10n'
import FollowUpStatus from './FollowUpStatus.vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { localToday } from '../utils/contactUpdate.js'

const options = defineModel({ type: Object, required: true })
const props = defineProps({
  disabled: { type: Boolean, default: false },
  variant: { type: String, default: 'optional', validator: value => ['optional', 'details'].includes(value) },
  currentStatus: { type: String, default: 'unknown' },
})
const isDetails = computed(() => props.variant === 'details')
const contactDate = computed({
  get: () => (isDetails.value ? options.value.last_contact_date : options.value.date) ?? '',
  set: value => {
    if (isDetails.value) options.value.last_contact_date = value
    else options.value.date = value
  },
})
const modeOptions = computed(() => ['automatic', 'manual'].map(id => ({ id, label: t('ticky_crm', 'follow_up_' + id) })))
const manualStatusOptions = computed(() => statusOptions.value.filter(option => option.id !== 'automatic'))
const selectedMode = computed({
  get: () => modeOptions.value.find(option => option.id === options.value.follow_up_mode),
  set: option => { if (option) options.value.follow_up_mode = option.id },
})
const selectedManualStatus = computed({
  get: () => manualStatusOptions.value.find(option => option.id === options.value.follow_up_manual),
  set: option => { if (option) options.value.follow_up_manual = option.id },
})
const today = localToday
const statusOptions = computed(() => ['automatic', 'green', 'yellow', 'red'].map(id => ({
  id, label: t('ticky_crm', id === 'automatic' ? 'follow_up_automatic' : 'follow_up_' + id),
})))
const selectedStatus = computed({
  get: () => statusOptions.value.find(option => option.id === options.value.status),
  set: option => { if (option) options.value.status = option.id },
})
</script>

<style scoped>
.contact-status-update { border: 1px solid var(--color-border); border-radius: var(--border-radius-large); }
summary :deep(.follow-up-status) { vertical-align: middle; }
summary { padding: 10px 12px; cursor: pointer; }
.contact-status-update__fields { min-width: 0; border: 0; padding: 0 12px 12px; margin: 0; display: flex; flex-direction: column; gap: 12px; }
p { color: var(--color-text-maxcontrast); font-size: 13px; }
</style>

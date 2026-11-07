<template>
  <span
    class="follow-up-status"
    :class="status"
    :title="label"
    :role="iconOnly ? 'img' : undefined"
    :aria-label="iconOnly ? label : undefined"
  >
    <component :is="icons[status]" :size="20" aria-hidden="true" />
    <span v-if="!iconOnly">{{ label }}</span>
  </span>
</template>
<script setup>
import { computed } from 'vue'
import { t } from '@nextcloud/l10n'
import Check from 'vue-material-design-icons/CheckCircleOutline.vue'
import Clock from 'vue-material-design-icons/ClockOutline.vue'
import Alert from 'vue-material-design-icons/AlertCircleOutline.vue'
import Pause from 'vue-material-design-icons/PauseCircleOutline.vue'
import Help from 'vue-material-design-icons/HelpCircleOutline.vue'
const props = defineProps({
  status: { type: String, default: 'unknown' },
  iconOnly: { type: Boolean, default: false },
})
const icons = { green: Check, yellow: Clock, red: Alert, inactive: Pause, unknown: Help }
const label = computed(() => t('ticky_crm', 'follow_up_' + props.status))
</script>
<style scoped>
.follow-up-status { display: inline-flex; align-items: center; gap: 6px; }
.green :deep(.material-design-icon) { color: #22813b; }
.yellow :deep(.material-design-icon) { color: #9b7000; }
.red :deep(.material-design-icon) { color: #c62828; }
.inactive :deep(.material-design-icon) { color: #616161; }
.unknown :deep(.material-design-icon) { color: var(--color-text-maxcontrast); }
</style>

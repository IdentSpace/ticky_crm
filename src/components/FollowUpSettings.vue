<template>
  <form class="follow-up-settings" @submit.prevent="save">
    <h3>{{ t('ticky_crm', 'follow_up_title') }}</h3>
    <p>{{ t('ticky_crm', 'follow_up_settings_description') }}</p>
    <NcTextField
      v-model="yellowDays"
      type="number"
      min="1"
      max="36499"
      :label="t('ticky_crm', 'follow_up_yellow_days')"
      :disabled="loading || saving || !canManage || loadFailed"
      required
    />
    <NcTextField
      v-model="redDays"
      type="number"
      min="2"
      max="36500"
      :label="t('ticky_crm', 'follow_up_red_days')"
      :disabled="loading || saving"
      required
    />
    <p v-if="!loading && !loadFailed && !canManage">
      {{ t('ticky_crm', 'product_options_admin_only') }}
    </p>
    <p v-if="error" role="alert">
      {{ error }}
    </p>
    <p v-if="saved" role="status">
      {{ t('ticky_crm', 'follow_up_saved') }}
    </p>
    <NcButton type="submit" :disabled="loading || saving">
      {{ t('ticky_crm', 'settings_save') }}
    </NcButton>
  </form>
</template>
<script setup>
import { ref, onMounted } from 'vue'
import { NcTextField, NcButton } from '@nextcloud/vue'
import { t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
const yellowDays = ref('30')
const redDays = ref('60')
const loading = ref(true)
const canManage = ref(false)
const loadFailed = ref(false)
const saving = ref(false)
const saved = ref(false)
const error = ref('')
const url = generateUrl('/apps/ticky_crm/api/v1/settings/follow-up')
onMounted(async () => {
  try {
    const { data } = await axios.get(url)
    canManage.value = data.can_manage === true
    yellowDays.value = String(data.yellow_days)
    redDays.value = String(data.red_days)
  } catch { loadFailed.value = true; error.value = t('ticky_crm', 'settings_error_load') }
  finally { loading.value = false }
})
async function save() {
  saved.value = false
  error.value = ''
  const yellow = Number(yellowDays.value)
  const red = Number(redDays.value)
  if (!Number.isInteger(yellow) || !Number.isInteger(red) || yellow < 1 || red <= yellow || red > 36500) {
    error.value = t('ticky_crm', 'follow_up_invalid_intervals')
    return
  }
  saving.value = true
  try {
    await axios.post(url, { yellowDays: yellow, redDays: red })
    saved.value = true
  } catch { error.value = t('ticky_crm', 'settings_error_save') }
  finally { saving.value = false }
}
</script>
<style scoped>
.follow-up-settings { display: flex; flex-direction: column; gap: 16px; }
</style>

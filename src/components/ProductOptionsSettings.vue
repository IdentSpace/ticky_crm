<template>
  <section class="product-options-settings">
    <h3>{{ t('ticky_crm', 'product_options_title') }}</h3>
    <p>{{ t('ticky_crm', 'product_options_description') }}</p>
    <FormError v-if="error" :message="error" />
    <p v-if="loading" role="status">
      {{ t('ticky_crm', 'settings_loading') }}
    </p>
    <NcButton v-if="loadFailed" @click="load">
      {{ t('ticky_crm', 'product_retry') }}
    </NcButton>
    <p v-if="!loading && !loadFailed && !canManage">
      {{ t('ticky_crm', 'product_options_admin_only') }}
    </p>
    <form v-if="!loading && !loadFailed && canManage" @submit.prevent="save">
      <fieldset v-for="field in fields" :key="field">
        <legend>{{ t('ticky_crm', `product_${field}`) }}</legend>
        <div v-for="(option, index) in options[field]" :key="index" class="option-row">
          <NcTextField
            v-model="option.id"
            :label="t('ticky_crm', 'product_options_key')"
            :disabled="saving"
            :readonly="isProtected(field, option)"
            :maxlength="field === 'category' ? 255 : field === 'currency' ? 3 : 32"
            required
          />
          <NcTextField
            v-model="option.label"
            :label="t('ticky_crm', 'product_options_label')"
            :disabled="saving"
            :maxlength="128"
            required
          />
          <NcButton
            :aria-label="t('ticky_crm', 'product_options_remove', { name: option.label || option.id })"
            :disabled="saving || isProtected(field, option)"
            variant="tertiary"
            @click="options[field].splice(index, 1)"
          >
            <template #icon>
              <IconDelete :size="20" />
            </template>
          </NcButton>
        </div>
        <div class="option-actions">
          <NcButton :disabled="saving" @click="options[field].push({ id: '', label: '' })">
            {{ t('ticky_crm', 'product_options_add') }}
          </NcButton>
          <NcButton :disabled="saving" @click="options[field] = clone(defaults[field])">
            {{ t('ticky_crm', 'product_options_defaults') }}
          </NcButton>
        </div>
      </fieldset>
      <p class="option-hint">
        {{ t('ticky_crm', 'product_options_key_hint') }}
      </p>
      <NcButton type="submit" variant="primary" :disabled="saving">
        {{ t('ticky_crm', saving ? 'settings_saving' : 'settings_save') }}
      </NcButton>
      <p v-if="saved" role="status">
        {{ t('ticky_crm', 'product_options_saved') }}
      </p>
    </form>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import IconDelete from 'vue-material-design-icons/DeleteOutline.vue'
import FormError from './FormError.vue'
import { loadProductOptions, saveProductOptions } from '../services/productOptionsService'

const fields = ['category', 'type', 'procurement_type', 'status', 'unit', 'currency']
const options = ref({})
const defaults = ref({})
const canManage = ref(false)
const loading = ref(true)
const loadFailed = ref(false)
const saving = ref(false)
const saved = ref(false)
const error = ref('')
const clone = value => JSON.parse(JSON.stringify(value))
const isProtected = (field, option) => field === 'status' && ['active', 'archived'].includes(option.id)
const load = async () => {
  loading.value = true
  loadFailed.value = false
  error.value = ''
  try {
    const data = await loadProductOptions()
    options.value = clone(data.options)
    defaults.value = data.defaults
    canManage.value = data.can_manage
  } catch {
    loadFailed.value = true
    error.value = t('ticky_crm', 'settings_error_load')
  } finally {
    loading.value = false
  }
}
const save = async () => {
  if (saving.value) return
  saving.value = true
  saved.value = false
  error.value = ''
  try {
    options.value = clone(await saveProductOptions(options.value))
    saved.value = true
  } catch (cause) {
    const field = cause.response?.data?.field
    error.value = field ? t('ticky_crm', 'product_options_invalid', { field: t('ticky_crm', `product_${field}`) }) : t('ticky_crm', 'settings_error_save')
  } finally {
    saving.value = false
  }
}
onMounted(load)
</script>

<style scoped>
.product-options-settings, .product-options-settings form { display: flex; flex-direction: column; gap: 20px; }
fieldset { border: 0; padding: 0; margin: 0; min-width: 0; }
legend { font-weight: 600; margin-bottom: 12px; }
.option-row { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto; align-items: center; gap: 8px; margin-bottom: 8px; }
.option-actions { display: flex; flex-wrap: wrap; gap: 8px; }
.option-hint { font-size: 13px; color: var(--color-text-maxcontrast); }
@media (max-width: 500px) {
  .option-row { grid-template-columns: minmax(0, 1fr) auto; }
  .option-row > :first-child { grid-column: 1 / -1; }
}
</style>

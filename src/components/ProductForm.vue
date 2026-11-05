<template>
  <form ref="form" class="product-form" @submit.prevent="emit('submit')">
    <FormError v-if="loadFailed" :message="t('ticky_crm', 'settings_error_load')" />
    <NcButton v-if="loadFailed" @click="load">
      {{ t('ticky_crm', 'product_retry') }}
    </NcButton>
    <fieldset>
      <legend>{{ t('ticky_crm', 'section_master_data') }}</legend>
      <div class="product-grid">
        <NcTextField
          v-for="field in masterFields"
          :key="field.name"
          :model-value="product[field.name] ?? ''"
          :label="t('ticky_crm', `product_${field.name}`)"
          :maxlength="field.length"
          :required="field.required"
          :disabled="disabled || loading || loadFailed"
          @update:model-value="product[field.name] = $event"
        />
        <NcSelect
          v-for="field in selects"
          :key="field.name"
          :model-value="field.options.find(option => option.id === product[field.name]) ?? null"
          :options="field.options"
          :input-label="t('ticky_crm', `product_${field.name}`)"
          :clearable="['category', 'procurement_type'].includes(field.name)"
          :required="!['category', 'procurement_type'].includes(field.name)"
          :disabled="disabled || loading || loadFailed"
          label="label"
          @update:model-value="product[field.name] = $event?.id ?? null"
        />
      </div>
    </fieldset>
    <fieldset>
      <legend>{{ t('ticky_crm', 'product_prices') }}</legend>
      <div class="product-grid">
        <NcSelect
          :model-value="optionList('currency').find(option => option.id === product.currency) ?? null"
          :options="optionList('currency')"
          :input-label="t('ticky_crm', 'product_currency')"
          :disabled="disabled || loading || loadFailed"
          :clearable="false"
          label="label"
          required
          @update:model-value="product.currency = $event?.id ?? null"
        />
        <DecimalField
          v-for="field in priceFields"
          :key="field"
          :model-value="product[field] ?? ''"
          :label="t('ticky_crm', `product_${field}`)"
          :required="field.startsWith('sales_')"
          :disabled="disabled"
          @update:model-value="product[field] = $event"
        />
      </div>
      <p class="product-hint">
        {{ t('ticky_crm', 'product_purchase_hint') }}
      </p>
    </fieldset>
    <fieldset>
      <legend>{{ t('ticky_crm', 'product_description') }}</legend>
      <NcTextArea
        :model-value="product.description ?? ''"
        :label="t('ticky_crm', 'product_description')"
        :disabled="disabled"
        :maxlength="65535"
        @update:model-value="product.description = $event"
      />
      <NcTextArea
        :model-value="product.internal_note ?? ''"
        :label="t('ticky_crm', 'product_internal_note')"
        :disabled="disabled"
        :maxlength="65535"
        @update:model-value="product.internal_note = $event"
      />
    </fieldset>
  </form>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import DecimalField from './DecimalField.vue'
import FormError from './FormError.vue'
import NcButton from '@nextcloud/vue/components/NcButton'
import { loadProductOptions, productOptions } from '../services/productOptionsService'

defineProps({ disabled: { type: Boolean, default: false } })
const product = defineModel({ type: Object, required: true })
const emit = defineEmits(['submit'])
const form = ref(null)
const loading = ref(true)
const loadFailed = ref(false)
defineExpose({ validate: () => !loading.value && !loadFailed.value && form.value.reportValidity() })

const masterFields = [
  { name: 'product_number', length: 64, required: true },
  { name: 'name', length: 255, required: true },
]
const priceFields = ['sales_price_net', 'sales_tax_rate', 'purchase_price_net', 'purchase_tax_rate']
const optionList = field => {
  const configured = productOptions.value?.[field] ?? []
  const current = product.value[field]
  return product.value.uuid && current && !configured.some(option => option.id === current)
    ? [...configured, { id: current, label: current }] : configured
}
const selects = computed(() => ['category', 'type', 'procurement_type', 'status', 'unit'].map(name => ({ name, options: optionList(name) })))
const load = async () => {
  loading.value = true
  loadFailed.value = false
  try {
    await loadProductOptions()
    if (!product.value.uuid) {
      for (const field of ['type', 'status', 'unit', 'currency']) {
        if (!productOptions.value[field].some(option => option.id === product.value[field])) product.value[field] = productOptions.value[field][0]?.id ?? null
      }
    }
  } catch {
    loadFailed.value = true
  } finally {
    loading.value = false
  }
}
onMounted(load)
</script>

<style scoped lang="scss">
.product-form {
  display: flex;
  flex-direction: column;
  gap: 24px;
  container-type: inline-size;
  fieldset {
    border: 0;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 16px;
    min-width: 0;
  }
  legend {
    font-weight: 600;
    font-size: 12px;
    color: var(--color-text-maxcontrast);
    text-transform: uppercase;
    margin-bottom: 12px;
  }
}
.product-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px 20px;
}
.product-hint {
  color: var(--color-text-maxcontrast);
  font-size: 13px;
}
@container (max-width: 450px) {
  .product-grid { grid-template-columns: minmax(0, 1fr); }
}
</style>

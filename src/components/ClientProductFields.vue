<template>
  <div class="position-fields">
    <DecimalField
      v-for="field in fields"
      :key="field"
      :model-value="position[field] ?? ''"
      :label="t('ticky_crm', field === 'sales_price_net' ? 'client_product_actual_price' : `product_${field}`)"
      :required="!field.startsWith('purchase_')"
      :disabled="disabled"
      @update:model-value="position[field] = $event"
    />
  </div>
</template>

<script setup>
import { t } from '@nextcloud/l10n'
import DecimalField from './DecimalField.vue'

defineProps({ disabled: { type: Boolean, default: false } })
const position = defineModel({ type: Object, required: true })
const fields = ['quantity', 'sales_price_net', 'sales_tax_rate', 'purchase_price_net', 'purchase_tax_rate']
</script>

<style scoped>
.position-fields { display: flex; flex-direction: column; gap: 16px; }
</style>

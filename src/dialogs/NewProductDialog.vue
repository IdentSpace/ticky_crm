<template>
  <NcDialog
    v-model:open="showDialog"
    :name="t('ticky_crm', 'product_create_title')"
    :buttons="buttons"
    :close-on-click-outside="!saving"
    :close-on-press-escape="!saving"
    size="large"
  >
    <div class="product-dialog">
      <FormError v-if="error" :message="error" class="product-error" />
      <ProductForm
        ref="form"
        v-model="product"
        :disabled="saving"
        @submit="save"
      />
    </div>
  </NcDialog>
</template>

<script setup>
import { computed, ref } from 'vue'
import { t } from '@nextcloud/l10n'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import ProductForm from '../components/ProductForm.vue'
import FormError from '../components/FormError.vue'
import { createProduct, newProduct, productError } from '../services/productService'

const open = defineModel('open', { type: Boolean, required: true })
const emit = defineEmits(['product-created'])
const product = ref(newProduct())
const form = ref(null)
const saving = ref(false)
const error = ref('')
const showDialog = computed({
  get: () => open.value,
  set: value => { if (!saving.value) open.value = value },
})
const save = async () => {
  // NcDialog closes after a button callback unless it explicitly returns false.
  if (saving.value || !form.value.validate()) return false
  saving.value = true
  error.value = ''
  try {
    emit('product-created', await createProduct(product.value))
    open.value = false
  } catch (cause) {
    error.value = productError(cause)
  } finally {
    saving.value = false
  }
  return false
}
const buttons = computed(() => [
  { label: t('ticky_crm', 'client_cancel'), callback: () => { open.value = false }, disabled: saving.value },
  { label: t('ticky_crm', saving.value ? 'client_saving' : 'product_create'), variant: 'primary', callback: save, disabled: saving.value },
])
</script>

<style scoped>
.product-dialog { padding: 8px 24px 16px; width: 760px; max-width: 100%; box-sizing: border-box; }
.product-error { margin-bottom: 16px; }
</style>

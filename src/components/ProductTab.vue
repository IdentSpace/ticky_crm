<template>
  <div class="product-details">
    <FormError v-if="error" :message="error" class="product-error" />
    <div class="product-fields">
      <ProductForm
        ref="form"
        v-model="editable"
        :disabled="saving"
        @submit="save"
      />
    </div>
    <div class="product-actions">
      <NcButton type="error" :disabled="saving" @click="remove">
        <template #icon>
          <IconDelete :size="16" />
        </template>
        {{ t('ticky_crm', 'client_delete') }}
      </NcButton>
      <NcButton type="primary" :disabled="saving" @click="save">
        <template #icon>
          <IconCheck :size="16" />
        </template>
        {{ t('ticky_crm', saving ? 'client_saving' : 'client_save') }}
      </NcButton>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { t } from '@nextcloud/l10n'
import { showSuccess } from '@nextcloud/dialogs'
import NcButton from '@nextcloud/vue/components/NcButton'
import IconDelete from 'vue-material-design-icons/Delete.vue'
import IconCheck from 'vue-material-design-icons/Check.vue'
import ProductForm from './ProductForm.vue'
import FormError from './FormError.vue'
import { updateProduct, deleteProduct, productError } from '../services/productService'

const props = defineProps({ product: { type: Object, required: true } })
const emit = defineEmits(['product-updated', 'product-deleted'])
const editable = ref({ ...props.product })
const saving = ref(false)
const error = ref('')
const form = ref(null)
const save = async () => {
  if (saving.value || !form.value.validate()) return
  saving.value = true
  error.value = ''
  try {
    const updated = await updateProduct(editable.value)
    editable.value = { ...updated }
    emit('product-updated', updated)
    showSuccess(t('ticky_crm', 'product_saved'))
  } catch (cause) {
    error.value = productError(cause)
  } finally {
    saving.value = false
  }
}
const remove = async () => {
  if (saving.value || !window.confirm(t('ticky_crm', 'product_confirm_delete', { name: props.product.name }))) return
  saving.value = true
  error.value = ''
  try {
    await deleteProduct(props.product.uuid)
    emit('product-deleted', props.product.uuid)
  } catch (cause) {
    error.value = productError(cause)
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.product-details { display: flex; flex-direction: column; height: 100%; }
.product-fields { flex: 1; padding: 24px; }
.product-actions { display: flex; justify-content: space-between; gap: 12px; padding: 16px 24px; border-top: 1px solid var(--color-border-light); }
.product-error { margin: 16px 24px 0; }
</style>

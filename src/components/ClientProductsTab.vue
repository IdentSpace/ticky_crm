<template>
  <div class="client-products-tab">
    <FormError v-if="error" :message="error" />
    <p v-if="loading" role="status">
      {{ t('ticky_crm', 'client_product_loading') }}
    </p>
    <NcButton v-if="loadFailed" :disabled="loading" @click="loadPositions">
      {{ t('ticky_crm', 'product_retry') }}
    </NcButton>

    <form ref="createForm" class="position-create" @submit.prevent="add">
      <NcSelect
        v-model="selectedProduct"
        :options="options"
        :loading="searching"
        :input-label="t('ticky_crm', 'client_product_select')"
        :disabled="saving"
        label="label"
        required
        @search="search"
      >
        <template #no-options>
          {{ t('ticky_crm', 'client_product_no_results') }}
        </template>
      </NcSelect>
      <template v-if="selectedProduct">
        <p>{{ productOptionLabel('unit', selectedProduct.unit) }} · {{ selectedProduct.currency }}</p>
        <ClientProductFields v-model="draft" :disabled="saving" />
        <p class="position-hint">
          {{ t('ticky_crm', 'client_product_snapshot_hint') }}
        </p>
      </template>
      <NcButton type="submit" variant="primary" :disabled="saving || !selectedProduct">
        <template #icon>
          <IconPlus :size="20" />
        </template>
        {{ t('ticky_crm', 'client_product_add') }}
      </NcButton>
    </form>

    <p v-if="!loading && !loadFailed && !positions.length">
      {{ t('ticky_crm', 'client_product_empty') }}
    </p>
    <ul class="position-list">
      <li v-for="position in sortedPositions" :key="position.uuid" class="position-card">
        <h3>{{ position.name }}</h3>
        <p class="position-hint">
          {{ position.product_number }}
        </p>
        <template v-if="editing?.uuid === position.uuid">
          <form @submit.prevent="savePosition">
            <ClientProductFields v-model="editing" :disabled="saving" />
            <div class="position-actions">
              <NcButton :disabled="saving" @click="editing = null">
                {{ t('ticky_crm', 'client_cancel') }}
              </NcButton>
              <NcButton type="submit" variant="primary" :disabled="saving">
                {{ t('ticky_crm', 'client_save') }}
              </NcButton>
            </div>
          </form>
        </template>
        <template v-else>
          <dl class="position-values">
            <div>
              <dt>{{ t('ticky_crm', 'product_quantity') }}</dt>
              <dd>{{ decimalDisplay(position.quantity) }} {{ productOptionLabel('unit', position.unit) }}</dd>
            </div>
            <div>
              <dt>{{ t('ticky_crm', 'client_product_actual_price') }}</dt>
              <dd>{{ decimalDisplay(position.sales_price_net) }} {{ position.currency }}</dd>
            </div>
            <div>
              <dt>{{ t('ticky_crm', 'product_sales_tax_rate') }}</dt>
              <dd>{{ decimalDisplay(position.sales_tax_rate) }} %</dd>
            </div>
          </dl>
          <div class="position-actions">
            <NcButton :disabled="saving" @click="editing = { ...position }">
              {{ t('ticky_crm', 'client_product_edit') }}
            </NcButton>
            <NcButton variant="error" :disabled="saving" @click="remove(position)">
              {{ t('ticky_crm', 'client_product_remove') }}
            </NcButton>
          </div>
        </template>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcButton from '@nextcloud/vue/components/NcButton'
import IconPlus from 'vue-material-design-icons/Plus.vue'
import FormError from './FormError.vue'
import ClientProductFields from './ClientProductFields.vue'
import { decimalDisplay } from '../utils/decimal'
import { sortByName } from '../utils/sortByName'
import { loadProductOptions, productOptionLabel } from '../services/productOptionsService'
import { getProducts, productError } from '../services/productService'
import { getClientProducts, createClientProduct, updateClientProduct, deleteClientProduct } from '../services/clientProductService'

const props = defineProps({ clientUuid: { type: String, required: true } })
const emit = defineEmits(['count-changed'])
const positions = ref([])
const sortedPositions = computed(() => sortByName(positions.value))
watch(() => positions.value.length, count => emit('count-changed', { clientUuid: props.clientUuid, count }))
const options = ref([])
const selectedProduct = ref(null)
const draft = ref({})
const editing = ref(null)
const createForm = ref(null)
const loading = ref(false)
const loadFailed = ref(false)
const searching = ref(false)
const saving = ref(false)
const error = ref('')
let searchTimer
let searchId = 0

watch(selectedProduct, product => {
  draft.value = product ? {
    quantity: '1', sales_price_net: product.sales_price_net, sales_tax_rate: product.sales_tax_rate,
    purchase_price_net: product.purchase_price_net, purchase_tax_rate: product.purchase_tax_rate,
  } : {}
})

const loadPositions = async () => {
  loading.value = true
  loadFailed.value = false
  error.value = ''
  try {
    positions.value = await getClientProducts(props.clientUuid)
  } catch (cause) {
    loadFailed.value = true
    error.value = productError(cause, 'client_product_error_load')
  } finally {
    loading.value = false
  }
}
const search = (query = '') => {
  clearTimeout(searchTimer)
  const current = ++searchId
  searching.value = true
  searchTimer = setTimeout(async () => {
    try {
      const result = await getProducts(0, query, true)
      if (current === searchId) options.value = result.map(product => ({ ...product, label: `${product.product_number} — ${product.name}` }))
    } catch (cause) {
      if (current === searchId) error.value = productError(cause, 'product_error_load')
    } finally {
      if (current === searchId) searching.value = false
    }
  }, 250)
}
const add = async () => {
  if (saving.value || !selectedProduct.value || !createForm.value.reportValidity()) return
  saving.value = true
  error.value = ''
  try {
    const position = await createClientProduct(props.clientUuid, { ...draft.value, product_uuid: selectedProduct.value.uuid })
    positions.value.unshift(position)
    selectedProduct.value = null
  } catch (cause) {
    error.value = productError(cause, 'client_product_error_save')
  } finally {
    saving.value = false
  }
}
const savePosition = async () => {
  if (saving.value || !editing.value) return
  saving.value = true
  error.value = ''
  try {
    const saved = await updateClientProduct(props.clientUuid, editing.value)
    positions.value = positions.value.map(item => item.uuid === saved.uuid ? saved : item)
    editing.value = null
  } catch (cause) {
    error.value = productError(cause, 'client_product_error_save')
  } finally {
    saving.value = false
  }
}
const remove = async position => {
  if (saving.value || !window.confirm(t('ticky_crm', 'client_product_confirm_remove', { name: position.name }))) return
  saving.value = true
  error.value = ''
  try {
    await deleteClientProduct(props.clientUuid, position.uuid)
    positions.value = positions.value.filter(item => item.uuid !== position.uuid)
    if (editing.value?.uuid === position.uuid) editing.value = null
  } catch (cause) {
    error.value = productError(cause, 'client_product_error_save')
  } finally {
    saving.value = false
  }
}
onMounted(() => {
  loadPositions()
  search()
  loadProductOptions().catch(() => { error.value = t('ticky_crm', 'settings_error_load') })
})
onBeforeUnmount(() => { clearTimeout(searchTimer); searchId++ })
</script>

<style scoped>
.client-products-tab { padding: 24px; display: flex; flex-direction: column; gap: 20px; }
.position-create, .position-card form { display: flex; flex-direction: column; gap: 16px; }
.position-hint { color: var(--color-text-maxcontrast); font-size: 13px; }
.position-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 16px; }
.position-card { border: 1px solid var(--color-border); border-radius: var(--border-radius-large); padding: 12px; }
.position-card h3 { margin: 0; font-size: 16px; line-height: 1.3; font-weight: 600; overflow-wrap: anywhere; }
.position-card > .position-hint { margin: 2px 0 0; }
.position-values { display: grid; gap: 6px; margin: 10px 0; }
.position-values > div { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); align-items: baseline; gap: 12px; padding: 0; margin: 0; }
.position-values dt, .position-values dd { float: none; width: auto; min-width: 0; min-height: 0; margin: 0; padding: 0; line-height: 1.4; white-space: normal; overflow-wrap: anywhere; }
.position-values dt { text-align: start; color: var(--color-text-maxcontrast); font-size: 12px; }
.position-values dd { text-align: end; font-size: 13px; }
.position-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
</style>

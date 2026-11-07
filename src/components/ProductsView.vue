<template>
  <NcAppContent :page-heading="t('ticky_crm', 'nav_products')">
    <TickyHeader
      :title="t('ticky_crm', 'nav_products')"
      :subtitle="n('ticky_crm', 'product_count_singular', 'product_count_plural', products.length, { count: products.length })"
    >
      <template #actions>
        <NcButton type="primary" @click="creating = true">
          <template #icon>
            <IconPlus :size="20" />
          </template>
          {{ t('ticky_crm', 'product_create') }}
        </NcButton>
      </template>
    </TickyHeader>
    <div v-if="error" class="product-message" role="alert">
      <p>{{ error }}</p>
      <NcButton :disabled="loading" @click="load()">
        {{ t('ticky_crm', 'product_retry') }}
      </NcButton>
    </div>
    <p v-if="loading" class="product-message" role="status">
      {{ t('ticky_crm', 'product_loading') }}
    </p>
    <p v-else-if="!error && !products.length" class="product-message">
      {{ t('ticky_crm', 'product_empty') }}
    </p>
    <TickyTable
      v-if="products.length"
      :value="products"
      default-sort-field="name"
      @row-click="selected = $event"
    >
      <TickyColumn
        sortable
        field="name"
        :header="t('ticky_crm', 'product_name')"
        max-width="250px"
      />
      <TickyColumn sortable field="product_number" :header="t('ticky_crm', 'product_product_number')" />
      <TickyColumn
        sortable
        field="type"
        :sort-compare="(a, b) => compareOption('type', a, b)"
        :header="t('ticky_crm', 'product_type')"
      />
      <TickyColumn
        sortable
        field="sales_price_net"
        :sort-compare="comparePrice"
        :header="t('ticky_crm', 'product_sales_price_net')"
      />
      <TickyColumn
        sortable
        field="unit"
        :sort-compare="(a, b) => compareOption('unit', a, b)"
        :header="t('ticky_crm', 'product_unit')"
      />
      <TickyColumn
        sortable
        field="status"
        :sort-compare="(a, b) => compareOption('status', a, b)"
        :header="t('ticky_crm', 'field_status')"
      />
      <template #type="{ data }">
        {{ productOptionLabel('type', data.type) }}
      </template>
      <template #sales_price_net="{ data }">
        {{ decimalDisplay(data.sales_price_net) }} {{ data.currency }}
      </template>
      <template #status="{ data }">
        {{ productOptionLabel('status', data.status) }}
      </template>
      <template #unit="{ data }">
        {{ productOptionLabel('unit', data.unit) }}
      </template>
    </TickyTable>
    <NcButton
      v-if="hasMore && !error"
      class="load-more"
      :disabled="loading"
      @click="load()"
    >
      {{ t('ticky_crm', 'product_load_more') }}
    </NcButton>
    <NewProductDialog v-if="creating" v-model:open="creating" @product-created="created" />
  </NcAppContent>
  <NcAppSidebar
    v-if="selected"
    :title="selected.name"
    :subtitle="selected.product_number"
    :active="activeTab"
    @update:active="activeTab = $event"
    @close="selected = null"
  >
    <NcAppSidebarTab id="product-details" :name="t('ticky_crm', 'tab_details')" :tab-index="0">
      <template #icon>
        <IconPackage :size="20" />
      </template>
      <ProductTab
        :key="selected.uuid"
        :product="selected"
        @product-updated="updated"
        @product-deleted="deleted"
      />
    </NcAppSidebarTab>
    <ActivitySidebarTab
      id="product-activities"
      :order="1"
      :product-uuid="selected.uuid"
      :active="activeTab === 'product-activities'"
    />
  </NcAppSidebar>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { t, n } from '@nextcloud/l10n'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import NcButton from '@nextcloud/vue/components/NcButton'
import IconPlus from 'vue-material-design-icons/Plus.vue'
import IconPackage from 'vue-material-design-icons/PackageVariantClosed.vue'
import ActivitySidebarTab from './ActivitySidebarTab.vue'
import TickyHeader from './TickyHeader.vue'
import TickyTable from './TickyTable.vue'
import TickyColumn from './TickyColumn.vue'
import ProductTab from './ProductTab.vue'
import NewProductDialog from '../dialogs/NewProductDialog.vue'
import { getProducts, productError } from '../services/productService'
import { decimalDisplay, decimalCompare } from '../utils/decimal'
import { loadProductOptions, productOptionLabel } from '../services/productOptionsService'

const products = ref([])
const optionCollator = new Intl.Collator(undefined, { sensitivity: 'base', numeric: true })
const compareOption = (field, a, b) => optionCollator.compare(productOptionLabel(field, a[field]), productOptionLabel(field, b[field]))
const comparePrice = (a, b) => decimalCompare(a.sales_price_net, b.sales_price_net)
const selected = ref(null)
const activeTab = ref('product-details')
const creating = ref(false)
const loading = ref(false)
const error = ref('')
const hasMore = ref(false)
let offset = 0
let requestId = 0
const load = async (reset = false) => {
  if (loading.value && !reset) return
  const currentRequest = ++requestId
  if (reset) {
    offset = 0
    products.value = []
    hasMore.value = false
  }
  loading.value = true
  error.value = ''
  try {
    const page = await getProducts(offset)
    if (currentRequest !== requestId) return
    const existing = new Set(products.value.map(product => product.uuid))
    products.value.push(...page.filter(product => !existing.has(product.uuid)))
    offset += page.length
    hasMore.value = page.length === 100
  } catch (cause) {
    if (currentRequest === requestId) error.value = productError(cause, 'product_error_load')
  } finally {
    if (currentRequest === requestId) loading.value = false
  }
}
const created = (product) => {
  selected.value = product
  load(true)
}
const updated = (product) => {
  if (selected.value?.uuid === product.uuid) selected.value = product
  load(true)
}
const deleted = (uuid) => {
  if (selected.value?.uuid === uuid) selected.value = null
  load(true)
}
onMounted(() => {
  load()
  loadProductOptions().catch(() => { error.value = t('ticky_crm', 'settings_error_load') })
})
</script>

<style scoped>
.product-message { padding: 16px 24px; color: var(--color-text-maxcontrast); }
.load-more { margin: 16px 24px; }
</style>

<template>
  <NcAppSidebarTab :id="id" :name="t('ticky_crm', 'tab_activities')" :order="order">
    <template #icon>
      <IconHistory :size="20" />
    </template>
    <ActivityTab
      :key="productUuid || clientUuid"
      :client-uuid="clientUuid"
      :product-uuid="productUuid"
      :active="active"
    />
  </NcAppSidebarTab>
</template>

<script setup>
import { t } from '@nextcloud/l10n'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import IconHistory from 'vue-material-design-icons/History.vue'
import ActivityTab from './ActivityTab.vue'

defineProps({
  id: { type: String, required: true },
  order: { type: Number, default: 0 },
  clientUuid: { type: String, default: '' },
  productUuid: { type: String, default: '' },
  active: { type: Boolean, default: true },
})
</script>

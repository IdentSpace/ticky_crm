<template>
  <NcContent app-name="ticky">
    <NcAppNavigation>
      <template #list>
        <NcAppNavigationItem
          v-for="item in navItems"
          :key="item.id"
          :name="item.name"
          :active="activeNav === item.id"
          @click="activeNav = item.id"
        >
          <template #icon>
            <component :is="item.icon" :size="20" />
          </template>
        </NcAppNavigationItem>
      </template>

      <template #footer>
        <div class="navigation__footer">
          <NcButton wide @click="openSettings">
            <template #icon>
              <IconCogOutline :size="20" />
            </template>
            {{ t('ticky_crm', 'nav_settings') }}
          </NcButton>

          <SettingsDialog v-if="showSettingsModal" @close="closeSettings" />
        </div>
      </template>
    </NcAppNavigation>

    <ProductsView v-if="activeNav === 'products'" />

    <NcAppContent v-if="activeNav === 'clients'" :page-heading="t('ticky_crm', 'page_heading_clients')">
      <TickyHeader
        :title="t('ticky_crm', 'app_title')"
        :subtitle="n('ticky_crm', 'client_count_singular', 'client_count_plural', clients.length, { count: clients.length })"
      >
        <template #actions>
          <div class="client-status-filter">
            <NcActions
              :menu-name="statusFilterLabel"
              :title="statusFilterLabel"
              force-menu
              wide
            >
              <template #icon>
                <IconChevronDown :size="20" />
              </template>
              <NcActionCheckbox
                v-for="option in statusFilterOptions"
                :key="option.id"
                :model-value="statusFilters.some(selected => selected.id === option.id)"
                @update:model-value="setStatusFilter(option, $event)"
              >
                {{ option.label }}
              </NcActionCheckbox>
            </NcActions>
          </div>

          <NcButton type="primary" @click="isDialogOpen = true">
            <template #icon>
              <IconPlus :size="20" />
            </template>
            {{ t('ticky_crm', 'client_create_submit') }}
          </NcButton>
        </template>
      </TickyHeader>

      <TickyTable :value="filteredClients" default-sort-field="name" @row-click="selectedClient = $event">
        <TickyColumn
          sortable
          field="follow_up_status"
          align="center"
          :sort-compare="compareFollowUp"
          :header="t('ticky_crm', 'follow_up_title')"
        />
        <template #follow_up_status="{ data }">
          <FollowUpStatus :status="data.follow_up_status" icon-only />
        </template>
        <TickyColumn
          sortable
          field="name"
          :header="t('ticky_crm', 'column_name')"
          max-width="250px"
        />
        <TickyColumn sortable field="client_number" :header="t('ticky_crm', 'column_client_number')" />
        <TickyColumn sortable field="contact_email" :header="t('ticky_crm', 'column_email')" />
        <TickyColumn sortable field="phone" :header="t('ticky_crm', 'column_phone')" />
        <TickyColumn
          sortable
          field="product_count"
          align="center"
          :header="t('ticky_crm', 'nav_products')"
        />
        <TickyColumn
          sortable
          field="contact_count"
          align="center"
          :header="t('ticky_crm', 'column_contacts')"
        />
        <template #product_count="{ data }">
          {{ data.product_count ?? 0 }}
        </template>
        <template #contact_count="{ data }">
          {{ data.contact_count ?? 0 }}
        </template>
      </TickyTable>

      <NewClientDialog
        v-model:open="isDialogOpen"
        @client-created="handleClientCreated"
      />
    </NcAppContent>

    <NcAppSidebar
      v-if="activeNav === 'clients' && selectedClient"
      :title="selectedClient.name"
      :subtitle="selectedClient.client_number"
      :active="activeSidebarTab"
      @update:active="activeSidebarTab = $event"
      @close="selectedClient = null"
    >
      <NcAppSidebarTab id="ticky-details" :name="t('ticky_crm', 'tab_details')" :tab-index="0">
        <template #icon>
          <IconAccount :size="20" />
        </template>
        <ClientTab
          :client="selectedClient"
          @client-updated="handleClientUpdated"
          @client-deleted="handleClientDeleted"
        />
      </NcAppSidebarTab>

      <NcAppSidebarTab id="ticky-notes" :name="t('ticky_crm', 'tab_notes')" :tab-index="1">
        <template #icon>
          <IconNoteText :size="20" />
        </template>
        <ClientNotesTab :key="selectedClient.uuid" :client-id="selectedClient.id" @client-updated="handleNoteClientUpdated" />
      </NcAppSidebarTab>

      <NcAppSidebarTab id="contacts" :name="t('ticky_crm', 'tab_contacts')" icon="icon-contacts-dark">
        <ContactTab :key="selectedClient.uuid" :client-id="selectedClient.uuid" @count-changed="updateClientCount('contact_count', $event)" />
      </NcAppSidebarTab>

      <NcAppSidebarTab id="ticky-products" :name="t('ticky_crm', 'nav_products')" :tab-index="2">
        <template #icon>
          <IconPackage :size="20" />
        </template>
        <ClientProductsTab :key="selectedClient.uuid" :client-uuid="selectedClient.uuid" @count-changed="updateClientCount('product_count', $event)" />
      </NcAppSidebarTab>

      <ActivitySidebarTab
        id="ticky-activities"
        :order="3"
        :client-uuid="selectedClient.uuid"
        :active="activeSidebarTab === 'ticky-activities'"
      />
    </NcAppSidebar>
  </NcContent>
</template>

<script setup>
import { ref, onMounted, nextTick, computed } from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { t, n } from '@nextcloud/l10n'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionCheckbox from '@nextcloud/vue/components/NcActionCheckbox'
import IconChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import IconPlus from 'vue-material-design-icons/Plus.vue'
import IconAccount from 'vue-material-design-icons/Account.vue'
import IconAccountMultiple from 'vue-material-design-icons/AccountMultiple.vue'
import IconNoteText from 'vue-material-design-icons/NoteTextOutline.vue'
import IconCogOutline from 'vue-material-design-icons/CogOutline.vue'
import IconPackage from 'vue-material-design-icons/PackageVariantClosed.vue'
import ProductsView from './components/ProductsView.vue'
import TickyTable from './components/TickyTable.vue'
import TickyColumn from './components/TickyColumn.vue'
import TickyHeader from './components/TickyHeader.vue'
import NewClientDialog from './dialogs/NewClientDialog.vue'
import ActivitySidebarTab from './components/ActivitySidebarTab.vue'
import ClientTab from './components/ClientTab.vue'
import ClientNotesTab from './components/ClientNotesTab.vue'
import ContactTab from './components/ContactTab.vue'
import ClientProductsTab from './components/ClientProductsTab.vue'
import { getClients } from './services/clientService'
import FollowUpStatus from './components/FollowUpStatus.vue'
import SettingsDialog from './dialogs/SettingsDialog.vue'

// App State
const isDialogOpen     = ref(false)
const clients          = ref([])
const statusFilters = ref([])
const statusFilterLabel = computed(() => statusFilters.value.length
  ? statusFilters.value.map(option => option.label).join(', ')
  : t('ticky_crm', 'follow_up_filter'))
const setStatusFilter = (option, checked) => {
  statusFilters.value = checked
    ? [...statusFilters.value.filter(selected => selected.id !== option.id), option]
    : statusFilters.value.filter(selected => selected.id !== option.id)
}
const statusFilterOptions = computed(() => ['red', 'yellow', 'green', 'unknown', 'inactive'].map(id => ({
  id, label: t('ticky_crm', 'follow_up_' + id),
})))
const filteredClients = computed(() => clients.value.filter(client => statusFilters.value.length === 0 || statusFilters.value.some(option => option.id === client.follow_up_status)))
const compareFollowUp = (a, b) => {
  const priority = { red: 0, yellow: 1, green: 2, unknown: 3, inactive: 4 }
  return priority[a.follow_up_status] - priority[b.follow_up_status]
    || (a.last_contact_date ?? '9999-12-31').localeCompare(b.last_contact_date ?? '9999-12-31')
}
const selectedClient   = ref(null)
const activeSidebarTab = ref('ticky-details')
const activeNav        = ref('clients')

// Navigation reaktiv übersetzen via computed
const navItems = computed(() => [
  { id: 'clients', name: t('ticky_crm', 'nav_clients'), icon: IconAccountMultiple },
  { id: 'products', name: t('ticky_crm', 'nav_products'), icon: IconPackage },
])

// Settings State
const showSettingsModal = ref(false)
const settingsLoading   = ref(false)
const settingsError     = ref('')
const selectedGroups    = ref([])
const selectedUsers     = ref([])
const groupOptions      = ref([])
const userOptions       = ref([])

const closeSettings = async () => {
  showSettingsModal.value = false
  const uuid = selectedClient.value?.uuid
  await loadClients()
  if (uuid) selectedClient.value = clients.value.find(client => client.uuid === uuid) ?? null
}

const openSettings = async () => {
  showSettingsModal.value = true
  settingsLoading.value   = true
  settingsError.value     = ''

  try {
    const url      = generateUrl('/apps/ticky_crm/api/v1/settings')
    const response = await axios.get(url)
    const data     = response.data

    groupOptions.value = data.all_groups
    userOptions.value  = data.all_users

    selectedGroups.value = data.all_groups.filter(g => data.allowed_groups.includes(g.id))
    selectedUsers.value  = data.all_users.filter(u => data.allowed_users.includes(u.id))
  } catch (error) {
    settingsError.value = t('ticky_crm', 'settings_error_load')
    console.error(error)
  } finally {
    settingsLoading.value = false
  }
}

// Client Handlers
const handleClientCreated = (newClient) => {
  clients.value.push(newClient)
}

const handleNoteClientUpdated = (updatedClient) => {
  const index = clients.value.findIndex(client => client.id === updatedClient.id)
  if (index !== -1) clients.value[index] = { ...clients.value[index], ...updatedClient }
  if (selectedClient.value?.id === updatedClient.id) {
    selectedClient.value = { ...selectedClient.value, ...updatedClient }
  }
}

const handleClientUpdated = (updatedClient) => {
  const index = clients.value.findIndex(c => c.id === updatedClient.id)
  const merged = { ...(clients.value[index] ?? {}), ...updatedClient }
  if (index !== -1) clients.value[index] = merged
  selectedClient.value = merged
}

const updateClientCount = (field, { clientUuid, count }) => {
  const client = clients.value.find(item => item.uuid === clientUuid)
  if (client) client[field] = count
  if (selectedClient.value?.uuid === clientUuid) selectedClient.value[field] = count
}

const handleClientDeleted = (deletedClientId) => {
  clients.value = clients.value.filter(c => c.uuid !== deletedClientId)
  selectedClient.value = null
}

const loadClients = async () => {
  try {
    clients.value = await getClients()
  } catch (error) {
    console.error(error)
  }
}

onMounted(() => {
  loadClients().then(async () => {
    const params   = new URLSearchParams(window.location.search)
    const clientId = params.get('client')
    const tab      = params.get('tab')

    if (clientId) {
      const client = clients.value.find(c => c.id === parseInt(clientId))
      if (client) {
        selectedClient.value = client
        await nextTick()
        if (tab === 'notes') activeSidebarTab.value = 'ticky-notes'
      }
    }
  })
})
</script>
<style lang="scss" scoped>
.client-status-filter {
  width: clamp(180px, 25vw, 280px);
  max-width: 280px;
  flex: 0 0 auto;
  min-width: 0;

  :deep(.action-items),
  :deep(.action-item) {
    width: 100%;
    min-width: 0;
    max-width: 100%;
  }

  :deep(.action-item__menutoggle) {
    width: 100%;
    min-width: 0;
    max-width: 100%;
    height: var(--default-clickable-area);
  }

  :deep(.button-vue__text) {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}
.navigation__footer {
  display: flex;
  align-items: center;
  padding: 8px;
  width: 100%;
  box-sizing: border-box;
}

.ticky-modal-settings {
  padding: 24px;
  min-width: 420px;
  max-width: 100%;
  display: flex;
  flex-direction: column;
  gap: 20px;

  h3 {
    font-size: 16px;
    font-weight: 600;
    margin: 0;
    color: var(--color-main-text);
  }

  .settings-description {
    font-size: 13px;
    color: var(--color-text-maxcontrast);
    line-height: 1.5;
    margin: 0;
  }
}

.setting-row {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.settings-actions {
  display: flex;
  justify-content: flex-end;
  padding-top: 4px;
}

.settings-error {
  color: var(--color-error);
  font-size: 13px;
  margin: 0;
}

.settings-loading {
  color: var(--color-text-maxcontrast);
  font-size: 13px;
  padding: 16px 0;
  text-align: center;
}
</style>

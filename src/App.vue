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

          <SettingsDialog v-if="showSettingsModal" @close="showSettingsModal = false" />
        </div>
      </template>
    </NcAppNavigation>

    <NcAppContent :page-heading="t('ticky_crm', 'page_heading_clients')">
      <TickyHeader
        :title="t('ticky_crm', 'app_title')"
        :subtitle="n('ticky_crm', 'client_count_singular', 'client_count_plural', clients.length, { count: clients.length })"
      >
        <template #actions>
          <NcButton type="primary" @click="isDialogOpen = true">
            <template #icon>
              <IconPlus :size="20" />
            </template>
            {{ t('ticky_crm', 'client_create_submit') }}
          </NcButton>
        </template>
      </TickyHeader>

      <TickyTable :value="clients" @row-click="selectedClient = $event">
        <TickyColumn field="name" :header="t('ticky_crm', 'column_name')" />
        <TickyColumn field="client_number" :header="t('ticky_crm', 'column_client_number')" />
        <TickyColumn field="contact_email" :header="t('ticky_crm', 'column_email')" />
        <TickyColumn field="phone" :header="t('ticky_crm', 'column_phone')" />
      </TickyTable>

      <NewClientDialog
        v-model:open="isDialogOpen"
        @client-created="handleClientCreated"
      />
    </NcAppContent>

    <NcAppSidebar
      v-if="selectedClient"
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
        <ClientNotesTab :client-id="selectedClient.id" />
      </NcAppSidebarTab>
    </NcAppSidebar>
  </NcContent>
</template>

<script setup>
import { ref, onMounted, nextTick, computed } from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { t, n } from '@nextcloud/l10n' // Lokalisierungsmethoden importieren

import NcContent from '@nextcloud/vue/components/NcContent'
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import NcButton from '@nextcloud/vue/components/NcButton'

import IconPlus from 'vue-material-design-icons/Plus.vue'
import IconAccount from 'vue-material-design-icons/Account.vue'
import IconAccountMultiple from 'vue-material-design-icons/AccountMultiple.vue'
import IconNoteText from 'vue-material-design-icons/NoteTextOutline.vue'
import IconCogOutline from 'vue-material-design-icons/CogOutline.vue'

import TickyTable from './components/TickyTable.vue'
import TickyColumn from './components/TickyColumn.vue'
import TickyHeader from './components/TickyHeader.vue'
import NewClientDialog from './dialogs/NewClientDialog.vue'
import ClientTab from './components/ClientTab.vue'
import ClientNotesTab from './components/ClientNotesTab.vue'
import { getClients } from './services/clientService'
import SettingsDialog from './dialogs/SettingsDialog.vue'

// App State
const isDialogOpen     = ref(false)
const clients          = ref([])
const selectedClient   = ref(null)
const activeSidebarTab = ref('ticky-details')
const activeNav        = ref('clients')

// Navigation reaktiv übersetzen via computed
const navItems = computed(() => [
  { id: 'clients', name: t('ticky_crm', 'nav_clients'), icon: IconAccountMultiple }
])

// Settings State
const showSettingsModal = ref(false)
const settingsLoading   = ref(false)
const settingsError     = ref('')
const selectedGroups    = ref([])
const selectedUsers     = ref([])
const groupOptions      = ref([])
const userOptions       = ref([])

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

const handleClientUpdated = (updatedClient) => {
  const index = clients.value.findIndex(c => c.id === updatedClient.id)
  if (index !== -1) clients.value[index] = updatedClient
  selectedClient.value = updatedClient
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
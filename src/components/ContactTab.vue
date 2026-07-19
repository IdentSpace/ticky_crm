<template>
  <div class="client-contacts-tab">
    <div class="search-container">
      <NcSelect
        v-model="selectedContact"
        :options="searchResults"
        :loading="isSearching"
        label="displayName"
        :placeholder="t('ticky_crm', 'contact_search_placeholder')"
        @search="onSearchContacts"
      >
        <template #option="{ option }">
          <div class="contact-option">
            <NcAvatar
              v-if="option?.displayName"
              :display-name="option.displayName"
              :is-no-user="true"
              :size="24"
            />
            <div class="contact-option__text">
              <span class="contact-option__name">{{ option?.displayName }}</span>
              <span v-if="option.email" class="contact-option__email text-muted">{{ option?.email }}</span>
            </div>
          </div>
        </template>
        <template #no-options>
          {{ t('ticky_crm', 'contact_no_results') }}
        </template>
      </NcSelect>

      <NcButton
        type="primary"
        :disabled="!selectedContact || isLinking"
        @click="addContact"
      >
        <template v-if="isLinking" #icon>
          <NcLoadingIcon :size="20" />
        </template>
        {{ t('ticky_crm', 'contact_link_button') }}
      </NcButton>
    </div>

    <NcLoadingIcon v-if="isLoadingList" :name="t('ticky_crm', 'contact_loading')" />

    <ul v-else class="contacts-list">
      <li v-for="contact in linkedContacts" :key="contact.id" class="contact-item">
        <div class="contact-info">
          <NcAvatar
            :display-name="contact.displayName"
            :is-no-user="true"
            :size="32"
          />
          <div class="contact-details">
            <span class="contact-name">{{ contact.displayName }}</span>
            <span class="contact-meta-column">
              <span v-for="e in contact.emails" :key="e.value" class="contact-meta">
                <IconEmail :size="14" />
                {{ e.value }}
              </span>
              <span v-for="e in contact.phones" :key="e.value" class="contact-meta">
                <IconPhone :size="14" />
                {{ e.value }}
              </span>
            </span>
          </div>
        </div>

        <NcActions :aria-label="t('ticky_crm', 'contact_actions_aria_label', { name: contact.displayName })">
          <NcActionButton @click="openContactDetails(contact)">
            <template #icon>
              <IconEye :size="20" />
            </template>
            {{ t('ticky_crm', 'contact_view_details') }}
          </NcActionButton>
          <NcActionButton
            :disabled="removingId === contact.id"
            @click="removeContact(contact.id)"
          >
            <template #icon>
              <NcLoadingIcon v-if="removingId === contact.id" :size="20" />
              <IconDelete v-else :size="20" />
            </template>
            {{ t('ticky_crm', 'contact_remove_link') }}
          </NcActionButton>
        </NcActions>
      </li>
      <li v-if="linkedContacts.length === 0" class="empty-state text-muted">
        {{ t('ticky_crm', 'contact_empty_state') }}
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcSelect, NcAvatar, NcLoadingIcon, NcActions, NcActionButton } from '@nextcloud/vue'

import { showError, showSuccess, showInfo } from '@nextcloud/dialogs'
import IconEye from 'vue-material-design-icons/Eye.vue'
import IconDelete from 'vue-material-design-icons/Delete.vue'
import IconEmail from 'vue-material-design-icons/EmailOutline.vue'
import IconPhone from 'vue-material-design-icons/PhoneOutline.vue'

const props = defineProps({
  clientId: {
    type: Number,
    required: true,
  },
})

defineEmits(['view-details'])

const linkedContacts = ref([])
const searchResults = ref([])
const selectedContact = ref(null)
const isSearching = ref(false)
const isLoadingList = ref(false)
const isLinking = ref(false)
const removingId = ref(null)

let searchDebounceTimer = null

async function fetchLinkedContacts() {
  isLoadingList.value = true
  try {
    const response = await axios.get(
        generateUrl(`/apps/ticky_crm/api/v1/clients/${props.clientId}/contacts`),
    )
    linkedContacts.value = response.data
  } catch (error) {
    console.error('Ticky CRM: Kontakte konnten nicht geladen werden', error)
    showError(t('ticky_crm', 'contact_error_load'))
  } finally {
    isLoadingList.value = false
  }
}

function onSearchContacts(searchQuery) {
  window.clearTimeout(searchDebounceTimer)

  if (!searchQuery || searchQuery.trim().length < 2) {
    searchResults.value = []
    return
  }

  searchDebounceTimer = window.setTimeout(async () => {
    isSearching.value = true
    try {
      const response = await axios.get(
          generateUrl('/apps/ticky_crm/api/v1/contacts/search'),
          { params: { query: searchQuery } },
      )
      const results = Array.isArray(response.data) ? response.data : []

      // Bereits verknüpfte Kontakte gar nicht erst im Dropdown anbieten,
      // damit man sie nicht versehentlich ein zweites Mal auswählt.
      const linkedIds = new Set(linkedContacts.value.map((c) => c.id))
      searchResults.value = results.filter((c) => !linkedIds.has(c.id))
    } catch (error) {
      console.error('Ticky CRM: Kontaktsuche fehlgeschlagen', error)
      showError(t('ticky_crm', 'contact_error_search'))
      searchResults.value = []
    } finally {
      isSearching.value = false
    }
  }, 300)
}

async function addContact() {
  if (!selectedContact.value?.id) {
    console.warn('Ticky CRM: selectedContact hat keine gültige id', selectedContact.value)
    showError(t('ticky_crm', 'contact_error_invalid'))
    return
  }

  isLinking.value = true
  try {
    const response = await axios.post(
        generateUrl(`/apps/ticky_crm/api/v1/clients/${props.clientId}/contacts`),
        { cardId: selectedContact.value.id },
    )
    const newContact = response.data

    // Absicherung gegen doppelte Einträge in der Liste, z. B. falls derselbe
    // Kontakt trotz Filterung im Dropdown ein zweites Mal ausgewählt wurde.
    const alreadyInList = linkedContacts.value.some((c) => c.id === newContact.id)
    if (!alreadyInList) {
      linkedContacts.value.push(newContact)
    }

    if (response.status === 201) {
      showSuccess(t('ticky_crm', 'contact_success_linked'))
    } else {
      showInfo(t('ticky_crm', 'contact_info_already_linked'))
    }

    selectedContact.value = null
    searchResults.value = []
  } catch (error) {
    console.error('Ticky CRM: Kontakt konnte nicht verknüpft werden', error)
    showError(
        error.response?.data?.message
        ?? t('ticky_crm', 'contact_error_link_failed'),
    )
  } finally {
    isLinking.value = false
  }
}

async function removeContact(cardId) {
  removingId.value = cardId
  try {
    await axios.delete(
        generateUrl(`/apps/ticky_crm/api/v1/clients/${props.clientId}/contacts/${cardId}`),
    )
    linkedContacts.value = linkedContacts.value.filter((c) => c.id !== cardId)
    showSuccess(t('ticky_crm', 'contact_success_unlinked'))
  } catch (error) {
    console.error('Ticky CRM: Verknüpfung konnte nicht entfernt werden', error)
    showError(t('ticky_crm', 'contact_error_unlink_failed'))
  } finally {
    removingId.value = null
  }
}

function openContactDetails(contact) {
  if (!contact) {
    showError(t('ticky_crm', 'contact_error_missing_uid'))
    return
  }

  console.log(contact.url)

  // Option A: In einem neuen Browsertab öffnen (Empfohlen für CRM-Workflows)
  window.open(contact.url, '_blank', 'noopener,noreferrer')
}

watch(() => props.clientId, fetchLinkedContacts, { immediate: true })
</script>

<style scoped>
.client-contacts-tab {
  padding: 16px;
}
.search-container {
  display: flex;
  gap: 8px;
  margin-bottom: 20px;
  align-items: flex-start;
}
.search-container :deep(.nc-select) {
  flex-grow: 1;
}
.contact-option {
  display: flex;
  align-items: center;
  gap: 8px;
}
.contact-option__text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.contact-option__name {
  font-weight: 600;
}
.contact-option__email {
  font-size: 0.85em;
}
.contacts-list {
  list-style: none;
  padding: 0;
  margin: 0;
}
.contact-item {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 10px 0;
  border-bottom: 1px solid var(--color-border);
}
.contact-info {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  min-width: 0;
}
.contact-details {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.contact-name {
  font-weight: bold;
}
.contact-meta-column {
  display: flex;
  flex-flow: column;
  gap: 12px;
}
.contact-meta {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 0.85em;
  color: var(--color-text-maxcontrast);
  white-space: nowrap;
}
.empty-state {
  text-align: center;
  padding: 20px;
}
</style>
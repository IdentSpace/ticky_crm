<template>
  <div class="ticky-client-notes">
    <form class="ticky-note-form" @submit.prevent="handleCreate">
      <input
        v-model="newNote.title"
        type="text"
        :placeholder="t('ticky_crm', 'note_title_placeholder')"
        class="ticky-input"
      >

      <select v-model="newNote.type" class="ticky-input">
        <option value="note">
          📝 {{ t('ticky_crm', 'note_type_note') }}
        </option>
        <option value="phone">
          📞 {{ t('ticky_crm', 'note_type_phone') }}
        </option>
        <option value="meeting">
          🤝 {{ t('ticky_crm', 'note_type_meeting') }}
        </option>
      </select>

      <div class="ticky-textarea-wrapper">
        <textarea
          ref="newNoteTextarea"
          v-model="newNote.content"
          :placeholder="t('ticky_crm', 'note_content_placeholder')"
          required
          rows="4"
          class="ticky-textarea"
          @input="(e) => checkForMention(e, 'new')"
          @keyup.esc="closeMentionDropdown"
        />

        <div v-if="showMentionDropdown && activeMentionTarget === 'new'" class="ticky-mention-dropdown">
          <div v-if="isSearchingUsers" class="ticky-mention-loading">
            {{ t('ticky_crm', 'mention_searching') }}
          </div>
          <div v-else-if="mentionUsers.length === 0" class="ticky-mention-empty">
            {{ t('ticky_crm', 'mention_empty') }}
          </div>
          <button
            v-for="user in mentionUsers"
            v-else
            :key="user.id"
            type="button"
            class="ticky-mention-item"
            @click.prevent="insertMention(user, 'new')"
          >
            <strong>{{ user.label }}</strong> <small>({{ user.id }})</small>
          </button>
        </div>
      </div>

      <div class="ticky-form-actions">
        <button type="submit" class="primary" :disabled="loading || !newNote.content.trim()">
          {{ t('ticky_crm', 'note_save') }}
        </button>
      </div>
    </form>

    <hr class="ticky-separator">

    <div v-if="loading && notes.length === 0" class="ticky-state-message">
      {{ t('ticky_crm', 'note_loading') }}
    </div>

    <div v-else-if="notes.length === 0" class="ticky-state-message">
      {{ t('ticky_crm', 'note_empty_state') }}
    </div>

    <div v-else class="ticky-notes-list">
      <div
        v-for="note in notes"
        :key="note.id"
        class="ticky-note-item"
        :class="'type-' + note.type"
      >
        <div class="ticky-note-header">
          <span class="ticky-note-icon">{{ getTypeIcon(note.type) }}</span>
          <strong class="ticky-note-title">
            {{ note.title || t('ticky_crm', 'note_type_default') }}
          </strong>
          <span class="ticky-note-meta">
            {{ formatUser(note.user_id) }} • {{ formatDate(note.created_at) }}
          </span>
        </div>

        <div class="ticky-note-body">
          <div v-if="editingId === note.id" class="ticky-edit-mode">
            <div class="ticky-textarea-wrapper">
              <textarea
                :ref="el => { if (el) editNoteTextareaEl = el }"
                v-model="editData.content"
                class="ticky-textarea"
                rows="3"
                @input="(e) => checkForMention(e, 'edit')"
                @keyup.esc="closeMentionDropdown"
              />

              <div v-if="showMentionDropdown && activeMentionTarget === 'edit'" class="ticky-mention-dropdown">
                <div v-if="isSearchingUsers" class="ticky-mention-loading">
                  {{ t('ticky_crm', 'mention_searching') }}
                </div>
                <div v-else-if="mentionUsers.length === 0" class="ticky-mention-empty">
                  {{ t('ticky_crm', 'mention_empty') }}
                </div>
                <button
                  v-for="user in mentionUsers"
                  v-else
                  :key="user.id"
                  type="button"
                  class="ticky-mention-item"
                  @click.prevent="insertMention(user, 'edit')"
                >
                  <strong>{{ user.label }}</strong> <small>({{ user.id }})</small>
                </button>
              </div>
            </div>

            <div class="ticky-edit-actions">
              <button class="primary" @click="handleUpdate(note.id)">
                {{ t('ticky_crm', 'note_edit_ok') }}
              </button>
              <button @click="cancelEdit">
                {{ t('ticky_crm', 'client_cancel') }}
              </button>
            </div>
          </div>
          <!-- eslint-disable-next-line -->
          <p v-else class="ticky-note-text" v-html="safeMentions(renderMentions(note.content))" />
        </div>

        <div v-if="editingId !== note.id" class="ticky-context-menu">
          <button
            class="ticky-menu-trigger"
            :title="t('ticky_crm', 'note_options')"
            @click.stop="toggleMenu(note.id)"
          >
            ⋮
          </button>

          <div v-if="activeMenuId === note.id" class="ticky-dropdown">
            <button @click.stop="startEdit(note)">
              ✏️ {{ t('ticky_crm', 'note_edit') }}
            </button>
            <button class="ticky-text-danger" @click.stop="handleDelete(note.id)">
              🗑️ {{ t('ticky_crm', 'note_delete') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, watch, onMounted, onUnmounted, nextTick } from 'vue'
import axios from '@nextcloud/axios'
import DOMPurify from 'dompurify'
import { generateUrl } from '@nextcloud/router'
import { t } from '@nextcloud/l10n' // Native Nextcloud Lokalisierung importieren

const props = defineProps({
  clientId: {
    type: Number,
    required: true
  }
})

const notes = ref([])
const loading = ref(false)
const activeMenuId = ref(null)
const showMentionDropdown = ref(false)
const activeMentionTarget = ref(null)
const isSearchingUsers = ref(false)
const mentionUsers = ref([])
const newNoteTextarea = ref(null)

let editNoteTextareaEl = null

const newNote = reactive({ title: '', content: '', type: 'note' })
const editingId = ref(null)
const editData = reactive({ title: '', content: '', type: 'note' })

const getTextareaEl = (target) => {
  return target === 'new' ? newNoteTextarea.value : editNoteTextareaEl
}

const fetchNotes = async () => {
  if (!props.clientId) return
  loading.value = true
  try {
    const url = generateUrl(`/apps/ticky_crm/api/v1/clients/${props.clientId}/notes`)
    const response = await axios.get(url)
    notes.value = response.data
  } catch (error) {
    console.error('Fehler beim Laden der Notizen:', error)
  } finally {
    loading.value = false
  }
}

watch(() => props.clientId, fetchNotes, { immediate: true })

const handleCreate = async () => {
  if (!newNote.content.trim()) return
  try {
    const url = generateUrl(`/apps/ticky_crm/api/v1/clients/${props.clientId}/notes`)
    const response = await axios.post(url, newNote)
    notes.value.unshift(response.data)
    newNote.title = ''
    newNote.content = ''
    newNote.type = 'note'
  } catch (error) {
    console.error('Fehler beim Erstellen der Notiz:', error)
  }
}

const startEdit = (note) => {
  editingId.value = note.id
  Object.assign(editData, note)
  closeMenu()
  nextTick(() => {
    editNoteTextareaEl?.focus()
  })
}

const cancelEdit = () => {
  editingId.value = null
  editNoteTextareaEl = null
  closeMentionDropdown()
}

const handleUpdate = async (id) => {
  try {
    const url = generateUrl(`/apps/ticky_crm/api/v1/notes/${id}`)
    const response = await axios.put(url, editData)
    const index = notes.value.findIndex(n => n.id === id)
    if (index !== -1) {
      notes.value[index] = response.data
    }
    cancelEdit()
  } catch (error) {
    console.error('Fehler beim Aktualisieren:', error)
  }
}

const handleDelete = async (id) => {
  // Übersetzter JS-Confirm Dialog
  if (!confirm(t('ticky_crm', 'confirm_delete_note'))) return
  try {
    const url = generateUrl(`/apps/ticky_crm/api/v1/notes/${id}`)
    await axios.delete(url)
    notes.value = notes.value.filter(n => n.id !== id)
    closeMenu()
  } catch (error) {
    console.error('Fehler beim Löschen:', error)
  }
}

const toggleMenu = (id) => {
  activeMenuId.value = activeMenuId.value === id ? null : id
}

const closeMenu = () => {
  activeMenuId.value = null
}

const checkForMention = async (event, target) => {
  const cursorPosition = event.target.selectionStart
  const content = target === 'new' ? newNote.content : editData.content
  const textBeforeCursor = content.substring(0, cursorPosition)
  const words = textBeforeCursor.split(/\s+/)
  const currentWord = words[words.length - 1]

  if (currentWord.startsWith('@')) {
    activeMentionTarget.value = target
    showMentionDropdown.value = true
    await fetchNextcloudUsers(currentWord.substring(1))
  } else {
    closeMentionDropdown()
  }
}

const safeMentions = (rawHtml) => {
  return DOMPurify.sanitize(rawHtml, {
    ALLOWED_TAGS: ['span', 'br', 'strong', 'em'], // Erlaube Spans (für Badges) und Zeilenumbrüche
    ALLOWED_ATTR: ['class', 'data-username']      // Erlaube deine Styling-Klassen
  })
}

const fetchNextcloudUsers = async (searchStr) => {
  isSearchingUsers.value = true
  try {
    const url = generateUrl('/ocs/v1.php/core/autocomplete/get')
    const response = await axios.get(url, {
      params: { search: searchStr, itemType: 'users', format: 'json' }
    })
    mentionUsers.value = response.data.ocs?.data || []
  } catch (error) {
    console.error('Fehler bei der Nutzersuche:', error)
    mentionUsers.value = []
  } finally {
    isSearchingUsers.value = false
  }
}

const insertMention = (user, target) => {
  const textareaEl = getTextareaEl(target)
  if (!textareaEl) return

  const cursorPosition = textareaEl.selectionStart
  const content = target === 'new' ? newNote.content : editData.content
  const textBeforeCursor = content.substring(0, cursorPosition)
  const textAfterCursor = content.substring(cursorPosition)

  const wordsBefore = textBeforeCursor.split(/\s+/)
  wordsBefore.pop()

  const formattedMention = `[@${user.id}] `
  const newTextBefore = wordsBefore.length > 0
      ? wordsBefore.join(' ') + ' ' + formattedMention
      : formattedMention

  const newContent = newTextBefore + textAfterCursor

  if (target === 'new') {
    newNote.content = newContent
  } else {
    editData.content = newContent
  }

  closeMentionDropdown()
  setTimeout(() => textareaEl.focus(), 50)
}

const closeMentionDropdown = () => {
  showMentionDropdown.value = false
  activeMentionTarget.value = null
  mentionUsers.value = []
}

const renderMentions = (content) => {
  if (!content) return ''
  const escaped = content
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
  return escaped.replace(
      /\[@(.*?)\]/g,
      '<span class="ticky-mention-badge">@$1</span>'
  )
}

const getTypeIcon = (type) => {
  const icons = { note: '📝', phone: '📞', meeting: '🤝' }
  return icons[type] || '📄'
}

const formatDate = (dateString) => {
  if (!dateString) return ''
  return new Date(dateString).toLocaleString([], {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit'
  })
}

const formatUser = (userId) => userId

onMounted(() => document.addEventListener('click', closeMenu))
onUnmounted(() => document.removeEventListener('click', closeMenu))
</script>
<style lang="scss" scoped>
.ticky-client-notes {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 24px;

  .ticky-input,
  .ticky-textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--color-border);
    border-radius: var(--border-radius);
    background-color: var(--color-main-background);
    color: var(--color-main-text);
    box-sizing: border-box;
    font-family: inherit;

    &:focus {
      border-color: var(--color-primary);
      outline: none;
    }
  }

  .ticky-textarea {
    resize: vertical;
  }

  .ticky-note-form {
    display: flex;
    flex-direction: column;
    gap: 12px;
    background-color: var(--color-background-hover);
    padding: 16px;
    border-radius: var(--border-radius-large);

    .ticky-form-actions {
      display: flex;
      justify-content: flex-end;
      margin-top: 4px;
    }
  }

  .ticky-separator {
    border: 0;
    border-top: 1px solid var(--color-border);
    margin: 0;
  }

  .ticky-state-message {
    text-align: center;
    color: var(--color-text-maxcaption);
    padding: 24px 0;
  }

  .ticky-notes-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  .ticky-note-item {
    position: relative;
    padding: 16px;
    border-left: 4px solid var(--color-primary);
    background-color: var(--color-main-background);
    border-radius: 0 var(--border-radius) var(--border-radius) 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);

    &.type-phone { border-left-color: #3498db; }
    &.type-meeting { border-left-color: #2ecc71; }

    .ticky-note-header {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 12px;
      padding-right: 24px;

      .ticky-note-title {
        font-size: 14px;
        color: var(--color-main-text);
      }

      .ticky-note-meta {
        font-size: 12px;
        color: var(--color-text-maxcaption);
        margin-left: auto;
      }
    }

    .ticky-note-text {
      white-space: pre-wrap;
      margin: 0;
      font-size: 13px;
      color: var(--color-text-light);
      line-height: 1.5;
    }

    .ticky-context-menu {
      position: absolute;
      top: 12px;
      right: 8px;

      .ticky-menu-trigger {
        background: transparent;
        border: none;
        color: var(--color-text-maxcaption);
        font-size: 18px;
        padding: 4px 8px;
        cursor: pointer;
        border-radius: var(--border-radius);
        line-height: 1;

        &:hover {
          background-color: var(--color-background-hover);
          color: var(--color-main-text);
        }
      }

      .ticky-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        background-color: var(--color-main-background);
        border: 1px solid var(--color-border);
        border-radius: var(--border-radius);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 100;
        display: flex;
        flex-direction: column;
        min-width: 140px;
        overflow: hidden;

        button {
          background: transparent;
          border: none;
          padding: 10px 16px;
          text-align: left;
          font-size: 13px;
          cursor: pointer;
          color: var(--color-main-text);
          display: flex;
          align-items: center;
          gap: 8px;

          &:hover { background-color: var(--color-background-hover); }
          &.ticky-text-danger { color: var(--color-error); }
        }
      }
    }

    .ticky-edit-mode {
      display: flex;
      flex-direction: column;
      gap: 12px;

      .ticky-edit-actions {
        display: flex;
        gap: 8px;
      }
    }
  }
}

.ticky-textarea-wrapper {
  position: relative;
  width: 100%;
}

.ticky-mention-dropdown {
  position: absolute;
  top: 100%;
  left: 0;
  width: 100%;
  max-height: 200px;
  overflow-y: auto;
  background-color: var(--color-main-background);
  border: 1px solid var(--color-border);
  border-radius: 0 0 var(--border-radius) var(--border-radius);
  box-shadow: 0 4px 10px rgba(0,0,0,0.1);
  z-index: 100;
  display: flex;
  flex-direction: column;

  .ticky-mention-item {
    padding: 10px 12px;
    background: transparent;
    border: none;
    border-bottom: 1px solid var(--color-border);
    text-align: left;
    cursor: pointer;
    color: var(--color-main-text);

    &:last-child { border-bottom: none; }
    &:hover { background-color: var(--color-background-hover); }

    small {
      color: var(--color-text-maxcaption);
      margin-left: 8px;
    }
  }

  .ticky-mention-loading,
  .ticky-mention-empty {
    padding: 12px;
    text-align: center;
    color: var(--color-text-maxcaption);
    font-size: 13px;
  }
}

:deep(.ticky-mention-badge) {
  display: inline-block;
  background-color: var(--color-primary-light);
  color: var(--color-primary-element);
  border-radius: 4px;
  padding: 1px 6px;
  font-size: 12px;
  font-weight: 600;
}
</style>
<template>
  <div class="ticky-activities-container">
    <div v-if="error" class="activity-state" role="alert">
      <p>{{ error }}</p>
      <NcButton @click="fetchActivities">
        {{ t('ticky_crm', 'product_retry') }}
      </NcButton>
    </div>
    <div v-else-if="loading" class="activity-state activity-loading">
      <!--      <NcLoadingIcon :size="24" />-->
      <p>{{ t('ticky_crm', 'loading_activities') }}</p>
    </div>

    <div v-else-if="activities.length === 0" class="activity-state activity-empty">
      <p>{{ t('ticky_crm', 'no_activities_found') }}</p>
    </div>

    <div v-else class="activity-timeline">
      <div
        v-for="activity in activities"
        :key="activity.id"
        class="activity-item"
      >
        <div class="activity-meta">
          <span class="activity-author">
            @{{ activity.user }}
          </span>
          <span class="activity-time">
            {{ formatTimestamp(activity.timestamp) }}
          </span>
        </div>

        <div class="activity-content">
          {{ activity.parsedSubject }} {{ activity.objectName }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { t } from '@nextcloud/l10n'
import axios from '@nextcloud/axios';
import { generateUrl } from '@nextcloud/router'
import NcButton from '@nextcloud/vue/components/NcButton'

const props = defineProps({
  clientUuid: {
    type: String,
    default: ''
  },
  productUuid: { type: String, default: '' },
  active: { type: Boolean, default: true },
})

const activities = ref([])
const loading = ref(false)
const error = ref('')
let requestId = 0

/**
 * Holt die Aktivitäten vom neuen ActivityController
 */
const fetchActivities = async () => {
  const uuid = props.productUuid || props.clientUuid
  if (!uuid) return
  const current = ++requestId
  loading.value = true
  error.value = ''
  try {
    const resource = props.productUuid ? 'products' : 'clients'
    const url = generateUrl(`/apps/ticky_crm/api/v1/${resource}/${encodeURIComponent(uuid)}/activities`)
    const response = await axios.get(url)
    if (current === requestId) activities.value = response.data
  } catch {
    if (current === requestId) error.value = t('ticky_crm', 'activity_error_load')
  } finally {
    if (current === requestId) loading.value = false
  }
}

/**
 * Formatiert den Unix-Timestamp in ein lesbares, lokales Format
 */
const formatTimestamp = (timestamp) => {
  if (!timestamp) return ''
  return new Date(timestamp * 1000).toLocaleDateString(undefined, {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

watch(() => [props.clientUuid, props.productUuid, props.active], () => {
  if (props.active) fetchActivities()
}, { immediate: true })
</script>

<style lang="scss" scoped>
.ticky-activities-container {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
}

.activity-state {
  padding: 40px 16px;
  text-align: center;
  color: var(--color-text-maxcontrast);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  font-size: 13px;
}

.activity-timeline {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 16px;
  overflow-y: auto;
}

.activity-item {
  position: relative;
  border-left: 2px solid var(--color-border);
  padding-left: 16px;
  margin-left: 6px;
  transition: border-left-color 0.2s ease;

  // Der "Dot" auf der Zeitachse
  &::before {
    content: '';
    position: absolute;
    left: -5px;
    top: 4px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--color-border);
    transition: background 0.2s ease;
  }

  &:hover {
    border-left-color: var(--color-primary);

    &::before {
      background: var(--color-primary);
    }
  }

  .activity-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11px;
    color: var(--color-text-maxcontrast);
    margin-bottom: 4px;

    .activity-author {
      font-weight: 600;
      color: var(--color-text-main);
    }
  }

  .activity-content {
    font-size: 13px;
    line-height: 1.4;
    color: var(--color-main-text);

    // Falls Nextcloud <strong> Tags im parsedSubject mitsendet
    :deep(strong) {
      font-weight: 600;
      color: var(--color-text-main);
    }
  }
}
</style>

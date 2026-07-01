<template>
  <div class="ticky-activities-container">
    <div v-if="loading" class="activity-state activity-loading">
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

        <div class="activity-content" v-html="activity.parsedSubject"></div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { t, n } from '@nextcloud/l10n'
import axios from '@nextcloud/axios';
import { generateUrl } from '@nextcloud/router'

const props = defineProps({
  clientUuid: {
    type: String,
    required: true
  }
})

const activities = ref([])
const loading = ref(false)

/**
 * Holt die Aktivitäten vom neuen ActivityController
 */
const fetchActivities = async () => {
  if (!props.clientUuid) return

  loading.value = true
  try {
    const url = generateUrl(`/apps/ticky_crm/api/v1/clients/${props.clientUuid}/activities`)
    const response = await axios.get(url)
    activities.value = response.data
  } catch (error) {
    console.error('Fehler beim Laden der Ticky-Aktivitäten:', error)
  } finally {
    loading.value = false
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

onMounted(() => {
  fetchActivities()
})

watch(() => props.clientUuid, () => {
  fetchActivities()
})
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
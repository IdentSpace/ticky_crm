<template>
  <div class="ticky-table-container">
    <table class="ticky-table">
      <thead>
      <tr>
        <th
            v-for="col in columns"
            :key="col.header"
            :style="{ maxWidth: col.maxWidth }"
            :class="{ 'has-max-width': col.maxWidth !== 'none' }"
        >
          <span class="cell-content">{{ col.header }}</span>
        </th>
      </tr>
      </thead>
      <tbody>
      <tr
          v-for="(row, rowIndex) in value"
          :key="getRowKey(row, rowIndex)"
          :class="{ 'is-selected': isRowSelected(row, rowIndex) }"
          @click="handleRowClick(row, rowIndex)"
      >
        <td
            v-for="col in columns"
            :key="col.header"
            :style="{ maxWidth: col.maxWidth }"
            :class="{ 'has-max-width': col.maxWidth !== 'none' }"
        >
          <div class="cell-content">
            <slot :name="col.field" :data="row">
              {{ row[col.field] }}
            </slot>
          </div>
        </td>
      </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { useSlots, computed, ref, watch } from 'vue'

// eslint-disable-next-line
const props = defineProps({
  value: { type: Array, required: true }
})

const emit = defineEmits(['row-click'])
const slots = useSlots()

const selectedRow = ref(null)

const columns = computed(() => {
  return slots.default().map(vnode => ({
    header: vnode.props?.header,
    field: vnode.props?.field,
    maxWidth: vnode.props?.['max-width'] || vnode.props?.maxWidth || 'none'
  }))
})

const handleRowClick = (row, index) => {
  selectedRow.value = row
  emit('row-click', row)
}

const getRowKey = (row, index) => {
  if (row && typeof row === 'object') {
    return row.uuid || row.id || index
  }
  return index
}

const isRowSelected = (row, index) => {
  if (!selectedRow.value) return false

  if (row.uuid && selectedRow.value.uuid) return row.uuid === selectedRow.value.uuid
  if (row.id && selectedRow.value.id) return row.id === selectedRow.value.id

  return row === selectedRow.value
}

watch(() => props.value, () => {
  selectedRow.value = null
}, { deep: false })
</script>

<style lang="scss" scoped>
.ticky-table-container {
  width: 100%;
  overflow-x: auto;
}

.ticky-table {
  width: 100%;
  border-collapse: collapse;
  position: relative;

  /* Wichtig für fixierte Spaltenbreiten bei Tabellen */
  table-layout: fixed;

  th, td {
    padding: 12px;
    vertical-align: middle;

    // Wenn die Spalte eine max-width hat, aktivieren wir das Abschneiden
    &.has-max-width {
      overflow: hidden;

      .cell-content {
        display: block;
        width: 100%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
      }
    }
  }

  th {
    text-align: left;
    color: var(--color-text-maxcontrast);
    font-weight: bold;
    position: sticky;
    background-color: var(--color-main-background);
    border-bottom: 1px solid var(--color-main-background);
  }

  td {
    border-bottom: 1px solid var(--color-border-light);
    color: var(--color-main-text);
  }

  tr {
    cursor: pointer;

    &:hover {
      td {
        background-color: var(--color-background-hover);
      }
    }

    &.is-selected {
      td {
        background-color: var(--color-background-hover);
        color: var(--color-main-text);
        font-weight: 500;
      }
      border-left: 3px solid var(--color-primary);
    }
  }
}
</style>
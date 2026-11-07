<template>
  <div class="ticky-table-container">
    <table class="ticky-table" :style="{ minWidth: `${columns.length * 160}px` }">
      <thead>
        <tr>
          <th
            v-for="col in columns"
            :key="col.field"
            :aria-sort="col.sortable ? (sortField === col.field ? (sortDirection === 'asc' ? 'ascending' : 'descending') : 'none') : undefined"
            :style="{ maxWidth: col.maxWidth, textAlign: col.align }"
            :class="{ 'has-max-width': col.maxWidth !== 'none' }"
          >
            <button
              v-if="col.sortable"
              class="column-sort"
              type="button"
              :title="col.header"
              @click="toggleSort(col.field)"
            >
              <span class="cell-content">{{ col.header }}</span>
              <IconArrowUp v-if="sortField === col.field && sortDirection === 'asc'" :size="16" />
              <IconArrowDown v-else-if="sortField === col.field" :size="16" />
              <IconSort v-else :size="16" />
            </button>
            <span v-else class="cell-content">{{ col.header }}</span>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(row, rowIndex) in sortedRows"
          :key="getRowKey(row, rowIndex)"
          :class="{ 'is-selected': isRowSelected(row) }"
          @click="handleRowClick(row)"
        >
          <td
            v-for="col in columns"
            :key="col.header"
            :style="{ maxWidth: col.maxWidth, textAlign: col.align }"
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
import IconArrowUp from 'vue-material-design-icons/ArrowUp.vue'
import IconArrowDown from 'vue-material-design-icons/ArrowDown.vue'
import IconSort from 'vue-material-design-icons/UnfoldMoreHorizontal.vue'
import { sortTableRows } from '../utils/tableSort.js'

 
const props = defineProps({
  value: { type: Array, required: true },
  defaultSortField: { type: String, default: '' }
})

const emit = defineEmits(['row-click'])
const slots = useSlots()

const selectedRow = ref(null)
const sortField = ref(props.defaultSortField)
const sortDirection = ref('asc')
const toggleSort = field => {
  sortDirection.value = sortField.value === field && sortDirection.value === 'asc' ? 'desc' : 'asc'
  sortField.value = field
}
const sortedRows = computed(() => {
  const column = columns.value.find(col => col.field === sortField.value && col.sortable)
  return column ? sortTableRows(props.value, column.field, sortDirection.value, column.sortCompare) : props.value
})

const columns = computed(() => {
  return slots.default().map(vnode => ({
    header: vnode.props?.header,
    field: vnode.props?.field,
    maxWidth: vnode.props?.['max-width'] || vnode.props?.maxWidth || 'none',
    align: vnode.props?.align || 'left',
    sortable: vnode.props?.sortable === '' || vnode.props?.sortable === true,
    sortCompare: vnode.props?.['sort-compare'] || vnode.props?.sortCompare
  }))
})

const handleRowClick = (row) => {
  selectedRow.value = row
  emit('row-click', row)
}

const getRowKey = (row, index) => {
  if (row && typeof row === 'object') {
    return row.uuid || row.id || index
  }
  return index
}

const isRowSelected = (row) => {
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
  min-width: 0;
  max-width: 100%;
  overflow-x: auto;
}

.ticky-table {
  width: 100%;
  border-collapse: collapse;
  position: relative;

  /* Wichtig für fixierte Spaltenbreiten bei Tabellen */
  table-layout: fixed;

  .cell-content { overflow-wrap: anywhere; }

  th, td {
    padding: 12px;
    vertical-align: middle;
    overflow: hidden;

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

  .column-sort {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    width: 100%;
    padding: 0;
    margin: 0;
    border: 0;
    border-radius: 0;
    background: transparent;
    color: inherit;
    font: inherit;
    text-align: inherit;
    cursor: pointer;
    .cell-content {
      flex: 1;
      min-width: 0;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    :deep(.material-design-icon) { flex-shrink: 0; }

    &:focus-visible { outline: 2px solid var(--color-primary); outline-offset: 4px; }
  }

  th {
    text-align: left;
    color: var(--color-text-maxcontrast);
    font-weight: bold;
    position: sticky;
    background-color: var(--color-main-background);
    border-bottom: 1px solid var(--color-border, #ededed);
  }

  td {
    border-block: 1px solid var(--color-border, #ededed);
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

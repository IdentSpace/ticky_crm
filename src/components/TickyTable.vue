<template>
  <div class="ticky-table-container">
    <table class="ticky-table">
      <thead>
        <tr>
          <th v-for="col in columns" :key="col.header">
            {{ col.header }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(row, rowIndex) in value"
          :key="rowIndex"
          @click="emit('row-click', row)"
        >
          <td v-for="col in columns" :key="col.header">
            <slot :name="col.field" :data="row">
              {{ row[col.field] }}
            </slot>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
import { useSlots, computed } from 'vue'

// eslint-disable-next-line
const props = defineProps({
  value: { type: Array, required: true }
})

// Das Event für die Parent-Komponente deklarieren
const emit = defineEmits(['row-click'])

const slots = useSlots()

// Extrahiert die Metadaten aus den TickyColumn-Kindkomponenten
const columns = computed(() => {
  return slots.default().map(vnode => ({
    header: vnode.props?.header,
    field: vnode.props?.field
  }))
})
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

  th {
    text-align: left;
    padding: 12px;
    color: var(--color-text-maxcontrast);
    font-weight: bold;
    border-bottom: 1px solid var(--color-main-background);
    position: sticky;
    background-color: var(--color-main-background);
  }

  td {
    padding: 12px;
    border-bottom: 1px solid var(--color-border-light);
    color: var(--color-main-text);
    vertical-align: middle;
  }

  tr {
    cursor: pointer; // Zeigt das "Hand"-Symbol beim Hovern

    &:hover {
      td {
        background-color: var(--color-background-hover);
      }
    }
  }
}
</style>
<script setup>
defineProps({
  field: { type: String, required: true },
  header: { type: String, required: true },
  width: { type: String, default: 'auto' },
  maxWidth: { type: String, default: 'none' },
  align: { type: String, default: 'left', validator: value => ['left', 'center', 'right'].includes(value) },
  sortable: { type: Boolean, default: false },
  sortCompare: { type: Function, default: null }
})
</script>

<template>
  <slot />
</template>
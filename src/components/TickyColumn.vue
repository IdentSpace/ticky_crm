<script setup>
defineProps({
  field: { type: String, required: true },
  header: { type: String, required: true },
  width: { type: String, default: 'auto' },
  maxWidth: { type: String, default: 'none' }
})
</script>

<template>
  <slot />
</template>
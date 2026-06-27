<script setup>
defineProps({
  field: { type: String, required: true },
  header: { type: String, required: true },
  width: { type: String, default: 'auto' }
})
</script>

<template>
  <slot />
</template>
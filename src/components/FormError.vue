<template>
  <div
    ref="alert"
    class="form-error"
    role="alert"
    tabindex="-1"
  >
    <IconAlertCircle :size="24" aria-hidden="true" />
    <p>{{ message }}</p>
  </div>
</template>

<script setup>
import { nextTick, ref, watch } from 'vue'
import IconAlertCircle from 'vue-material-design-icons/AlertCircleOutline.vue'

const props = defineProps({ message: { type: String, required: true } })
const alert = ref(null)
watch(() => props.message, async () => {
  await nextTick()
  alert.value?.focus({ preventScroll: true })
  alert.value?.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
}, { immediate: true })
</script>

<style scoped>
.form-error {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 16px;
  color: var(--color-main-text);
  background: var(--color-error-background, var(--color-main-background));
  border: 2px solid var(--color-error);
  border-inline-start-width: 6px;
  border-radius: var(--border-radius);
  font-size: 14px;
  font-weight: 600;
  line-height: 1.5;
}
.form-error p { margin: 0; color: inherit; font-weight: inherit; }
.form-error > :first-child { flex-shrink: 0; color: var(--color-error); }
.form-error:focus-visible { outline: 2px solid var(--color-main-text); outline-offset: 2px; }
</style>

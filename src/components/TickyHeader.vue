<template>
  <header class="ticky-header">
    <div class="ticky-header__left">
      <h2 class="ticky-header__title">
        {{ title }}
      </h2>
      <span v-if="subtitle" class="ticky-header__subtitle">{{ subtitle }}</span>
    </div>

    <div class="ticky-header__actions">
      <slot name="actions" />
    </div>
  </header>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    required: true
  },
  subtitle: {
    type: String,
    default: ''
  }
})
</script>

<style lang="scss" scoped>
.ticky-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  // Links genug Platz für den NC-Navigation-Toggle (~50px) + eigener Abstand
  padding: 16px 24px 16px 64px;
  background-color: var(--color-main-background);
  border-bottom: 1px solid var(--color-border-light);
  position: sticky;
  top: 0;
  z-index: 100;
  min-height: 60px;
  box-sizing: border-box;

  &__left {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0; // Verhindert Overflow bei langem Subtitle
  }

  &__title {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    color: var(--color-main-text);
    letter-spacing: -0.2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  &__subtitle {
    font-size: 12px;
    color: var(--color-text-maxcontrast);
    font-weight: 400;
    white-space: nowrap;
  }

  &__actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0; // Buttons nie quetschen
    margin-left: 16px;
  }
}

@media (max-width: 768px) {
  .ticky-header {
    padding: 12px 16px 12px 56px;

    &__title { font-size: 17px; }
  }
}
</style>
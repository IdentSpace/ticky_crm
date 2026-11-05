<template>
  <NcTextField
    :model-value="decimalDisplay(value)"
    inputmode="decimal"
    @update:model-value="value = decimalDisplay($event)"
  />
</template>

<script setup>
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { decimalDisplay } from '../utils/decimal'

const value = defineModel({ type: [String, Number], default: '' })
</script>

<script setup lang="ts">
import { PRIORITIES, type Priority } from '@/api/notes'
import { PRIORITY_LABELS } from '@/priority'

const model = defineModel<Priority | null>({ required: true })

const options: { value: Priority | null; label: string }[] = [
  { value: null, label: 'Vše' },
  ...PRIORITIES.map((value) => ({ value, label: PRIORITY_LABELS[value] })),
]
</script>

<template>
  <div
    class="segmented"
    role="group"
    aria-label="Filtr podle priority"
  >
    <button
      v-for="option in options"
      :key="option.label"
      type="button"
      class="segmented__item"
      :aria-pressed="model === option.value"
      @click="model = option.value"
    >
      {{ option.label }}
    </button>
  </div>
</template>

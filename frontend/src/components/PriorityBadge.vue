<script setup lang="ts">
import type { Priority } from '@/api/notes'
import { PRIORITY_LABELS } from '@/priority'

defineProps<{ priority: Priority }>()
</script>

<template>
  <span :class="['badge', `badge--${priority}`]">{{ PRIORITY_LABELS[priority] }}</span>
</template>

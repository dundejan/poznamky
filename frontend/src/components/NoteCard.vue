<script setup lang="ts">
import type { Note } from '@/api/notes'
import { formatDateTime } from '@/format'
import PriorityBadge from './PriorityBadge.vue'

defineProps<{ note: Note; deleting: boolean }>()
defineEmits<{ delete: [note: Note] }>()
</script>

<template>
  <article class="card note-card">
    <header class="note-card__header">
      <h2 class="note-card__title">
        <RouterLink :to="{ name: 'note-edit', params: { id: note.id } }">
          {{ note.title }}
        </RouterLink>
      </h2>
      <PriorityBadge :priority="note.priority" />
    </header>

    <p
      v-if="note.content"
      class="note-card__content"
    >
      {{ note.content }}
    </p>

    <footer class="note-card__footer">
      <time
        :datetime="note.updatedAt"
        class="muted"
      >
        Upraveno {{ formatDateTime(note.updatedAt) }}
      </time>
      <div class="note-card__actions">
        <RouterLink
          :to="{ name: 'note-edit', params: { id: note.id } }"
          class="button button--small"
        >
          Upravit
        </RouterLink>
        <button
          type="button"
          class="button button--small button--danger"
          :disabled="deleting"
          @click="$emit('delete', note)"
        >
          {{ deleting ? 'Mažu…' : 'Smazat' }}
        </button>
      </div>
    </footer>
  </article>
</template>

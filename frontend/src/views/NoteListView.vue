<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useTemplateRef, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, deleteNote, errorMessage, isPriority, listNotes, type Note, type Priority } from '@/api/notes'
import NoteCard from '@/components/NoteCard.vue'
import PriorityFilter from '@/components/PriorityFilter.vue'
import { pluralizeNotes } from '@/format'
import { PRIORITY_LABELS } from '@/priority'

const route = useRoute()
const router = useRouter()
const heading = useTemplateRef<HTMLHeadingElement>('heading')

const notes = ref<Note[]>([])
const status = ref<'loading' | 'ready' | 'error'>('loading')
const loadError = ref('')
const actionError = ref<string | null>(null)
const flash = ref<string | null>(null)
const deletingId = ref<number | null>(null)

// The filter lives in the URL, so it can be bookmarked and shared.
const priority = computed<Priority | null>({
  get: () => (isPriority(route.query.priority) ? route.query.priority : null),
  set: (value) => void router.replace({ query: value ? { priority: value } : {} }),
})

let controller: AbortController | null = null

async function load(): Promise<void> {
  // Cancel the previous request so a stale response cannot win.
  controller?.abort()
  controller = new AbortController()
  const { signal } = controller

  status.value = 'loading'
  try {
    notes.value = await listNotes(priority.value, signal)
    status.value = 'ready'
  } catch (error) {
    if (signal.aborted) return
    loadError.value = errorMessage(error)
    status.value = 'error'
  }
}

async function remove(note: Note): Promise<void> {
  if (!window.confirm(`Opravdu smazat poznámku „${note.title}“?`)) {
    return
  }

  deletingId.value = note.id
  actionError.value = null
  flash.value = null
  try {
    await deleteNote(note.id)
  } catch (error) {
    // 404: already gone, treat it as deleted.
    if (!(error instanceof ApiError && error.status === 404)) {
      actionError.value = `Poznámku se nepodařilo smazat. ${errorMessage(error)}`
      return
    }
  } finally {
    deletingId.value = null
  }

  flash.value = 'Poznámka byla smazána.'
  // An in-flight list request may still contain the note.
  if (status.value === 'loading') {
    void load()
  } else {
    notes.value = notes.value.filter((item) => item.id !== note.id)
  }
  await nextTick()
  heading.value?.focus()
}

watch(priority, () => {
  flash.value = null
  actionError.value = null
  void load()
})

onMounted(() => {
  void load()

  const state = window.history.state as { flash?: unknown } | null
  if (typeof state?.flash === 'string') {
    flash.value = state.flash
    // Show the message only once, not again after a reload.
    window.history.replaceState({ ...state, flash: undefined }, '')
  }
})

onBeforeUnmount(() => controller?.abort())
</script>

<template>
  <div class="toolbar">
    <h1
      ref="heading"
      tabindex="-1"
    >
      Moje poznámky
    </h1>
    <PriorityFilter v-model="priority" />
  </div>

  <div role="status">
    <p
      v-if="flash"
      class="alert alert--success"
    >
      {{ flash }}
    </p>
  </div>
  <p
    v-if="actionError"
    class="alert alert--error"
    role="alert"
  >
    {{ actionError }}
  </p>

  <p
    v-if="status === 'loading' && notes.length === 0"
    class="muted"
    aria-live="polite"
  >
    Načítám poznámky…
  </p>

  <div
    v-else-if="status === 'error'"
    class="card state"
    role="alert"
  >
    <p>Poznámky se nepodařilo načíst. {{ loadError }}</p>
    <button
      type="button"
      class="button"
      @click="load"
    >
      Zkusit znovu
    </button>
  </div>

  <div
    v-else-if="status === 'ready' && notes.length === 0"
    class="card state"
  >
    <template v-if="priority">
      <p>Žádné poznámky s prioritou „{{ PRIORITY_LABELS[priority] }}“.</p>
      <button
        type="button"
        class="button"
        @click="priority = null"
      >
        Zobrazit všechny
      </button>
    </template>
    <template v-else>
      <p>Zatím tu nejsou žádné poznámky.</p>
      <RouterLink
        :to="{ name: 'note-create' }"
        class="button button--primary"
      >
        Vytvořit první poznámku
      </RouterLink>
    </template>
  </div>

  <template v-else-if="notes.length > 0">
    <p class="muted">
      {{ pluralizeNotes(notes.length) }}, seřazeno podle priority a poslední změny
    </p>
    <ul
      class="note-list"
      :aria-busy="status === 'loading'"
    >
      <li
        v-for="note in notes"
        :key="note.id"
      >
        <NoteCard
          :note="note"
          :deleting="deletingId === note.id"
          @delete="remove"
        />
      </li>
    </ul>
  </template>
</template>

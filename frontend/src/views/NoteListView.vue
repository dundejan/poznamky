<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, deleteNote, isPriority, listNotes, type Note, type Priority } from '@/api/notes'
import NoteCard from '@/components/NoteCard.vue'
import PriorityFilter from '@/components/PriorityFilter.vue'
import { errorMessage, pluralizeNotes } from '@/format'
import { PRIORITY_LABELS } from '@/priority'

const route = useRoute()
const router = useRouter()

const notes = ref<Note[]>([])
const status = ref<'loading' | 'ready' | 'error'>('loading')
const loadError = ref('')
const actionError = ref<string | null>(null)
const flash = ref<string | null>(null)
const deletingId = ref<number | null>(null)

// The filter lives in the URL (?priority=high), so a filtered list can be bookmarked or shared.
const priority = computed<Priority | null>({
  get: () => (isPriority(route.query.priority) ? route.query.priority : null),
  set: (value) => void router.replace({ query: value ? { priority: value } : {} }),
})

let controller: AbortController | null = null

async function load(): Promise<void> {
  // A slower response for a previous filter must not overwrite the current one.
  controller?.abort()
  controller = new AbortController()

  status.value = 'loading'
  try {
    notes.value = await listNotes(priority.value, controller.signal)
    status.value = 'ready'
  } catch (error) {
    if (error instanceof DOMException && error.name === 'AbortError') {
      return
    }
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
  try {
    await deleteNote(note.id)
  } catch (error) {
    // Already deleted elsewhere: the goal is reached, just drop it from the list.
    if (!(error instanceof ApiError && error.status === 404)) {
      actionError.value = `Poznámku se nepodařilo smazat. ${errorMessage(error)}`
      return
    }
  } finally {
    deletingId.value = null
  }
  flash.value = 'Poznámka byla smazána.'
  // A list request started before the delete could still bring the note back; reload instead.
  if (status.value === 'loading') {
    void load()
  } else {
    notes.value = notes.value.filter((item) => item.id !== note.id)
  }
}

watch(priority, load, { immediate: true })

onMounted(() => {
  const state = window.history.state as { flash?: unknown } | null
  if (typeof state?.flash === 'string') {
    flash.value = state.flash
    // Do not show the message again after a reload.
    window.history.replaceState({ ...state, flash: undefined }, '')
  }

  // Normalize an unknown filter value in the URL instead of silently ignoring it.
  if (route.query.priority !== undefined && priority.value === null) {
    void router.replace({ query: {} })
  }
})
</script>

<template>
  <div class="toolbar">
    <h1>Moje poznámky</h1>
    <PriorityFilter v-model="priority" />
  </div>

  <p
    v-if="flash"
    class="alert alert--success"
    role="status"
  >
    {{ flash }}
  </p>
  <p
    v-if="actionError"
    class="alert alert--error"
    role="alert"
  >
    {{ actionError }}
  </p>

  <p
    v-if="status === 'loading'"
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
    v-else-if="notes.length === 0"
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

  <template v-else>
    <p class="muted">
      {{ pluralizeNotes(notes.length) }}, řazeno podle priority a poslední změny
    </p>
    <ul class="note-list">
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

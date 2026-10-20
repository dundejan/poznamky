<script setup lang="ts">
import { ref, watch } from 'vue'
import { ApiError, errorMessage, getNote, updateNote, type Note } from '@/api/notes'
import NoteForm from '@/components/NoteForm.vue'
import { useNoteSave } from '@/composables/useNoteSave'
import { formatDateTime } from '@/format'

const props = defineProps<{ id: number }>()

const note = ref<Note | null>(null)
const status = ref<'loading' | 'ready' | 'not-found' | 'error'>('loading')
const loadError = ref('')

const { submitting, fieldErrors, formError, submit } = useNoteSave(
  (input) => updateNote(props.id, input),
  'Změny byly uloženy.',
)

async function load(id: number, signal?: AbortSignal): Promise<void> {
  status.value = 'loading'
  try {
    note.value = await getNote(id, signal)
    status.value = 'ready'
  } catch (error) {
    if (signal?.aborted) return
    if (error instanceof ApiError && error.status === 404) {
      status.value = 'not-found'
    } else {
      loadError.value = errorMessage(error)
      status.value = 'error'
    }
  }
}

// The router reuses this view when only the id changes.
watch(
  () => props.id,
  (id, _previous, onCleanup) => {
    const controller = new AbortController()
    onCleanup(() => controller.abort())
    void load(id, controller.signal)
  },
  { immediate: true },
)
</script>

<template>
  <h1>Upravit poznámku</h1>

  <p
    v-if="status === 'loading'"
    class="muted"
    aria-live="polite"
  >
    Načítám poznámku…
  </p>

  <div
    v-else-if="status === 'not-found'"
    class="card state"
  >
    <p>Tato poznámka neexistuje, možná byla smazána.</p>
    <RouterLink
      :to="{ name: 'notes' }"
      class="button"
    >
      Zpět na seznam
    </RouterLink>
  </div>

  <div
    v-else-if="status === 'error'"
    class="card state"
    role="alert"
  >
    <p>Poznámku se nepodařilo načíst. {{ loadError }}</p>
    <button
      type="button"
      class="button"
      @click="load(id)"
    >
      Zkusit znovu
    </button>
  </div>

  <template v-else-if="note">
    <p class="muted">
      Vytvořeno {{ formatDateTime(note.createdAt) }}, naposledy upraveno {{ formatDateTime(note.updatedAt) }}
    </p>

    <p
      v-if="formError"
      class="alert alert--error"
      role="alert"
    >
      {{ formError }}
    </p>

    <NoteForm
      :key="note.id"
      :initial="{ title: note.title, content: note.content, priority: note.priority }"
      submit-label="Uložit změny"
      :submitting="submitting"
      :errors="fieldErrors"
      @submit="submit"
    />
  </template>
</template>

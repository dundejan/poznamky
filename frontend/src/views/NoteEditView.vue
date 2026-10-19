<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { ApiError, getNote, updateNote, type Note } from '@/api/notes'
import NoteForm from '@/components/NoteForm.vue'
import { useNoteSave } from '@/composables/useNoteSave'
import { errorMessage, formatDateTime } from '@/format'

const props = defineProps<{ id: number }>()

const note = ref<Note | null>(null)
const status = ref<'loading' | 'ready' | 'not-found' | 'error'>('loading')
const loadError = ref('')

const { submitting, fieldErrors, formError, submit } = useNoteSave(
  (input) => updateNote(props.id, input),
  'Změny byly uloženy.',
)

async function load(): Promise<void> {
  status.value = 'loading'
  try {
    note.value = await getNote(props.id)
    status.value = 'ready'
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) {
      status.value = 'not-found'
    } else {
      loadError.value = errorMessage(error)
      status.value = 'error'
    }
  }
}

onMounted(load)
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
    <p>Tahle poznámka neexistuje. Možná byla smazána.</p>
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
    <p>{{ loadError }}</p>
    <button
      type="button"
      class="button"
      @click="load"
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
      :initial="{ title: note.title, content: note.content, priority: note.priority }"
      submit-label="Uložit změny"
      :submitting="submitting"
      :errors="fieldErrors"
      @submit="submit"
    />
  </template>
</template>

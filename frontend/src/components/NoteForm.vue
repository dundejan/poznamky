<script setup lang="ts">
import { computed, nextTick, reactive, useTemplateRef, watch } from 'vue'
import { PRIORITIES, type FieldErrors, type NoteInput } from '@/api/notes'
import { PRIORITY_LABELS } from '@/priority'

// Mirror the backend limits (Note::TITLE_MAX_LENGTH, Note::CONTENT_MAX_LENGTH); the API validates.
const TITLE_MAX = 200
const CONTENT_MAX = 10_000

const props = defineProps<{
  initial: NoteInput
  submitLabel: string
  submitting: boolean
  errors: FieldErrors
}>()

const emit = defineEmits<{ submit: [input: NoteInput] }>()

const form = reactive<NoteInput>({ ...props.initial })
const formEl = useTemplateRef<HTMLFormElement>('formEl')

// Code points, as the API counts them.
const contentLength = computed(() => [...form.content].length)

function fieldError(field: keyof NoteInput): string | undefined {
  return props.errors[field]?.[0]
}

// Move focus to the first invalid field.
watch(
  () => props.errors,
  async (errors) => {
    const field = (['title', 'priority', 'content'] as const).find((name) => errors[name])
    if (!field) return
    await nextTick()
    const selector = field === 'priority' ? 'input[name="priority"]' : `#note-${field}`
    formEl.value?.querySelector<HTMLElement>(selector)?.focus()
  },
)
</script>

<template>
  <form
    ref="formEl"
    class="card form"
    novalidate
    @submit.prevent="emit('submit', { ...form })"
  >
    <div class="field">
      <label
        for="note-title"
        class="field__label"
      >Název</label>
      <input
        id="note-title"
        v-model="form.title"
        type="text"
        class="input"
        autocomplete="off"
        required
        :maxlength="TITLE_MAX"
        :aria-invalid="!!fieldError('title')"
        :aria-describedby="fieldError('title') ? 'note-title-error' : undefined"
      >
      <p
        v-if="fieldError('title')"
        id="note-title-error"
        class="field__error"
      >
        {{ fieldError('title') }}
      </p>
    </div>

    <fieldset
      class="field"
      :aria-describedby="fieldError('priority') ? 'note-priority-error' : undefined"
    >
      <legend class="field__label">
        Priorita
      </legend>
      <div class="segmented">
        <label
          v-for="priority in PRIORITIES"
          :key="priority"
          class="segmented__item"
        >
          <input
            v-model="form.priority"
            type="radio"
            name="priority"
            :value="priority"
            class="visually-hidden"
          >
          {{ PRIORITY_LABELS[priority] }}
        </label>
      </div>
      <p
        v-if="fieldError('priority')"
        id="note-priority-error"
        class="field__error"
      >
        {{ fieldError('priority') }}
      </p>
    </fieldset>

    <div class="field">
      <label
        for="note-content"
        class="field__label"
      >Obsah</label>
      <textarea
        id="note-content"
        v-model="form.content"
        class="input textarea"
        rows="8"
        :aria-invalid="!!fieldError('content')"
        :aria-describedby="fieldError('content') ? 'note-content-hint note-content-error' : 'note-content-hint'"
      />
      <p
        id="note-content-hint"
        class="field__hint"
        :class="{ 'is-over': contentLength > CONTENT_MAX }"
      >
        {{ contentLength.toLocaleString('cs-CZ') }} / {{ CONTENT_MAX.toLocaleString('cs-CZ') }} znaků
      </p>
      <p
        v-if="fieldError('content')"
        id="note-content-error"
        class="field__error"
      >
        {{ fieldError('content') }}
      </p>
    </div>

    <div class="form__actions">
      <button
        type="submit"
        class="button button--primary"
        :disabled="submitting"
      >
        {{ submitting ? 'Ukládám…' : submitLabel }}
      </button>
      <RouterLink
        :to="{ name: 'notes' }"
        class="button"
      >
        Zrušit
      </RouterLink>
    </div>
  </form>
</template>

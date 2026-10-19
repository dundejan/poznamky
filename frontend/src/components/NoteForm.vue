<script setup lang="ts">
import { computed, reactive } from 'vue'
import { PRIORITIES, type FieldErrors, type NoteInput } from '@/api/notes'
import { PRIORITY_LABELS } from '@/priority'

// Mirrors the backend limit so the counter can warn early; the API stays the source of truth.
const CONTENT_MAX = 10_000

const props = defineProps<{
  initial: NoteInput
  submitLabel: string
  submitting: boolean
  errors: FieldErrors
}>()

const emit = defineEmits<{ submit: [input: NoteInput] }>()

const form = reactive<NoteInput>({ ...props.initial })

// Counts characters the way the API does (code points, not UTF-16 units).
const contentLength = computed(() => [...form.content].length)

function fieldError(field: keyof NoteInput): string | undefined {
  return props.errors[field]?.[0]
}
</script>

<template>
  <form
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
        :aria-invalid="!!fieldError('title')"
        aria-describedby="note-title-error"
      >
      <p
        v-if="fieldError('title')"
        id="note-title-error"
        class="field__error"
      >
        {{ fieldError('title') }}
      </p>
    </div>

    <fieldset class="field">
      <legend class="field__label">
        Priorita
      </legend>
      <div class="segmented">
        <label
          v-for="priority in PRIORITIES"
          :key="priority"
          class="segmented__item"
          :class="{ 'is-selected': form.priority === priority }"
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
        aria-describedby="note-content-hint note-content-error"
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

<script setup lang="ts">
import { createNote } from '@/api/notes'
import NoteForm from '@/components/NoteForm.vue'
import { useNoteSave } from '@/composables/useNoteSave'

const { submitting, fieldErrors, formError, submit } = useNoteSave(createNote, 'Poznámka byla vytvořena.')
</script>

<template>
  <h1>Nová poznámka</h1>

  <p
    v-if="formError"
    class="alert alert--error"
    role="alert"
  >
    {{ formError }}
  </p>

  <NoteForm
    :initial="{ title: '', content: '', priority: 'medium' }"
    submit-label="Vytvořit"
    :submitting="submitting"
    :errors="fieldErrors"
    @submit="submit"
  />
</template>

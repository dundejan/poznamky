import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { ApiError, type FieldErrors, type Note, type NoteInput } from '@/api/notes'
import { errorMessage } from '@/format'

/**
 * Shared submit flow of the create and edit forms: field errors from the API go
 * next to the fields, anything else is shown above the form.
 */
export function useNoteSave(save: (input: NoteInput) => Promise<Note>, successMessage: string) {
  const router = useRouter()
  const submitting = ref(false)
  const fieldErrors = ref<FieldErrors>({})
  const formError = ref<string | null>(null)

  async function submit(input: NoteInput): Promise<void> {
    submitting.value = true
    fieldErrors.value = {}
    formError.value = null

    try {
      await save(input)
      await router.push({ name: 'notes', state: { flash: successMessage } })
    } catch (error) {
      if (error instanceof ApiError && error.code === 'validation_failed') {
        fieldErrors.value = error.fields
      } else {
        formError.value = errorMessage(error)
      }
    } finally {
      submitting.value = false
    }
  }

  return { submitting, fieldErrors, formError, submit }
}
